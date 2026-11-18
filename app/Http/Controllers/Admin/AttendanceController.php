<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\AdminUpdateAttendanceRequest;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Rest;
use App\Models\Request as AttendanceRequest;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    /**
     * 管理者用日次勤怠一覧表示（管理者は除外）
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $dateQuery = $request->query('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateQuery);

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        // ▼ 管理者（admin_status = 1）を一覧の対象から除外
        $users = User::where('admin_status', '!=', 1)->with(['attendances' => function ($query) use ($date) {
            $query->whereDate('date', $date->format('Y-m-d'))->with('rests');
        }])->get();

        // ▼ 管理者ユーザーに紐づく勤怠レコードも除外
        $attendanceRecords = Attendance::whereHas('user', function($q) {
                $q->where('admin_status', '!=', 1);
            })
            ->whereDate('date', $date->format('Y-m-d'))
            ->with(['user', 'rests'])
            ->get();

        return view('admin.admin-attendance-list', compact(
            'user', 
            'date', 
            'previousDay', 
            'nextDay', 
            'users', 
            'attendanceRecords'
        ));
    }

    /**
     * 管理者用のスタッフ別月次勤怠一覧画面の表示 (URL: /admin/attendance/staff/{id})
     */
    public function showStaffMonthly(Request $request, $id)
    {
        $user = Auth::user();
        $targetUser = User::findOrFail($id);

        $dateQuery = $request->query('date', Carbon::today()->format('Y-m-01'));
        $date = Carbon::parse($dateQuery);

        $previousMonth = $date->copy()->subMonth()->format('Y-m-d');
        $nextMonth = $date->copy()->addMonth()->format('Y-m-d');

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $attendances = Attendance::with('rests')
            ->where('user_id', $id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->orderBy('date', 'asc')
            ->get();

        // データの整形と労働時間・休憩時間の計算ロジック
        $formattedAttendanceRecords = $attendances->map(function ($attendance) {
            $totalBreakMinutes = 0;
            foreach ($attendance->rests as $rest) {
                if ($rest->break_start && $rest->break_end) {
                    $totalBreakMinutes += Carbon::parse($rest->break_start)->diffInMinutes(Carbon::parse($rest->break_end));
                }
            }

            // 総労働時間（勤務時間 - 休憩時間）の計算
            $totalWorkMinutes = 0;
            if ($attendance->clock_in && $attendance->clock_out) {
                $workMinutes = Carbon::parse($attendance->clock_in)->diffInMinutes(Carbon::parse($attendance->clock_out));
                $totalWorkMinutes = max(0, $workMinutes - $totalBreakMinutes);
            }

            $totalBreakTime = $totalBreakMinutes > 0 
                ? sprintf('%02d:%02d', floor($totalBreakMinutes / 60), $totalBreakMinutes % 60) 
                : null;

            $totalTime = $totalWorkMinutes > 0 
                ? sprintf('%02d:%02d', floor($totalWorkMinutes / 60), $totalWorkMinutes % 60) 
                : null;

            return [
                'id' => $attendance->id,
                'date' => Carbon::parse($attendance->date)->format('n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][Carbon::parse($attendance->date)->dayOfWeek] . '）'),
                'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
                'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
                'total_break_time' => $totalBreakTime,
                'total_time' => $totalTime, // ★ ここに total_time を追加
            ];
        });

        return view('admin.staff-attendance-list', compact(
            'user', 
            'targetUser', 
            'date', 
            'previousMonth', 
            'nextMonth', 
            'formattedAttendanceRecords'
        ));
    }
    /**
     * 管理者用勤怠詳細画面の表示
     */
    public function show($id)
    {
        $attendance = Attendance::with(['user', 'rests'])->findOrFail($id);
        $user = $attendance->user;

        $pendingRequest = AttendanceRequest::where('attendance_id', $attendance->id)
            ->where('approval_status', true)
            ->first();

        $comment = '';

        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => Carbon::parse($attendance->date)->format('Y年'),
            'date' => Carbon::parse($attendance->date)->format('n月j日'),
            'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
            'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
            'breaks' => $attendance->rests->map(function ($rest) {
                return [
                    'break_in' => $rest->break_start ? Carbon::parse($rest->break_start)->format('H:i') : '',
                    'break_out' => $rest->break_end ? Carbon::parse($rest->break_end)->format('H:i') : '',
                ];
            })->toArray(),
            'comment' => $comment,
            'application' => $pendingRequest,
        ];

        return view('admin.admin-detail', compact('user', 'attendanceRecord'));
    }

    /**
     * 管理者による直接の出退勤・休憩時間修正処理
     */
    public function store(AdminUpdateAttendanceRequest $request, $id)
    {
        $attendance = Attendance::findOrFail($id);

        $hasPending = AttendanceRequest::where('attendance_id', $attendance->id)
            ->where('approval_status', true)
            ->exists();

        if ($hasPending) {
            return redirect()->back()->withErrors(['error' => '承認待ちの修正申請が存在するため、現在は直接修正できません。']);
        }

        $attendanceDate = Carbon::parse($attendance->date)->format('Y-m-d');

        $clockInInput = $request->input('new_clock_in');
        $clockOutInput = $request->input('new_clock_out');

        $clockIn = (!empty($clockInInput) && $clockInInput !== 'null') 
            ? $attendanceDate . ' ' . $clockInInput . ':00' 
            : null;
            
        $clockOut = (!empty($clockOutInput) && $clockOutInput !== 'null') 
            ? $attendanceDate . ' ' . $clockOutInput . ':00' 
            : null;

        $attendance->clock_in = $clockIn;
        $attendance->clock_out = $clockOut;
        $attendance->save();

        Rest::where('attendance_id', $attendance->id)->delete();

        $breakIns = $request->input('new_break_in', []);
        $breakOuts = $request->input('new_break_out', []);

        foreach ($breakIns as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            // ▼ 【追加のガード】空欄、null、または「開始と終了が全く同じ時間」の場合はデータベースに登録（加算）しない
            if (empty($breakIn) || $breakIn === 'null' || empty($breakOut) || $breakOut === 'null' || $breakIn === $breakOut) {
                continue;
            }

            Rest::create([
                'attendance_id' => $attendance->id,
                'break_start' => $attendanceDate . ' ' . $breakIn . ':00',
                'break_end' => $attendanceDate . ' ' . $breakOut . ':00',
            ]);
        }

        $comment = $request->input('comment');
        if (!empty($comment)) {
            \DB::table('requests')->insert([
                'attendance_id'       => $attendance->id,
                'user_id'             => $attendance->user_id,
                'requested_clock_in'  => $clockIn ?? ($attendanceDate . ' 00:00:00'),
                'requested_clock_out' => $clockOut ?? ($attendanceDate . ' 00:00:00'),
                'reason'              => $comment,
                'approval_status'     => false, 
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        return redirect()->route('admin.attendance.list', ['date' => $attendanceDate])
            ->with('success', '勤怠情報を修正しました。');
    }
}