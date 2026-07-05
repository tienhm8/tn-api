<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->attempt(
                (string) $request->validated('email'),
                (string) $request->validated('password'),
            );

            Log::info('Auth login success', ['user_id' => $result['user']->id, 'action' => 'login']);

            return response()->json([
                'token' => $result['token'],
                'token_type' => $result['token_type'],
                'expires_in' => $result['expires_in'],
                'user' => new UserResource($result['user']),
            ]);
        } catch (AuthenticationException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (\Throwable $e) {
            Log::error('Auth login failed', ['action' => 'login', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Đã có lỗi xảy ra.'], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        Log::info('Auth logout', ['user_id' => $request->user()?->id, 'action' => 'logout']);

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }
}
