<?php

namespace App\Enums;

enum ActivityType: string
{
    case Note = 'note';
    case Call = 'call';
    case StatusChange = 'status_change';
    case Assigned = 'assigned';
    case Created = 'created';
    case Imported = 'imported';

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
            self::Note => 'Ghi chú',
            self::Call => 'Cuộc gọi',
            self::StatusChange => 'Đổi trạng thái',
            self::Assigned => 'Giao sale',
            self::Created => 'Tạo mới',
            self::Imported => 'Import',
        };
    }
}
