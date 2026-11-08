<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance; // 勤怠モデル（モデル名が異なる場合は適宜調整してください）

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // リクエストから日付を取得（指定がない場合は今日）
        $dateQuery = $request->query('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateQuery);

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        // ▼ 1. すべてのユーザーを取得
        $users = User::all();

        // ▼ 2. 選択された日付の勤怠データを取得（※モデル名やリレーションはプロジェクトの設計に合わせて調整してください）
        $attendanceRecords = Attendance::whereDate('date', $date->format('Y-m-d'))->get();

        // ビューに必要な変数をすべて渡す
        return view('admin.admin-attendance-list', compact(
            'user', 
            'date', 
            'previousDay', 
            'nextDay', 
            'users', 
            'attendanceRecords'
        ));
    }
}