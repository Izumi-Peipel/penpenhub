<?php

namespace App\Support;

final class AssetTypes
{
    public const WEBSITE = 'website';
    public const ORDERING_APP = 'ordering_app';
    public const POS = 'pos';
    public const DELIVERY_INTEGRATION = 'delivery_integration';
    public const PAYMENT_GATEWAY = 'payment_gateway';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::WEBSITE => 'Website',
            self::ORDERING_APP => 'Aplikasi pemesanan',
            self::POS => 'POS/Kasir',
            self::DELIVERY_INTEGRATION => 'Integrasi delivery',
            self::PAYMENT_GATEWAY => 'Payment gateway',
        ];
    }

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }
}
