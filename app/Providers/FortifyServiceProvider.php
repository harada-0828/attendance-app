<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Laravel\Fortify\Contracts\LoginResponse;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use App\Http\Responses\RegisterResponse as CustomRegisterResponse;
use Laravel\Fortify\Contracts\CreatesNewUsers; 

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 新規ユーザー作成アクションのバインド
        $this->app->singleton(CreatesNewUsers::class, CreateNewUser::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ログイン成功時のレスポンスをカスタムクラスに差し替え
        $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);

        // 会員登録成功時のレスポンスをカスタムクラスに差し替え
        $this->app->singleton(RegisterResponse::class, CustomRegisterResponse::class);

        // 1. 会員登録画面のビュー指定
        Fortify::registerView(function () {
            return view('user.register');
        });

        // 2. ログイン画面のビュー指定（URLで一般と管理者を切り分け）
        Fortify::loginView(function (Request $request) {
            if ($request->is('admin/login*')) {
                // 管理者用ログイン画面のビューを指定（admin-login.blade.php）
                return view('admin.admin-login');
            }
            // 一般ユーザー用ログイン画面のビューを指定
            return view('user.user-login');
        });

        // 3. ログイン時のレートリミットの設定
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;

            return Limit::perMinute(5)->by($email . $request->ip());
        });
    }
}