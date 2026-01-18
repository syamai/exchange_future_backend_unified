<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserWithdrawalAddress;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserWithdrawalAddressPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the userWithdrawalAddress.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UserWithdrawalAddress  $userWithdrawalAddress
     * @return mixed
     */
    public function view(User $user, UserWithdrawalAddress $userWithdrawalAddress)
    {
        return $user->id === $userWithdrawalAddress->user_id;
    }

    /**
     * Determine whether the user can create userWithdrawalAddresses.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
    }

    /**
     * Determine whether the user can update the userWithdrawalAddress.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UserWithdrawalAddress  $userWithdrawalAddress
     * @return mixed
     */
    public function update(User $user, UserWithdrawalAddress $userWithdrawalAddress)
    {
        return $user->id === $userWithdrawalAddress->user_id;
    }

    /**
     * Determine whether the user can delete the userWithdrawalAddress.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UserWithdrawalAddress  $userWithdrawalAddress
     * @return mixed
     */
    public function delete(User $user, UserWithdrawalAddress $userWithdrawalAddress)
    {
        return $user->id === $userWithdrawalAddress->user_id;
    }
}
