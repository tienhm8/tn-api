<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\User\UserRepositoryInterface;
use Firebase\JWT\JWT;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Xác thực email + mật khẩu, trả về token + user.
     *
     * @return array{token: string, token_type: string, expires_in: int, user: User}
     *
     * @throws AuthenticationException
     */
    public function attempt(string $email, string $password): array
    {
        /** @var User|null $user */
        $user = $this->userRepository->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->getAuthPassword())) {
            throw new AuthenticationException('Email hoặc mật khẩu không đúng.');
        }

        if (! $user->is_active) {
            throw new AuthenticationException('Tài khoản đã bị vô hiệu hóa.');
        }

        return $this->issueToken($user);
    }

    /**
     * Phát hành JWT cho user.
     *
     * @return array{token: string, token_type: string, expires_in: int, user: User}
     */
    public function issueToken(User $user): array
    {
        $ttlMinutes = (int) config('jwt.ttl', 1440);
        $issuedAt = Carbon::now();
        $expiresAt = $issuedAt->copy()->addMinutes($ttlMinutes);

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
            'roles' => $user->getRoleNames()->all(),
        ];

        $token = JWT::encode($payload, (string) config('jwt.secret'), (string) config('jwt.algo', 'HS256'));

        return [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlMinutes * 60,
            'user' => $user,
        ];
    }
}
