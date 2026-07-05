<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case Caring = 'caring';
    case Won = 'won';
    case Lost = 'lost';

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
            self::New => 'Mới',
            self::Assigned => 'Đã giao sale',
            self::Caring => 'Đang chăm sóc',
            self::Won => 'Chốt thành công',
            self::Lost => 'Không thành công',
        };
    }
}
