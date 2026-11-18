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

        $requests = AttendanceRequest::with(['attendance', 'user'])
            ->where('user_id', $userId)
            ->get();

        $formattedApplications = $requests->map(function ($req) {
            $dateObj = Carbon::parse($req->attendance->date ?? now());
            
            return [
                'id' => $req->id,
                'attendance_id' => $req->attendance_id,
                'approval_status' => $req->approval_status ? '承認待ち' : '承認済み', 
                'date' => $dateObj->format('Y/m/d'), 
                'comment' => $req->reason, 
                'application_date'=> $req->created_at->format('Y/m/d H:i'), 
            ];
        });

        return view('user.user-application-list', compact('formattedApplications', 'user'));
    }

    /**
     * 勤怠詳細画面の表示 (GET)
     */
    public function show($id)
    {
        $userId = Auth::id();
        $user = Auth::user();

        $attendance = Attendance::with(['user', 'rests'])
            ->where('user_id', $userId)
            ->findOrFail($id);

        $dateObj = Carbon::parse($attendance->date);
        
        $pendingRequest = AttendanceRequest::with('requestRests')
            ->where('attendance_id', $attendance->id)
            ->where('approval_status', 1)
            ->first();

        $latestRequest = AttendanceRequest::where('attendance_id', $attendance->id)
            ->latest('id')
            ->first();

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

            'comment' => $latestRequest ? $latestRequest->reason : '', 
            'application' => $pendingRequest,
        ];

        if ($pendingRequest) {
            $data['clock_in'] = $pendingRequest->requested_clock_in ? Carbon::parse($pendingRequest->requested_clock_in)->format('H:i') : $data['clock_in'];
            $data['clock_out'] = $pendingRequest->requested_clock_out ? Carbon::parse($pendingRequest->requested_clock_out)->format('H:i') : $data['clock_out'];
            $data['comment'] = $pendingRequest->reason ?? $data['comment'];

            if ($pendingRequest->requestRests && $pendingRequest->requestRests->count() > 0) {
                $data['breaks'] = $pendingRequest->requestRests->map(function ($r) {
                    return [
                        'break_in'  => $r->break_start ? Carbon::parse($r->break_start)->format('H:i') : '',
                        'break_out' => $r->break_end ? Carbon::parse($r->break_end)->format('H:i') : '',
                    ];
                })->toArray();
            }
        }

        return view('user.user-detail', compact('attendance', 'data', 'user'));
    }

    /**
     * 勤怠詳細の修正申請 (POST)
     */
    public function store(UpdateAttendanceRequest $request, $id)
    {
        $validated = $request->validated();
        $userId = Auth::id();

        $attendance = Attendance::where('user_id', $userId)->findOrFail($id);

        DB::transaction(function () use ($attendance, $validated, $userId) {
            $attendanceRequest = AttendanceRequest::create([
                'attendance_id'       => $attendance->id,
                'user_id'             => $userId,
                'requested_clock_in'  => $validated['new_clock_in'],
                'requested_clock_out' => $validated['new_clock_out'],
                'reason'              => $validated['comment'],
                'approval_status'     => true,
            ]);

            if (!empty($validated['new_break_in'])) {
                $validBreaks = []; // 重複チェック用の配列

                foreach ($validated['new_break_in'] as $index => $breakIn) {
                    $breakOut = $validated['new_break_out'][$index] ?? null;

                    // 空欄、null、または開始と終了が同じ時間はスキップ
                    if (empty($breakIn) || $breakIn === 'null' || empty($breakOut) || $breakOut === 'null' || $breakIn === $breakOut) {
                        continue;
                    }

                    $newInterval = ['start' => $breakIn, 'end' => $breakOut];

                    // すでに登録リストにある休憩時間と重複していないかチェック（ダブルチェック）
                    $isDuplicate = false;
                    foreach ($validBreaks as $existing) {
                        if ($newInterval['start'] < $existing['end'] && $existing['start'] < $newInterval['end']) {
                            $isDuplicate = true;
                            break;
                        }
                    }

                    // 重複していなければ登録し、RequestRestを作成
                    if (!$isDuplicate) {
                        $validBreaks[] = $newInterval;

                        RequestRest::create([
                            'request_id'  => $attendanceRequest->id,
                            'break_start' => $breakIn,
                            'break_end'   => $breakOut,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('attendance.detail', $id)->with('success', '修正申請を送信しました。');
    }
}