<?php

namespace App\Http\Requests\Admin;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmailTemplate::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:120', 'alpha_dash', 'unique:email_templates,key'],
            'name' => ['required', 'string', 'max:191'],
            'subject' => ['required', 'string', 'max:191'],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
            'enabled' => ['sometimes', 'boolean'],
            'meta' => ['sometimes', 'array'],
        ];
    }
}
