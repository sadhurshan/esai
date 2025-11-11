<?php

namespace App\Enums;

enum PlatformAdminRole: string
{
    case Super = 'super';
    case Support = 'support';

    public function allows(string|self $ability): bool
    {
        $role = $ability instanceof self ? $ability : self::from($ability);

        if ($this === self::Super) {
            return true;
        }

        return $role === self::Support;
    }
}
