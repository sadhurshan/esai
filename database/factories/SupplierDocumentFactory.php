<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Models\Company;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierDocument>
 */
class SupplierDocumentFactory extends Factory
{
    protected $model = SupplierDocument::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'company_id' => Company::factory(),
            'type' => $this->faker->randomElement(['iso9001', 'iso14001', 'as9100', 'itar', 'reach', 'rohs', 'insurance', 'nda', 'other']),
            'path' => 'supplier-documents/'.$this->faker->uuid().'.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(50_000, 5_000_000),
            'issued_at' => $this->faker->dateTimeBetween('-2 years', '-6 months'),
            'expires_at' => $this->faker->dateTimeBetween('+1 month', '+2 years'),
            'status' => 'valid',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SupplierDocument $supplierDocument): void {
            if ($supplierDocument->document_id !== null) {
                return;
            }

            $document = Document::factory()->create([
                'company_id' => $supplierDocument->company_id,
                'documentable_type' => Supplier::class,
                'documentable_id' => $supplierDocument->supplier_id,
                'kind' => DocumentKind::Certificate->value,
                'category' => DocumentCategory::Qa->value,
                'visibility' => 'company',
                'expires_at' => $supplierDocument->expires_at,
                'path' => $supplierDocument->path,
                'filename' => basename($supplierDocument->path) ?: 'supplier-document-'.$supplierDocument->id.'.pdf',
                'mime' => $supplierDocument->mime,
                'size_bytes' => $supplierDocument->size_bytes,
            ]);

            $supplierDocument->forceFill(['document_id' => $document->id])->save();
        });
    }
}
