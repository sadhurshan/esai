<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetController extends ApiController
{
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->sendResetLink($request->validated());

        if ($status === Password::RESET_LINK_SENT) {
            return $this->ok(null, 'If an account exists for that email, a reset link has been sent.');
        }

        return $this->fail('Unable to send password reset link.', Response::HTTP_UNPROCESSABLE_ENTITY, [
            'email' => [trans($status)],
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $status = Password::reset(
            $payload,
            function (CanResetPasswordContract $user) use ($payload): void {
                $user->forceFill([
                    'password' => $payload['password'],
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->ok(null, 'Password reset successfully.');
        }

        return $this->fail('Unable to reset password.', Response::HTTP_UNPROCESSABLE_ENTITY, [
            'email' => [trans($status)],
        ]);
    }
}
