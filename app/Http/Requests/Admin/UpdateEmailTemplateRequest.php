<?php

namespace App\Http\Requests\Admin;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
    $template = $this->route('email_template') ?? $this->route('template');

        return $template instanceof EmailTemplate
            ? ($this->user()?->can('update', $template) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
    $template = $this->route('email_template') ?? $this->route('template');

        return [
            'key' => ['sometimes', 'string', 'max:120', 'alpha_dash', 'unique:email_templates,key,'.$template?->id],
            'name' => ['sometimes', 'string', 'max:191'],
            'subject' => ['sometimes', 'string', 'max:191'],
            'body_html' => ['sometimes', 'string'],
            'body_text' => ['sometimes', 'nullable', 'string'],
            'enabled' => ['sometimes', 'boolean'],
            'meta' => ['sometimes', 'array'],
        ];
    }
}
