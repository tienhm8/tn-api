<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Private channel per user. Chỉ chính chủ mới đăng ký được channel của mình,
| dùng cho thông báo realtime (nhắc lịch, khách mới được gán) qua Reverb.
|
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === $id;
});
