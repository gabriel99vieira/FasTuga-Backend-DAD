<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Customer;
use App\Traits\APIHybridAuthentication;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomersPolicy
{
    use HandlesAuthorization, APIHybridAuthentication;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(?User $user)
    {
        return $this->ifAuthenticated($user)->isManager();
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(?User $user, Customer $customer)
    {
        return $this->ifAuthenticated($user)->isManager()
            || ($this->ifAuthenticated($user)->isCustomer() && $this->ifAuthenticated($user)->customer->id == $customer->id);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(?User $user)
    {
        return $this->ifAuthenticated($user)->isAnonymous();
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(?User $user, Customer $customer)
    {
        return $this->ifAuthenticated($user)->isManager()
            || ($this->ifAuthenticated($user)->isCustomer() && $this->ifAuthenticated($user)->customer->id == $customer->id);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(?User $user, Customer $customer)
    {
        return $this->ifAuthenticated($user)->isManager();
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(?User $user, Customer $customer)
    {
        return $this->ifAuthenticated($user)->isManager();
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(?User $user, Customer $customer)
    {
        return $this->ifAuthenticated($user)->isManager();
    }
}
