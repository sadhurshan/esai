<?php

namespace App\Http\Requests\Api\Ai;

use App\Enums\AiChatToolCall;
use App\Services\Ai\WorkspaceToolResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}
