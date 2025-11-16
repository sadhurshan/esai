<?php

namespace Database\Factories;

use App\Enums\DocumentKind;
use App\Models\Company;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'documentable_type' => null,
            'documentable_id' => null,
            'kind' => DocumentKind::Other->value,
            'category' => 'other',
            'visibility' => 'company',
            'version_number' => 1,
            'expires_at' => null,
            'path' => 'documents/demo.pdf',
            'filename' => 'demo.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(1024, 20480),
            'hash' => $this->faker->sha256(),
            'watermark' => [],
            'meta' => [],
        ];
    }
}
