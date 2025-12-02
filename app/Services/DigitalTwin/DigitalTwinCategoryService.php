<?php

namespace App\Services\DigitalTwin;

use App\Models\DigitalTwin;
use App\Models\DigitalTwinCategory;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DigitalTwinCategoryService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, parent_id?: int|null, is_active?: bool}  $data
     */
    public function create(array $data): DigitalTwinCategory
    {
        $payload = $this->preparePayload($data);

        $category = DigitalTwinCategory::create($payload);

        $this->auditLogger->created($category, $category->toArray());

        return $category->fresh();
    }

    /**
     * @param  array{name?: string, slug?: string|null, description?: string|null, parent_id?: int|null, is_active?: bool}  $data
     */
    public function update(DigitalTwinCategory $category, array $data): DigitalTwinCategory
    {
        $before = $category->replicate()->toArray();

        $payload = $this->preparePayload($data, $category);

        $category->fill($payload);
        $category->save();

        $this->auditLogger->updated($category, $before, $category->toArray());

        return $category->fresh();
    }

    public function delete(DigitalTwinCategory $category): void
    {
        if (DigitalTwinCategory::query()->where('parent_id', $category->getKey())->exists()) {
            throw ValidationException::withMessages([
                'category' => ['Category has child categories and cannot be deleted.'],
            ]);
        }

        if (DigitalTwin::withTrashed()->where('category_id', $category->getKey())->exists()) {
            throw ValidationException::withMessages([
                'category' => ['Category is referenced by digital twins and cannot be deleted.'],
            ]);
        }

        $before = $category->toArray();
        $category->delete();

        $this->auditLogger->deleted($category, $before);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name?: string, slug?: string|null, description?: string|null, parent_id?: int|null, is_active?: bool}
     */
    private function preparePayload(array $data, ?DigitalTwinCategory $category = null): array
    {
        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }

        if (array_key_exists('parent_id', $data)) {
            $payload['parent_id'] = $data['parent_id'];
        }

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = (bool) $data['is_active'];
        }

        if (array_key_exists('slug', $data)) {
            $slugValue = $data['slug'];
            if (is_string($slugValue) && $slugValue !== '') {
                $payload['slug'] = $this->resolveUniqueSlug($slugValue, $category?->getKey());
            } elseif ($category === null && isset($data['name'])) {
                $payload['slug'] = $this->resolveUniqueSlug($data['name']);
            }
        } elseif ($category === null && isset($data['name'])) {
            $payload['slug'] = $this->resolveUniqueSlug($data['name']);
        }

        return $payload;
    }

    private function resolveUniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug;
        $suffix = 1;

        $query = DigitalTwinCategory::withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        while ($query->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;

            $query = DigitalTwinCategory::withTrashed()->where('slug', $slug);
            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }
        }

        return $slug !== '' ? $slug : Str::slug(Str::random(8));
    }
}
