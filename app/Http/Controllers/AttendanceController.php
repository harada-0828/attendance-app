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
     * 勤怠一覧画面の表示 
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // 1. クエリパラメータ 'date' から表示する月を取得
        $dateStr = $request->input('date', Carbon::now()->format('Y-m'));
        $date = Carbon::parse($dateStr);

        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        // 2. 前月と翌月のリンク用文字列を作成 (FN024)
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        // 3. ログインユーザーの指定月の勤怠データを取得し、日付をキーにする (FN023)
        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        // 4. Blade側のループ（$formattedAttendanceRecords）に合わせて1ヶ月分のデータを組み立て
        $formattedAttendanceRecords = [];
        $periodDate = $startDate->copy();

        while ($periodDate <= $endDate) {
            $dateKey = $periodDate->format('Y-m-d');
            $attendance = $attendances->get($dateKey);

            
            $formattedDate = $periodDate->isoFormat('MM/DD(ddd)');

            $formattedAttendanceRecords[] = [
                'id' => $attendance ? $attendance->id : null,
                'date' => $formattedDate, 
                'clock_in' => $attendance && $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
                'clock_out' => $attendance && $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
                'total_break_time' => $attendance ? $attendance->total_break_time : null,
                'total_time' => $attendance ? $attendance->total_time : null,
            ];

            $periodDate->addDay();
        }

        // 5. Bladeへ変数を渡す
        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

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