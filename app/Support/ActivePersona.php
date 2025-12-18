<?php

namespace App\Support;

class ActivePersona
{
    public const TYPE_BUYER = 'buyer';
    public const TYPE_SUPPLIER = 'supplier';

    /**
     * @var array{
     *     key:string,
     *     type:string,
     *     company_id:int,
     *     company_name?:string|null,
     *     company_status?:string|null,
     *     company_supplier_status?:string|null,
     *     role?:string|null,
     *     is_default?:bool,
     *     supplier_id?:int|null,
     *     supplier_name?:string|null,
     *     supplier_company_id?:int|null,
     *     supplier_company_name?:string|null
     * }
     */
    private array $attributes;

    private function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public static function fromArray(?array $payload): ?self
    {
        if ($payload === null) {
            return null;
        }

        $key = self::stringValue($payload['key'] ?? null);
        $type = self::stringValue($payload['type'] ?? null);
        $companyId = self::intValue($payload['company_id'] ?? null);

        if ($key === null || $type === null || $companyId === null) {
            return null;
        }

        if (! in_array($type, [self::TYPE_BUYER, self::TYPE_SUPPLIER], true)) {
            return null;
        }

        $attributes = [
            'key' => $key,
            'type' => $type,
            'company_id' => $companyId,
            'company_name' => self::stringValue($payload['company_name'] ?? null),
            'company_status' => self::stringValue($payload['company_status'] ?? null),
            'company_supplier_status' => self::stringValue($payload['company_supplier_status'] ?? null),
            'role' => self::stringValue($payload['role'] ?? null),
            'is_default' => self::boolValue($payload['is_default'] ?? null),
            'supplier_id' => self::intValue($payload['supplier_id'] ?? null),
            'supplier_name' => self::stringValue($payload['supplier_name'] ?? null),
            'supplier_company_id' => self::intValue($payload['supplier_company_id'] ?? null),
            'supplier_company_name' => self::stringValue($payload['supplier_company_name'] ?? null),
        ];

        return new self($attributes);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function key(): string
    {
        return $this->attributes['key'];
    }

    public function type(): string
    {
        return $this->attributes['type'];
    }

    public function companyId(): int
    {
        return $this->attributes['company_id'];
    }

    public function supplierId(): ?int
    {
        return $this->attributes['supplier_id'] ?? null;
    }

    public function supplierCompanyId(): ?int
    {
        return $this->attributes['supplier_company_id'] ?? null;
    }

    public function role(): ?string
    {
        return $this->attributes['role'] ?? null;
    }

    public function isSupplier(): bool
    {
        return $this->type() === self::TYPE_SUPPLIER;
    }

    private static function stringValue(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    private static function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true'], true)) {
            return true;
        }

        return false;
    }
}
