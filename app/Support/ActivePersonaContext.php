<?php

namespace App\Support;

class ActivePersonaContext
{
    private static ?ActivePersona $persona = null;

    public static function set(?ActivePersona $persona): void
    {
        self::$persona = $persona;
    }

    public static function get(): ?ActivePersona
    {
        return self::$persona;
    }

    public static function clear(): void
    {
        self::$persona = null;
    }

    public static function companyId(): ?int
    {
        return self::$persona?->companyId();
    }

    public static function supplierId(): ?int
    {
        return self::$persona?->supplierId();
    }

    public static function supplierCompanyId(): ?int
    {
        return self::$persona?->supplierCompanyId();
    }

    public static function key(): ?string
    {
        return self::$persona?->key();
    }

    public static function isSupplier(): bool
    {
        return self::$persona?->isSupplier() ?? false;
    }

    public static function toArray(): ?array
    {
        return self::$persona?->toArray();
    }
}
