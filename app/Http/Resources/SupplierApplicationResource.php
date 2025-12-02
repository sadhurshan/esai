<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\SupplierDocumentResource;

/** @mixin \App\Models\SupplierApplication */
class SupplierApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'submitted_by' => $this->submitted_by,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'form_json' => $this->form_json,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'company' => CompanyResource::make($this->whenLoaded('company')),
            'documents' => $this->whenLoaded('documents', function () use ($request): array {
                $documents = $this->documents;
                if (method_exists($documents, 'load')) {
                    $documents->load('document');
                }

                return SupplierDocumentResource::collection($documents)->toArray($request);
            }, []),
        ];
    }
}
