<?php

namespace App\Support;

class CompanyContext
{
    private static ?int $companyId = null;
    private static int $bypassDepth = 0;

    public static function set(?int $companyId): void
    {
        self::$companyId = $companyId !== null ? (int) $companyId : null;
    }

    public static function get(): ?int
    {
        return self::$companyId;
    }

    public static function clear(): void
    {
        self::$companyId = null;
    }

    public static function isBypassed(): bool
    {
        return self::$bypassDepth > 0;
    }

    /**
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    public static function bypass(callable $callback)
    {
        self::$bypassDepth++;

        try {
            return $callback();
        } finally {
            self::$bypassDepth--;
        }
    }

    /**
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    public static function forCompany(?int $companyId, callable $callback)
    {
        $previousId = self::$companyId;
        $previousBypass = self::$bypassDepth;
        self::set($companyId);
        self::$bypassDepth = 0;

        try {
            return $callback();
        } finally {
            self::set($previousId);
            self::$bypassDepth = $previousBypass;
        }
    }
}
