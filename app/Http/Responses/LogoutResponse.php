<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * ログアウト処理後のリダイレクト先を制御する
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        // 管理者ガード（admin）でログインしていた場合は、管理者ログイン画面へ
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        // それ以外（一般ユーザーなど）は、通常のログイン画面へ
        return redirect()->route('login');
    }
}
