<?php

namespace App\Enums;

enum CustomerSource: string
{
    case Manual = 'manual';
    case Import = 'import';

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
            self::Manual => 'Nhập tay',
            self::Import => 'Import Excel',
        };
    }
}
