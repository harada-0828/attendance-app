<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\Request as AttendanceRequest; 
use App\Models\RequestRest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequestController extends Controller
{
    /**
     * ユーザーの修正履歴一覧の表示 (GET)
     */
    public function index()
    {
        $userId = Auth::id();
        $user = Auth::user();

        // 1. ログインユーザーのすべての申請データを取得
        $requests = AttendanceRequest::with(['attendance', 'user'])
            ->where('user_id', $userId)
            ->get();

        // 2. Blade側が求めている $formattedApplications の形式にデータを整形・変換する
        $formattedApplications = $requests->map(function ($req) {
            $dateObj = Carbon::parse($req->attendance->date ?? now());
            
            return [
                'id' => $req->id,
                'attendance_id' => $req->attendance_id,
                // Blade側で 'approval_status' が '承認待ち' かどうかで振り分けているため文字列に変換
                'approval_status' => $req->approval_status ? '承認待ち' : '承認済み', 
                'date' => $dateObj->format('Y/m/d'), 
                'comment' => $req->reason, 
                'application_date'=> $req->created_at->format('Y/m/d H:i'), 
            ];
        });

        // 3. 申請一覧ビューへデータを渡す
        return view('user.user-application-list', compact('formattedApplications', 'user'));
    }

    /**
     * 勤怠詳細画面の表示 (GET)
     */
    public function show($id)
    {
        $userId = Auth::id();
        $user = Auth::user();

        // 1. 対象の勤怠データを取得
        $attendance = Attendance::with(['user', 'rests', 'requests'])
            ->where('user_id', $userId)
            ->findOrFail($id);

        $dateObj = Carbon::parse($attendance->date);
        
        $data = [
            'id' => $attendance->id,
            'year' => $dateObj->format('Y年'), 
            'date' => $dateObj->format('n月j日'), 
            'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
            'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
            
            'breaks' => $attendance->rests->map(function ($rest) {
                return [
                    'break_in' => $rest->break_start ? Carbon::parse($rest->break_start)->format('H:i') : '',
                    'break_out' => $rest->break_end ? Carbon::parse($rest->break_end)->format('H:i') : '',
                ];
            })->toArray(),

            'comment' => $attendance->memo ?? '', 
            
            // 「承認待ち」の申請が存在するか判定してBladeに渡す。
            'application' => $attendance->requests->where('approval_status', true)->first(),
        ];

        return view('user.user-detail', compact('attendance', 'data', 'user'));
    }

    /**
     * 勤怠詳細の修正申請 (POST)
     */
    public function store(UpdateAttendanceRequest $request, $id)
    {
        // 1. バリデーション済みデータの取得
        $validated = $request->validated();
        $userId = Auth::id();

        // 2. 該当勤怠データの存在確認
        $attendance = Attendance::where('user_id', $userId)->findOrFail($id);

        // 3. データベースへの登録処理（トランザクション）
        DB::transaction(function () use ($attendance, $validated, $userId) {
            
            $attendanceRequest = AttendanceRequest::create([
                'attendance_id'       => $attendance->id,
                'user_id'             => $userId,
                'requested_clock_in'  => $validated['new_clock_in'],
                'requested_clock_out' => $validated['new_clock_out'],
                'reason'              => $validated['comment'],
                'approval_status'     => true, // 申請済み(true)
            ]);

            // 休憩申請データの保存（request_restsテーブル）
            if (!empty($validated['new_break_in'])) {
                foreach ($validated['new_break_in'] as $index => $breakIn) {
                    // 入力がある場合のみ保存
                    if (!empty($breakIn)) {
                        RequestRest::create([
                            'request_id'  => $attendanceRequest->id,
                            'break_start' => $breakIn,
                            'break_end'   => $validated['new_break_out'][$index] ?? null,
                        ]);
                    }
                }
            }
        });

        // 4. 登録後のリダイレクト（定義した route('attendance.detail', $id) により /attendance/detail/{id} へ飛ぶ）
        return redirect()->route('attendance.detail', $id)->with('success', '修正申請を送信しました。');
    }
}