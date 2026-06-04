<?php

namespace App\Services;

use App\Exceptions\ApiResponseException;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function sendMail($request)
    {
        $isApi = $request->expectsJson();
        $validator = Validator::make($request->all(), [
            'user_name' => 'required|exists:users,username',
        ]);

        if ($validator->fails()) {
            if ($isApi) {
                throw new ApiResponseException($validator->errors()->first(), 422, false);
            }

            return back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = User::where('username', $request->user_name)->first();

        // Check if user is inactive
        if (!$user || $user->status == 0) {
            $message = 'User is inactive';

            if ($isApi) {
                throw new ApiResponseException($message, 403, false);
            }

            return back()
                ->withErrors($message)
                ->withInput();
        }

        $status = Password::sendResetLink([
            'username' => $user->username,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            if ($isApi) {
                throw new ApiResponseException(__($status), 200, false);
            }

            return back()
                ->withErrors(['user_name' => __($status)])
                ->withInput();
        }

        if ($isApi) {
            throw new ApiResponseException('Password Reset link sent', 200, true);
        }

        return back()->with('success', 'Password Reset link sent');
    }
}
