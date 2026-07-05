<?php

namespace App\Repositories\Setting;

use App\Repositories\RepositoryInterface;

interface SettingRepositoryInterface extends RepositoryInterface
{
    /**
     * Đọc giá trị setting theo key (có cache), trả $default nếu không có.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Ghi/ cập nhật giá trị setting theo key (xóa cache liên quan).
     */
    public function put(string $key, ?string $value): void;

    /**
     * Xóa cache của một key.
     */
    public function forgetKey(string $key): void;
}
