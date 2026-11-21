<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * 新規ユーザー登録後のリダイレクト先レスポンスを生成する。
     *
     * @param  mixed  $request
     * @return Response
     */
    public function toResponse($request): Response
    {
        return redirect()->intended('/attendance');
    }
}