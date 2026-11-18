<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * 管理者用の登録済みスタッフ一覧を表示
     */
    public function index()
    {
        $user = Auth::user();
        
        // ▼ 管理者（admin_status = 1）を除外して一般スタッフのみ取得
        $users = User::where('admin_status', '!=', 1)->get();

        return view('admin.staff-list', compact('users', 'user'));
    }
}