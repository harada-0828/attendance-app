<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\RequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ゲスト用ルート
Route::get('/admin/login', function () {
    return view('admin.admin-login');
})->middleware('guest')->name('admin.login');

// 認証済みユーザー用ルート（メール認証必須の一般ユーザー機能）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance.create');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.list');
    Route::get('/attendance/detail/{id}', [RequestController::class, 'show'])->name('attendance.detail');
    Route::post('/attendance/detail/{id}', [RequestController::class, 'store'])->name('attendance.update');
    Route::get('/attendance/report', [AttendanceReportController::class, 'index'])->name('attendance.report');
});

// ログアウト・申請一覧（メール認証待ちユーザーでもログアウトできるように分離）
Route::middleware('auth')->group(function () {
    Route::get('/stamp_correction_request/list', function (Request $request) {
        $user = Auth::user();

        if ($user && $user->admin_status) {
            return app(AdminRequestController::class)->index();
        }

        if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return app(RequestController::class)->index();
    })->name('stamp_correction_request.list');

    Route::get('/stamp_correction_request/approve/{id}', [AdminRequestController::class, 'show'])
        ->name('admin.stamp_correction_request.approve');
    Route::post('/stamp_correction_request/approve/{id}', [AdminRequestController::class, 'update'])
        ->name('admin.stamp_correction_request.update');

    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');
});

// 管理者用ルート
Route::middleware('auth')->group(function () {
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])->name('admin.attendance.list');
    Route::get('/admin/staff/list', [AdminUserController::class, 'index'])->name('admin.staff.index');
    Route::get('/admin/attendance/staff/{id}', [AdminAttendanceController::class, 'showStaffMonthly'])->name('admin.attendance.staff');
    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'show'])->name('admin.attendance.detail');
    Route::post('/admin/attendance/{id}', [AdminAttendanceController::class, 'store'])->name('admin.attendance.update');

    Route::post('/admin/logout', function (Request $request) {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    })->name('admin.logout');
});