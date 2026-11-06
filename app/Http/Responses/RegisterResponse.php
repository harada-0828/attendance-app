<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        // 登録・自動ログインされたユーザーは一般ユーザーなので出勤登録画面へ
        return redirect()->intended('/attendance/register');
    }
}