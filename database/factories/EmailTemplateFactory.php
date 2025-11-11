<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        $key = 'template_'.Str::lower(Str::random(6));

        return [
            'key' => $key,
            'name' => Str::title(str_replace('_', ' ', $key)),
            'subject' => 'Notification for {{ $name }}',
            'body_html' => '<p>Hello {{ $name }},</p><p>This is a notification.</p>',
            'body_text' => 'Hello {{ $name }}, This is a notification.',
            'enabled' => true,
            'meta' => ['category' => 'test'],
        ];
    }
}
