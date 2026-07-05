<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decode JWT from the Authorization header, resolve the local User,
 * and bind it to the auth context so policies & spatie roles work.
 */
class JwtAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);
        if (! $token) {
            return response()->json(['message' => 'Token không hợp lệ.'], 401);
        }

        $secret = config('jwt.secret');
        if (! $secret) {
            return response()->json(['message' => 'JWT chưa được cấu hình.'], 500);
        }

        try {
            JWT::$leeway = (int) config('jwt.leeway', 0);
            $claims = JWT::decode($token, new Key($secret, config('jwt.algo', 'HS256')));
        } catch (ExpiredException) {
            return response()->json(['message' => 'Token đã hết hạn.'], 401);
        } catch (SignatureInvalidException) {
            return response()->json(['message' => 'Token không hợp lệ.'], 401);
        } catch (\Throwable) {
            return response()->json(['message' => 'Token không hợp lệ.'], 401);
        }

        $user = User::find((int) ($claims->sub ?? 0));
        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Người dùng không tồn tại hoặc đã bị vô hiệu hóa.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('userId', $user->id);
        $request->attributes->set('roles', (array) ($claims->roles ?? []));

        return $next($request);
    }

    /**
     * Extract Bearer token from Authorization header.
     */
    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
