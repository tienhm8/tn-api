<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Missed = 'missed';
    case Cancelled = 'cancelled';

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
            self::Scheduled => 'Đã đặt lịch',
            self::Completed => 'Đã hoàn thành',
            self::Missed => 'Trễ hẹn',
            self::Cancelled => 'Đã hủy',
        };
    }
}
