<?php

namespace App\Support;

final class Roles
{
    public const OWNER = 'owner';
    public const BRAND_MANAGER = 'brand_manager';
    public const OUTLET_MANAGER = 'outlet_manager';
    public const ACCOUNT_MANAGER = 'account_manager';
    public const DEVELOPER = 'developer';
    public const FINANCE = 'finance';
    public const SUPER_ADMIN = 'super_admin'; // global (spatie), bukan role membership

    public const SCOPE_COMPANY = 'company';
    public const SCOPE_BRAND = 'brand';
    public const SCOPE_OUTLET = 'outlet';

    public const CLIENT = [self::OWNER, self::BRAND_MANAGER, self::OUTLET_MANAGER];
    public const INTERNAL = [self::ACCOUNT_MANAGER, self::DEVELOPER, self::FINANCE];
    public const MEMBERSHIP = [
        self::OWNER, self::BRAND_MANAGER, self::OUTLET_MANAGER,
        self::ACCOUNT_MANAGER, self::DEVELOPER, self::FINANCE,
    ];

    public static function isInternal(string $role): bool
    {
        return in_array($role, self::INTERNAL, true);
    }

    public static function scopeTypeFor(string $role): string
    {
        return match ($role) {
            self::BRAND_MANAGER => self::SCOPE_BRAND,
            self::OUTLET_MANAGER => self::SCOPE_OUTLET,
            default => self::SCOPE_COMPANY,
        };
    }

    public static function label(string $role): string
    {
        return match ($role) {
            self::OWNER => 'Owner',
            self::BRAND_MANAGER => 'Brand Manager',
            self::OUTLET_MANAGER => 'Outlet Manager',
            self::ACCOUNT_MANAGER => 'Account Manager',
            self::DEVELOPER => 'Developer/Support',
            self::FINANCE => 'Finance',
            self::SUPER_ADMIN => 'Super Admin',
            default => $role,
        };
    }
}
