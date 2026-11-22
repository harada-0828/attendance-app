<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * 新規ユーザー登録後のリダイレクト先レスポンスを生成する。
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && !$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended('/attendance');
    }
}