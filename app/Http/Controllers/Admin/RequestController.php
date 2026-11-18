<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Request as AttendanceRequest;
use App\Models\Attendance;
use App\Models\Rest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RequestController extends Controller
{
    /**
     * 修正申請一覧表示 (GET)
     */
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

    /**
     * 修正申請承認画面の表示 (GET)
     * URL: /stamp_correction_request/approve/{attendance_correct_request_id}
     */
    public function show($attendance_correct_request_id)
    {
        $user = Auth::user();

        // 申請データと、紐づく申請側の休憩データ（requestRests）を一緒に取得
        $requestData = AttendanceRequest::with(['user', 'attendance.user', 'requestRests'])->findOrFail($attendance_correct_request_id);

        $application = new \stdClass();
        $application->id = $requestData->id;
        
        // approval_status が 1 (true) なら '承認待ち'、0 (false) なら '承認済み'
        $application->approval_status = $requestData->approval_status ? '承認待ち' : '承認済み';
        
        $application->user = $requestData->user ?? \App\Models\User::find($requestData->user_id);
        $application->AttendanceRecord = $requestData->attendance;
        
        $targetDate = $requestData->attendance ? $requestData->attendance->date : $requestData->created_at;
        $application->new_date = Carbon::parse($targetDate);

        $application->new_clock_in = $requestData->requested_clock_in 
            ? Carbon::parse($requestData->requested_clock_in)->format('H:i') 
            : ($requestData->attendance && $requestData->attendance->clock_in ? Carbon::parse($requestData->attendance->clock_in)->format('H:i') : '');

        $application->new_clock_out = $requestData->requested_clock_out 
            ? Carbon::parse($requestData->requested_clock_out)->format('H:i') 
            : ($requestData->attendance && $requestData->attendance->clock_out ? Carbon::parse($requestData->attendance->clock_out)->format('H:i') : '');

        // データベースのカラム名 (break_start / break_end) を Blade側が求めている (break_in / break_out) に変換
        $application->proposalBreaks = $requestData->requestRests ? $requestData->requestRests->map(function ($r) {
            $breakItem = new \stdClass();
            $breakItem->break_in = $r->break_start;
            $breakItem->break_out = $r->break_end;
            return $breakItem;
        }) : collect([]);

        $application->comment = $requestData->reason;

        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    /**
     * 修正申請の承認処理 (POST)
     * URL: /stamp_correction_request/approve/{attendance_correct_request_id}
     */
    public function update(Request $request, $attendance_correct_request_id)
    {
        $attendanceRequest = AttendanceRequest::with(['attendance.rests', 'requestRests'])->findOrFail($attendance_correct_request_id);

        DB::transaction(function () use ($attendanceRequest) {
            $attendance = $attendanceRequest->attendance;
            if ($attendance) {
                // 1. 出退勤時間の反映
                if ($attendanceRequest->requested_clock_in) {
                    $attendance->clock_in = $attendanceRequest->requested_clock_in;
                }
                if ($attendanceRequest->requested_clock_out) {
                    $attendance->clock_out = $attendanceRequest->requested_clock_out;
                }
                $attendance->save();

                // 2. 申請された休憩時間（request_rests）を実際の rests テーブルに反映
                $attendance->rests()->delete();

                foreach ($attendanceRequest->requestRests as $reqRest) {
                    Rest::create([
                        'attendance_id' => $attendance->id,
                        'break_start'   => $reqRest->break_start,
                        'break_end'     => $reqRest->break_end,
                    ]);
                }
            }

            // 3. 申請データのステータスを「承認済み」に変更（false: 0）[cite: 6]
            $attendanceRequest->approval_status = false; 
            $attendanceRequest->save();
        });

        // 承認後は同じ詳細画面へ戻り、ステータスが「承認済み」に切り替わった状態にする
        return redirect()->back()
            ->with('success', '修正申請を承認し、勤怠および休憩データに反映しました。');
    }
}