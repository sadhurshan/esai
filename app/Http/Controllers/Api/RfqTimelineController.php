<?php

namespace App\Http\Controllers\Api;

use App\Enums\RfqClarificationType;
use App\Models\RFQ;
use App\Models\RfqClarification;
use App\Models\RfqInvitation;
use App\Models\User;
use App\Policies\RfqClarificationPolicy;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RfqTimelineController extends ApiController
{
    public function __construct(private readonly RfqClarificationPolicy $clarificationPolicy)
    {
    }

    public function __invoke(RFQ $rfq, Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user] = $context;

        if (! $this->clarificationPolicy->viewClarifications($user, $rfq)) {
            return $this->fail('Forbidden', 403);
        }

        $timeline = CompanyContext::forCompany((int) $rfq->company_id, function () use ($rfq): array {
            $rfq->loadMissing([
                'creator:id,name,email',
                'invitations' => static function ($query): void {
                    $query->with([
                        'inviter:id,name,email',
                        'supplier:id,name',
                    ])->orderBy('created_at');
                },
                'clarifications' => static function ($query): void {
                    $query->with(['user:id,name,email'])->orderBy('created_at');
                },
                'awards' => static function ($query): void {
                    $query->with(['awarder:id,name,email'])->orderBy('awarded_at');
                },
                'deadlineExtensions' => static function ($query): void {
                    $query->with(['extendedBy:id,name,email'])->orderBy('created_at');
                },
            ]);

            return $this->buildTimeline($rfq);
        });

        if ($this->shouldRestrictTimelineForSupplier($user, $rfq)) {
            $timeline = $this->filterTimelineForSupplier($timeline);
        }

        return $this->ok([
            'items' => $timeline,
        ]);
    }

    private function shouldRestrictTimelineForSupplier(User $user, RFQ $rfq): bool
    {
        if ($user->isPlatformAdmin()) {
            return false;
        }

        return ! ($user->company_id !== null && (int) $user->company_id === (int) $rfq->company_id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $timeline
     * @return array<int, array<string, mixed>>
     */
    private function filterTimelineForSupplier(array $timeline): array
    {
        $visibleEvents = [
            'created',
            'published',
            'question_posted',
            'answer_posted',
            'amended',
            'deadline_extended',
            'awarded',
            'closed',
        ];

        return (new Collection($timeline))
            ->filter(static fn (array $entry) => in_array($entry['event'] ?? '', $visibleEvents, true))
            ->map(static function (array $entry): array {
                if (isset($entry['context']) && is_array($entry['context'])) {
                    unset($entry['context']['supplier_name'], $entry['context']['supplier_email']);
                }

                return $entry;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTimeline(RFQ $rfq): array
    {
        $entries = new Collection();

        $entries->push($this->makeEntry('created', $rfq->created_at, $rfq->creator, [
            'number' => $rfq->number,
            'item_name' => $rfq->item_name,
        ]));

        if ($rfq->sent_at !== null) {
            $entries->push($this->makeEntry('published', $rfq->sent_at, $rfq->creator, [
                'deadline_at' => optional($rfq->deadline_at)?->toIso8601String(),
            ]));
        }

        foreach ($rfq->invitations as $invitation) {
            $entries->push($this->makeInvitationEntry($invitation));
        }

        foreach ($rfq->clarifications as $clarification) {
            $entries->push($this->makeClarificationEntry($clarification));
        }

        foreach ($rfq->deadlineExtensions as $extension) {
            $entries->push($this->makeEntry('deadline_extended', $extension->created_at, $extension->extendedBy, array_filter([
                'previous_due_at' => optional($extension->previous_due_at)?->toIso8601String(),
                'new_due_at' => optional($extension->new_due_at)?->toIso8601String(),
                'reason' => Str::limit((string) $extension->reason, 240),
            ])));
        }

        if ($rfq->awards->isNotEmpty()) {
            $latestAward = $rfq->awards->sortByDesc('awarded_at')->first();
            $entries->push($this->makeEntry('awarded', optional($latestAward)->awarded_at ?? $rfq->updated_at, $latestAward?->awarder, [
                'awards_count' => $rfq->awards->count(),
            ]));
        } elseif ($rfq->status === 'awarded') {
            $entries->push($this->makeEntry('awarded', $rfq->updated_at, $rfq->creator));
        }

        if (in_array($rfq->status, ['closed', 'cancelled'], true)) {
            $entries->push($this->makeEntry('closed', $rfq->close_at ?? $rfq->updated_at, $rfq->creator, [
                'status' => $rfq->status,
            ]));
        }

        return $entries
            ->filter()
            ->sortBy(static fn (array $entry) => $entry['created_at'] ?? '')
            ->values()
            ->all();
    }

    private function makeInvitationEntry(RfqInvitation $invitation): array
    {
        return $this->makeEntry(
            'invitation_sent',
            $invitation->created_at,
            $invitation->inviter,
            array_filter([
                'supplier_name' => optional($invitation->supplier)->name,
                'status' => $invitation->status,
            ])
        );
    }

    private function makeClarificationEntry(RfqClarification $clarification): array
    {
        $event = match ($clarification->type) {
            RfqClarificationType::Question => 'question_posted',
            RfqClarificationType::Answer => 'answer_posted',
            RfqClarificationType::Amendment => 'amended',
            default => 'question_posted',
        };

        return $this->makeEntry($event, $clarification->created_at, $clarification->user, [
            'body' => Str::limit((string) $clarification->message, 180),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function makeEntry(string $event, ?Carbon $timestamp, ?User $actor = null, array $context = []): array
    {
        return [
            'event' => $event,
            'created_at' => $this->formatTimestamp($timestamp),
            'updated_at' => null,
            'deleted_at' => null,
            'actor' => $this->formatActor($actor),
            'context' => empty($context) ? null : $context,
        ];
    }

    private function formatActor(?User $actor): ?array
    {
        if ($actor === null) {
            return null;
        }

        return [
            'id' => (string) $actor->getRouteKey(),
            'name' => $actor->name ?? $actor->email,
        ];
    }

    private function formatTimestamp(?Carbon $timestamp): ?string
    {
        return $timestamp?->toIso8601String();
    }
}
