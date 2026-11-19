<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * 管理者用の登録済み一般スタッフ一覧画面を表示する（管理者は除外）。
     *
     * @return View
     */
    public function index(): View
    {
        $user = Auth::user();

        /** @var Collection<int, User> $users */
        $users = User::query()
            ->where(function ($query): void {
                $query->where('admin_status', false)
                    ->orWhereNull('admin_status');
            })
            ->get();

        return view('admin.staff-list', compact('users', 'user'));
    }
}