<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    
    public function toResponse($request)
    {
        // 認証されたユーザーを取得
        $user = auth()->user();

        // admin_status が true（管理者）の場合
        if ($user && $user->admin_status) {
            return redirect()->intended('/admin/attendance/list');
        }

        // 一般ユーザーの場合
        return redirect()->intended('/attendance');
    }
}