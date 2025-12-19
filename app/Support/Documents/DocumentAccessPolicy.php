<?php

namespace App\Support\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class DocumentAccessPolicy
{
    /**
     * @param array<int|string|null> $docIds
     * @return array{allowed: array<string>, denied: array<string>}
     */
    public function evaluate(User $user, int $companyId, array $docIds): array
    {
        $normalized = $this->normalizeDocIds($docIds);

        if ($normalized === []) {
            return ['allowed' => [], 'denied' => []];
        }

        $documents = $this->fetchDocuments($companyId, $normalized);

        $allowed = [];
        $denied = [];

        foreach ($normalized as $docId) {
            $document = $documents->get($docId);

            if (! $document instanceof Document) {
                $denied[] = $docId;
                continue;
            }

            if (Gate::forUser($user)->allows('view', $document)) {
                $allowed[] = $docId;
            } else {
                $denied[] = $docId;
            }
        }

        return ['allowed' => $allowed, 'denied' => $denied];
    }

    /**
     * @param array<int, array<string, mixed>> $hits
     * @return array{items: array<int, array<string, mixed>>, denied: array<string>}
     */
    public function filterSearchHits(User $user, int $companyId, array $hits): array
    {
        $docIds = array_map(static fn ($hit) => $hit['doc_id'] ?? null, $hits);
        $review = $this->evaluate($user, $companyId, $docIds);

        $filtered = [];
        foreach ($hits as $hit) {
            $docId = (string) ($hit['doc_id'] ?? '');

            if ($docId === '' || ! in_array($docId, $review['allowed'], true)) {
                continue;
            }

            $filtered[] = $hit;
        }

        return [
            'items' => $filtered,
            'denied' => $review['denied'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $citations
     * @return array{items: array<int, array<string, mixed>>, denied: array<string>}
     */
    public function filterCitations(User $user, int $companyId, array $citations): array
    {
        $docIds = array_map(static fn ($citation) => $citation['doc_id'] ?? null, $citations);
        $review = $this->evaluate($user, $companyId, $docIds);

        $filtered = [];
        foreach ($citations as $citation) {
            $docId = (string) ($citation['doc_id'] ?? '');

            if ($docId === '' || ! in_array($docId, $review['allowed'], true)) {
                continue;
            }

            $filtered[] = $citation;
        }

        return [
            'items' => $filtered,
            'denied' => $review['denied'],
        ];
    }

    /**
     * @param array<int|string|null> $docIds
     * @return array<string>
     */
    private function normalizeDocIds(array $docIds): array
    {
        $normalized = [];

        foreach ($docIds as $docId) {
            if (is_int($docId)) {
                if ($docId > 0) {
                    $normalized[] = (string) $docId;
                }
                continue;
            }

            if (is_string($docId) && $docId !== '' && ctype_digit($docId)) {
                $normalized[] = (string) ((int) $docId);
                continue;
            }

            if (is_numeric($docId)) {
                $value = (int) $docId;
                if ($value > 0) {
                    $normalized[] = (string) $value;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string> $docIds
     */
    private function fetchDocuments(int $companyId, array $docIds): Collection
    {
        return Document::query()
            ->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)
                    ->orWhere('visibility', 'public');
            })
            ->whereIn('id', array_map('intval', $docIds))
            ->get()
            ->keyBy(fn (Document $document): string => (string) $document->getKey());
    }
}
