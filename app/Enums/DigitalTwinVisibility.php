<?php

namespace App\Enums;

enum DigitalTwinVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    public function isPublic(): bool
    {
        return $this === self::Public;
    }
}
