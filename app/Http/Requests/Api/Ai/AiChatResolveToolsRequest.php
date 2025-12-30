<?php

namespace App\Http\Requests\Api\Ai;

use App\Enums\AiChatToolCall;
use App\Services\Ai\WorkspaceToolResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AiChatResolveToolsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $toolNames = AiChatToolCall::values();

        return [
            'tool_calls' => ['required', 'array', 'min:1', 'max:' . WorkspaceToolResolver::MAX_TOOL_CALLS],
            'tool_calls.*.tool_name' => ['required', 'string', 'max:128', Rule::in($toolNames)],
            'tool_calls.*.call_id' => ['required', 'string', 'max:128'],
            'tool_calls.*.arguments' => ['nullable', 'array'],
            'context' => ['nullable', 'array'],
            'context.locale' => ['sometimes', 'string', 'max:10'],
        ];
    }

    /**
     * @return list<array{tool_name:string,call_id:string,arguments:array<string, mixed>}>|
     *         list<array{tool_name:string,call_id:string}>
     */
    public function toolCalls(): array
    {
        $calls = $this->validated('tool_calls') ?? [];

        return is_array($calls) ? $calls : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function messageContext(): array
    {
        $context = $this->validated('context') ?? [];

        return is_array($context) ? $context : [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $toolCalls = $this->input('tool_calls', []);
            $context = $this->input('context', []);

            if (! is_array($toolCalls)) {
                return;
            }

            foreach ($toolCalls as $index => $call) {
                if (! is_array($call)) {
                    continue;
                }

                $toolName = (string) ($call['tool_name'] ?? '');

                if ($toolName !== AiChatToolCall::CreateDisputeDraft->value) {
                    continue;
                }

                $argumentPath = sprintf('tool_calls.%d.arguments', $index);
                $arguments = $call['arguments'] ?? null;

                if (! is_array($arguments)) {
                    $validator->errors()->add($argumentPath, 'workspace.create_dispute_draft requires an arguments object.');
                    continue;
                }

                if (! $this->hasInvoiceReference($arguments, $context)) {
                    $validator->errors()->add($argumentPath . '.invoice_id', 'workspace.create_dispute_draft requires invoice_id or invoice_number.');
                }
            }
        });
    }

    private function hasInvoiceReference(array $arguments, mixed $context): bool
    {
        $invoiceId = $this->stringValue($arguments['invoice_id'] ?? $arguments['id'] ?? null);
        $invoiceNumber = $this->stringValue($arguments['invoice_number'] ?? null);

        if ($invoiceId !== null || $invoiceNumber !== null) {
            return true;
        }

        if (! is_array($context)) {
            return false;
        }

        $contextPayload = isset($context['context']) && is_array($context['context'])
            ? $context['context']
            : [];

        $invoiceBlock = isset($contextPayload['invoice']) && is_array($contextPayload['invoice'])
            ? $contextPayload['invoice']
            : [];

        if ($invoiceBlock !== []) {
            $ctxInvoiceId = $this->stringValue($invoiceBlock['invoice_id'] ?? $invoiceBlock['id'] ?? null);
            $ctxInvoiceNumber = $this->stringValue($invoiceBlock['invoice_number'] ?? $invoiceBlock['number'] ?? null);

            if ($ctxInvoiceId !== null || $ctxInvoiceNumber !== null) {
                return true;
            }
        }

        $reference = isset($arguments['dispute_reference']) && is_array($arguments['dispute_reference'])
            ? $arguments['dispute_reference']
            : [];

        $referenceInvoice = isset($reference['invoice']) && is_array($reference['invoice'])
            ? $reference['invoice']
            : [];

        $refInvoiceId = $this->stringValue($referenceInvoice['id'] ?? null);
        $refInvoiceNumber = $this->stringValue($referenceInvoice['number'] ?? null);

        return $refInvoiceId !== null || $refInvoiceNumber !== null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
