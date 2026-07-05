<?php

namespace App\Enums;

enum LostReason: string
{
    case NoNeed = 'no_need';
    case Unreachable = 'unreachable';

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
            self::NoNeed => 'Không có nhu cầu',
            self::Unreachable => 'Không liên lạc được',
        };
    }
}
