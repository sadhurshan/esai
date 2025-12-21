<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;

class AiChatSendMessageRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:4000'],
            'context' => ['sometimes', 'array'],
            'ui_mode' => ['sometimes', 'string', 'max:50'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['array'],
            'stream' => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function message(): string
    {
        return (string) $this->validated()['message'];
    }

    /**
     * @return array<string, mixed>
     */
    public function contextPayload(): array
    {
        $payload = $this->validated()['context'] ?? [];

        return is_array($payload) ? $payload : [];
    }

    public function uiMode(): ?string
    {
        $value = $this->validated()['ui_mode'] ?? null;

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attachments(): array
    {
        $attachments = $this->validated()['attachments'] ?? [];

        if (! is_array($attachments)) {
            return [];
        }

        return collect($attachments)
            ->filter(static fn ($attachment) => is_array($attachment))
            ->map(static function (array $attachment): array {
                return $attachment;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function messageContext(): array
    {
        $payload = array_filter([
            'context' => $this->contextPayload(),
            'ui_mode' => $this->uiMode(),
            'attachments' => $this->attachments(),
        ], static fn ($value) => $value !== null && $value !== [] && $value !== '');

        return $payload;
    }

    public function wantsStream(): bool
    {
        return (bool) ($this->validated()['stream'] ?? false);
    }
}
