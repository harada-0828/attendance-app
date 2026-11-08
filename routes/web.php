<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\AttendanceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. ゲスト用ルート（未ログイン時のみアクセス可）
// ==========================================
Route::get('/admin/login', function () {
    return view('admin.admin-login');
})->middleware('guest')->name('admin.login');


// ==========================================
// 2. 認証済みユーザー用ルート
// ==========================================
Route::middleware('auth')->group(function () {
    
    // --- 画面表示・一覧 ---
    // 一般ユーザー用のホーム（打刻画面）
    Route::get('/attendance', function () {
        $user = auth()->user();
        $formattedDate = now()->format('Y年m月d日');
        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    });

    // 管理者用の勤怠一覧
    Route::get('/admin/attendance/list', [AttendanceController::class, 'index']);


    // --- ログアウト処理 ---
    // 一般ユーザー用ログアウト
    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');

    // 管理者用ログアウト
    Route::post('/admin/logout', function (Request $request) {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    })->name('admin.logout');
});