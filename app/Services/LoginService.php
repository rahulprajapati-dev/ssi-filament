<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\ApiResponseException;

class LoginService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function authenticate(string $user_name, string $password): User
    {
        $user = User::where('username', $user_name)->first();

        if (! $user) {
            throw new ApiResponseException('Incorrect username. Please try again', 200, false);
        }

        if (! Hash::check($password, $user->password)) {
            throw new ApiResponseException('Incorrect password. Please try again', 200, false);
        }

        if ($user->status == 0) {
            throw new ApiResponseException('User account is deactivated', 401, false);
        }

        // If user is linked to a dealer and dealer is inactive
        if ($user->dealer && (int) $user->dealer->active === 0) {
            throw new ApiResponseException(
                'Dealer account is inactive. Please contact administrator.',
                200,
                false
            );
        }

        return $user;
    }
}
