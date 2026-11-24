<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * ログインユーザーの権限に応じてリダイレクト先レスポンスを生成する。
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = auth()->user();

        if ($user && $user->admin_status) {
            return redirect()->intended('/admin/attendance/list');
        }

        return redirect()->intended('/attendance');
    }
}
