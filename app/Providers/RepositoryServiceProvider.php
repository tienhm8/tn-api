<?php

namespace App\Providers;

use App\Repositories\User\UserRepository;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository interface → Eloquent implementation bindings.
     *
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        UserRepositoryInterface::class => UserRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
