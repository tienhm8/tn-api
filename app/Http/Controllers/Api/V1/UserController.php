<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserBriefResource;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
    ) {}

    /**
     * Danh sách user active theo role (mặc định `sale`) — dùng cho gán lại khách.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $role = (string) $request->string('role', 'sale');

        return UserBriefResource::collection($this->userService->activeUsersByRole($role));
    }
}
