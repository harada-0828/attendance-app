<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Rest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    /**
     * 出勤登録画面（打刻画面）の表示
     */
    public function create()
    {
        $user = Auth::user();
        $today = Carbon::today();

        // 本日の打刻データを取得
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today->toDateString())
            ->first();

        // ステータスを判定（打刻データがない場合は「勤務外」扱いにするためカスタム属性を安全に取得）
        $status = $attendance ? $attendance->status : '勤務外';

        $formatter = new \IntlDateFormatter('ja_JP', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Asia/Tokyo', \IntlDateFormatter::GREGORIAN, 'yyyy年M月d日(E)');
$formattedDate = $formatter->format(now());
        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact('user', 'attendance', 'status', 'formattedDate', 'formattedTime'));
    }

    /**
     * 打刻処理（出勤・休憩入・休憩戻・退勤）
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today();
        $now = Carbon::now();

        $action = $request->input('action'); // どのボタンが押されたかを受け取る

        // 本日の打刻データを取得、またはインスタンスを準備
        $attendance = Attendance::firstOrNew([
            'user_id' => $user->id,
            'date' => $today->toDateString(),
        ]);

        switch ($action) {
            case 'clock_in':
                // すでに退勤済または出勤済みの場合は弾く
                if (!$attendance->exists) {
                    $attendance->clock_in = $now;
                    $attendance->save();
                }
                break;

            case 'rest_in':
                // 出勤中で、現在休憩中でない場合のみ休憩開始を記録
                if ($attendance->exists && $attendance->status === '出勤中') {
                    Rest::create([
                        'attendance_id' => $attendance->id,
                        'break_start' => $now,
                    ]);
                }
                break;

            case 'rest_out':
                // 現在「休憩中」の場合、最新の休憩データの休憩終了時刻を記録
                if ($attendance->exists && $attendance->status === '休憩中') {
                    $latestRest = $attendance->rests()->latest('id')->first();
                    if ($latestRest && is_null($latestRest->break_end)) {
                        $latestRest->break_end = $now;
                        $latestRest->save();
                    }
                }
                break;

            case 'clock_out':
                // 現在「出勤中」の場合のみ退勤を記録
                if ($attendance->exists && $attendance->status === '出勤中') {
                    $attendance->clock_out = $now;
                    $attendance->save();
                }
                break;
        }

        return redirect()->route('attendance.create'); 
    }
}