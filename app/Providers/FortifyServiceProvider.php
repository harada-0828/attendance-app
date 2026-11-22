<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\LoginRequest;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Http\Responses\RegisterResponse as CustomRegisterResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 新規ユーザー作成アクションのバインド
        $this->app->singleton(CreatesNewUsers::class, CreateNewUser::class);

        // Fortifyのログアウト時の挙動を、作成したクラスにする
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ログイン成功時のレスポンスをカスタムクラスに差し替え
        $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);

        // Fortifyのログインリクエストを上書き
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);

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

        // 3. メール認証誘導画面のビュー指定
        Fortify::verifyEmailView(function () {
            return view('auth.verify-email');
        });

        // 4. 認証処理のカスタマイズ（admin_statusで一般・管理者を弾く）
        Fortify::authenticateUsing(function (Request $request) {
            // 管理者ログイン画面からの送信かどうか
            $isAdminLogin = $request->input('login_type') === 'admin';

            // メールアドレスからユーザーを検索
            $user = User::where('email', $request->email)->first();

            // ユーザーがいない、またはパスワードが一致しない場合
            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => [__('auth.failed')],
                ]);
            }

            // admin_status カラムを使って権限チェック
            if ($isAdminLogin) {
                // 管理者ログイン画面で、一般ユーザーでのログインは弾く
                if (!$user->admin_status) {
                    throw ValidationException::withMessages([
                        'email' => [__('auth.failed')],
                    ]);
                }
                $guard = 'admin';
            } else {
                // 一般ログイン画面で、管理者のログインは弾く
                if ($user->admin_status) {
                    throw ValidationException::withMessages([
                        'email' => [__('auth.failed')],
                    ]);
                }
                $guard = 'web';
            }

            // 正しい権限の場合のみログイン処理を実行
            Auth::guard($guard)->login($user, $request->boolean('remember'));

            return $user;
        });

        // 5. ログイン時のレートリミットの設定
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;

            return Limit::perMinute(5)->by($email . $request->ip());
        });
    }
}