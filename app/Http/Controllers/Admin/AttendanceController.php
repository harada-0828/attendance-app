<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\AdminUpdateAttendanceRequest;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Rest;

class AttendanceController extends Controller
{
    /**
     * 管理者用日次勤怠一覧表示
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // リクエストから日付を取得（指定がない場合は今日）
        $dateQuery = $request->query('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateQuery);

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        // ▼ イーガーローディングを適用：ユーザーと一緒にその日の勤怠と休憩データも一括取得
        $users = User::with(['attendances' => function ($query) use ($date) {
            $query->whereDate('date', $date->format('Y-m-d'))->with('rests');
        }])->get();

        // 互換性のために attendanceRecords も保持
        $attendanceRecords = Attendance::whereDate('date', $date->format('Y-m-d'))
            ->with(['user', 'rests'])
            ->get();

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

    /**
     * 管理者用勤怠詳細画面の表示
     */
    public function show($id)
    {
        // 勤怠データとそれに紐づくユーザー、休憩データを取得（イーガーローディング適用済み）
        $attendance = Attendance::with(['user', 'rests'])->findOrFail($id);
        $user = $attendance->user;

        // requestsテーブルから直近の申請理由（reason）を取得してコメント代わりにする
        $latestRequest = \DB::table('requests')
            ->where('attendance_id', $attendance->id)
            ->latest('id')
            ->first();

        $comment = $latestRequest ? $latestRequest->reason : '';

        // ビュー（admin-detail.blade.php）が期待するデータ構造に整形
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
        ];

        return view('admin.admin-detail', compact('user', 'attendanceRecord'));
    }

    /**
     * 管理者による直接の出退勤・休憩時間修正処理
     */
    public function store(AdminUpdateAttendanceRequest $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
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

            if (!empty($breakIn) && $breakIn !== 'null' && !empty($breakOut) && $breakOut !== 'null') {
                Rest::create([
                    'attendance_id' => $attendance->id,
                    'break_start' => $attendanceDate . ' ' . $breakIn . ':00',
                    'break_end' => $attendanceDate . ' ' . $breakOut . ':00',
                ]);
            }
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