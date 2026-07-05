<?php

namespace App\Enums;

enum LeadSource: string
{
    case Facebook = 'facebook';
    case Website = 'website';
    case Zalo = 'zalo';
    case Referral = 'referral';
    case Other = 'other';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Website => 'Website',
            self::Zalo => 'Zalo',
            self::Referral => 'Giới thiệu',
            self::Other => 'Khác',
        };
    }
}
