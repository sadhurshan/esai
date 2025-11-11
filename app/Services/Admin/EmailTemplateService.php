<?php

namespace App\Services\Admin;

use App\Models\EmailTemplate;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;

class EmailTemplateService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): EmailTemplate
    {
        $template = EmailTemplate::create($attributes);

        $this->auditLogger->created($template);

        return $template->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(EmailTemplate $template, array $attributes): EmailTemplate
    {
        $before = Arr::only($template->getOriginal(), ['key', 'name', 'subject', 'body_html', 'body_text', 'enabled', 'meta']);

        $template->fill($attributes)->save();

        $template->refresh();

        $this->auditLogger->updated($template, $before, Arr::only($template->attributesToArray(), array_keys($before)));

        return $template;
    }

    public function delete(EmailTemplate $template): void
    {
        $before = Arr::only($template->attributesToArray(), ['key', 'name', 'subject']);

        $template->delete();

        $this->auditLogger->deleted($template, $before);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{html:string,text:string}
     */
    public function preview(EmailTemplate $template, array $payload): array
    {
        $html = Blade::render($template->body_html, $payload);

        $text = $template->body_text !== null
            ? Blade::render($template->body_text, $payload)
            : strip_tags($html);

        return [
            'html' => $html,
            'text' => $text,
        ];
    }
}
