<?php

namespace App\Providers;

use App\Address;
use App\Group;
use App\Permission;
use App\Person;
use App\PersonAddress;
use App\Policies\AddressPolicy;
use App\Policies\GroupPolicy;
use App\Policies\PersonAddressPolicy;
use App\Policies\PersonPolicy;
use App\Policies\UserPolicy;
use App\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Person::class        => PersonPolicy::class,
        PersonAddress::class => PersonAddressPolicy::class,
        Group::class         => GroupPolicy::class,
        User::class          => UserPolicy::class,

        # just for temp
        Address::class       => AddressPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        Permission::generateGates();

        Passport::routes();
    }
}
