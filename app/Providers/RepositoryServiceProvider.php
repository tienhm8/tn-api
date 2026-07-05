<?php

namespace App\Providers;

use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Customer\CustomerRepositoryInterface;
use App\Repositories\CustomerActivity\CustomerActivityRepository;
use App\Repositories\CustomerActivity\CustomerActivityRepositoryInterface;
use App\Repositories\Service\ServiceRepository;
use App\Repositories\Service\ServiceRepositoryInterface;
use App\Repositories\Setting\SettingRepository;
use App\Repositories\Setting\SettingRepositoryInterface;
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
        SettingRepositoryInterface::class => SettingRepository::class,
        ServiceRepositoryInterface::class => ServiceRepository::class,
        CustomerRepositoryInterface::class => CustomerRepository::class,
        CustomerActivityRepositoryInterface::class => CustomerActivityRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
