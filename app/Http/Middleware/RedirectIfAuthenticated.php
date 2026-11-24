<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                // 1. 【管理者ログイン画面】にアクセスした場合
                if ($request->is('admin/login*')) {
                    if ($user && ! $user->admin_status) {
                        // 一般ユーザーがログイン中なら、ログアウトさせて管理者ログイン画面を表示
                        Auth::guard($guard)->logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        return $next($request);
                    }

                    // すでに管理者としてログイン中なら管理画面へ
                    return redirect('/admin/attendance/list');
                }

                // 2. 【一般用ログイン画面（例: /login）】にアクセスした場合
                if ($request->is('login*')) {
                    if ($user && $user->admin_status) {
                        // 管理者がログイン中なら、ログアウトさせて一般用ログイン画面を表示
                        Auth::guard($guard)->logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        return $next($request);
                    }

                    // すでに一般ユーザーとしてログイン中なら勤怠画面へ
                    return redirect(RouteServiceProvider::HOME);
                }

                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
