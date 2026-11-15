<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request as AttendanceRequest;
use Illuminate\Support\Facades\Auth;

class RequestController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // ▼ イーガーローディング（with）を正しく適用しつつ、リレーション欠損によるエラーを防ぐ安全な実装
        $requests = AttendanceRequest::with(['attendance.user', 'user'])->get();

        $applications = $requests->map(function ($req) {
            $item = new \stdClass();
            $item->id = $req->id;
            $item->approval_status = $req->approval_status ? '承認待ち' : '承認済み';
            
            // リレーションから安全に取得（イーガーローディング済みのデータを活用）
            $item->user = $req->user ?? \App\Models\User::find($req->user_id);
            $item->AttendanceRecord = $req->attendance;
            
            $item->comment = $req->reason;
            $item->application_date = $req->created_at;

            return $item;
        });

        return view('admin.admin-application-list', compact('applications', 'user'));
    }
}