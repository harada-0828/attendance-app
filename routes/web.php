<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\Admin\RequestController as AdminRequestController;

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
// 2. 認証済みユーザー用ルート（一般ユーザー向け）
// ==========================================
Route::middleware('auth')->group(function () {
    // --- 一般ユーザー用の打刻画面 ---
    Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance.create');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

    // 1. 勤怠一覧画面 (FN023, FN024)
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.list');
    
    // 2. 勤怠詳細画面の表示 (GET)
    Route::get('/attendance/detail/{id}', [RequestController::class, 'show'])->name('attendance.detail');
    
    // 3. 勤怠詳細の修正申請 (POST)
    Route::post('/attendance/detail/{id}', [RequestController::class, 'store'])->name('attendance.update');

    // 4. 申請一覧画面（※一般ユーザーと管理者でアクセス先コントローラーを自動切り分け）
    Route::get('/stamp_correction_request/list', function (Request $request) {
        $user = Auth::user();
        if ($user && $user->admin_status) {
            return app(AdminRequestController::class)->index();
        }
        return app(RequestController::class)->index();
    })->name('stamp_correction_request.list');

    // --- 一般ユーザー用ログアウト ---
    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');
});


// ==========================================
// 3. 管理者用ルート
// ==========================================
Route::middleware('auth')->group(function () {
    // 管理者用の勤怠一覧
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])->name('admin.attendance.list');

    // 管理者用の勤怠詳細画面表示・直接修正処理
    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'show'])->name('admin.attendance.detail');
    Route::post('/admin/attendance/{id}', [AdminAttendanceController::class, 'store'])->name('admin.attendance.update');

    // 管理者用ログアウト
    Route::post('/admin/logout', function (Request $request) {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    })->name('admin.logout');
});