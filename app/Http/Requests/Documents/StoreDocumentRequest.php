<?php

namespace App\Http\Requests\Documents;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Http\Requests\ApiFormRequest;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends ApiFormRequest
{
    /**
     * @var array<string, class-string>
     */
    private const ENTITY_MAP = [
        'rfq' => RFQ::class,
        'quote' => Quote::class,
        'po' => PurchaseOrder::class,
        'invoice' => Invoice::class,
        'supplier' => Supplier::class,
        // TODO: clarify with spec for additional documentable entities (parts, orders, etc.).
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) config('documents.max_size_mb', 50) * 1024;
        $allowedVisibilities = config('documents.allowed_visibilities', ['private', 'company', 'public']);

        return [
            'entity' => ['required', 'string', Rule::in(array_keys(self::ENTITY_MAP))],
            'entity_id' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'string', Rule::in(DocumentKind::values())],
            'category' => ['required', 'string', Rule::in(DocumentCategory::values())],
            'visibility' => ['nullable', 'string', Rule::in($allowedVisibilities)],
            'expires_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
            'watermark' => ['nullable', 'array'],
            'file' => ['required', 'file', 'max:'.$maxKilobytes],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }

    public function documentableClass(): ?string
    {
        $entity = $this->validated('entity');

        return is_string($entity) ? (self::ENTITY_MAP[$entity] ?? null) : null;
    }

    public function documentableId(): int
    {
        return (int) $this->validated('entity_id');
    }
}
