<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;

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
    
    // --- 一般ユーザー用の打刻画面 ---
    Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance.create');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

    // --- 管理者用の勤怠一覧 ---
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index']);


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