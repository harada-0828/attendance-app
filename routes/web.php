<?php
 use Illuminate\Support\Facades\Route;

// --- ゲスト（未ログイン）でもアクセスできるルート ---
// 管理者用のログイン画面（FortifyServiceProviderの分岐をキャッチするため）
Route::get('/admin/login', function () {
    // 実際のファイル名（admin-login.blade.php）に合わせてドット記法で指定
    return view('admin.admin-login');
})->middleware('guest');


// --- 認証済みユーザーのみアクセスできるルート ---
Route::middleware('auth')->group(function () {
    // 1. 一般ユーザー用のログイン後リダイレクト先（仮設定）
    Route::get('/attendance', function () {
        $user = auth()->user();
        $formattedDate = now()->format('Y年m月d日');
        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    });

    // 2. 管理者用のログイン後リダイレクト先（仮設定）
    Route::get('/admin/attendance/list', function () {
        $user = auth()->user();

        return view('admin.admin-attendance-list', compact('user'));
    });
});