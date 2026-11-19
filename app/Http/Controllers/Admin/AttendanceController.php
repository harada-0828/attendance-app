<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\Request as AttendanceRequest;
use App\Models\Rest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * 勤怠レコードから合計休憩時間と実働合計時間を算出する。
     *
     * @param  Attendance|null  $attendance
     * @return array{total_break_time: string, total_time: string}
     */
    private function calculateAttendanceDurations(?Attendance $attendance): array
    {
        if (!$attendance) {
            return [
                'total_break_time' => '',
                'total_time' => '',
            ];
        }

        /** @var int $totalBreakMinutes */
        $totalBreakMinutes = $attendance->rests
            ->filter(fn (Rest $rest): bool => $rest->break_start !== null && $rest->break_end !== null)
            ->sum(fn (Rest $rest): int => Carbon::parse($rest->break_start)
                ->diffInMinutes(Carbon::parse($rest->break_end)));

        $totalBreakTime = $totalBreakMinutes > 0
            ? sprintf('%d:%02d', intdiv($totalBreakMinutes, 60), $totalBreakMinutes % 60)
            : '';

        $totalTime = '';
        if ($attendance->clock_in && $attendance->clock_out) {
            $workMinutes = Carbon::parse($attendance->clock_in)
                ->diffInMinutes(Carbon::parse($attendance->clock_out));
            $netWorkMinutes = max(0, $workMinutes - $totalBreakMinutes);

            $totalTime = sprintf(
                '%d:%02d',
                intdiv($netWorkMinutes, 60),
                $netWorkMinutes % 60
            );
        }

        return [
            'total_break_time' => $totalBreakTime,
            'total_time' => $totalTime,
        ];
    }

    /**
     * 管理者用の日次勤怠一覧画面を表示する（管理者は除外）。
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        $dateQuery = (string) $request->query('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateQuery);

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        /** @var Collection<int, User> $users */
        $users = User::query()
            ->where(function ($query): void {
                $query->where('admin_status', false)
                    ->orWhereNull('admin_status');
            })
            ->with([
                'attendances' => function ($query) use ($date): void {
                    $query->whereDate('date', $date->format('Y-m-d'))
                        ->with('rests');
                },
            ])
            ->get();

        /** @var Collection<int, array<string, mixed>> $dailyAttendanceRows */
        $dailyAttendanceRows = $users->map(function (User $staff): array {
            /** @var Attendance|null $attendance */
            $attendance = $staff->attendances->first();
            $durations = $this->calculateAttendanceDurations($attendance);

            $staff->setAttribute('today_attendance', $attendance);
            $staff->setAttribute('total_break_time', $durations['total_break_time']);
            $staff->setAttribute('total_time', $durations['total_time']);

            return [
                'user' => $staff,
                'attendance' => $attendance,
                'clock_in' => $attendance?->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
                'clock_out' => $attendance?->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
                'total_break_time' => $durations['total_break_time'],
                'total_time' => $durations['total_time'],
            ];
        });

        /** @var Collection<int, Attendance> $attendanceRecords */
        $attendanceRecords = $dailyAttendanceRows
            ->pluck('attendance')
            ->filter()
            ->values();

        return view('admin.admin-attendance-list', compact(
            'user',
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords',
            'dailyAttendanceRows'
        ));
    }

    /**
     * 管理者用のスタッフ別月次勤怠一覧画面を表示する。
     *
     * @param  Request  $request
     * @param  int|string  $id
     * @return View
     */
    public function showStaffMonthly(Request $request, int|string $id): View
    {
        $user = auth()->user();
        $targetUser = User::findOrFail($id);

        $dateQuery = (string) $request->query('date', Carbon::today()->format('Y-m-01'));
        $date = Carbon::parse($dateQuery);

        $previousMonth = $date->copy()->subMonth()->format('Y-m-d');
        $nextMonth = $date->copy()->addMonth()->format('Y-m-d');

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        /** @var Collection<int, Attendance> $attendances */
        $attendances = Attendance::query()
            ->with('rests')
            ->where('user_id', $id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->orderBy('date', 'asc')
            ->get();

        /** @var Collection<int, array<string, mixed>> $formattedAttendanceRecords */
        $formattedAttendanceRecords = $attendances->map(function (Attendance $attendance) use ($weekdays): array {
            $durations = $this->calculateAttendanceDurations($attendance);
            $parsedDate = Carbon::parse($attendance->date);

            return [
                'id' => $attendance->id,
                'date' => $parsedDate->format('n月j日') . '（' . $weekdays[$parsedDate->dayOfWeek] . '）',
                'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
                'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
                'total_break_time' => $durations['total_break_time'] !== '' ? $durations['total_break_time'] : null,
                'total_time' => $durations['total_time'] !== '' ? $durations['total_time'] : null,
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
     * 管理者用の勤怠詳細画面を表示する。
     *
     * @param  int|string  $id
     * @return View
     */
    public function show(int|string $id): View
    {
        $attendance = Attendance::with(['user', 'rests'])->findOrFail($id);
        $user = $attendance->user;

        $pendingRequest = AttendanceRequest::query()
            ->where('attendance_id', $attendance->id)
            ->where('approval_status', true)
            ->latest('id')
            ->first();

        $latestApprovedRequest = AttendanceRequest::query()
            ->where('attendance_id', $attendance->id)
            ->where('approval_status', false)
            ->latest('id')
            ->first();

        $comment = $latestApprovedRequest?->reason ?? '';

        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => Carbon::parse($attendance->date)->format('Y年'),
            'date' => Carbon::parse($attendance->date)->format('n月j日'),
            'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
            'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
            'breaks' => $attendance->rests->map(fn (Rest $rest): array => [
                'break_in' => $rest->break_start ? Carbon::parse($rest->break_start)->format('H:i') : '',
                'break_out' => $rest->break_end ? Carbon::parse($rest->break_end)->format('H:i') : '',
            ])->toArray(),
            'comment' => $comment,
            'application' => $pendingRequest,
        ];

        return view('admin.admin-detail', compact('user', 'attendanceRecord'));
    }

    /**
     * 管理者による直接の出退勤・休憩時間修正を保存し、日次勤怠一覧へリダイレクトする。
     *
     * @param  AdminUpdateAttendanceRequest  $request
     * @param  int|string  $id
     * @return RedirectResponse
     */
    public function store(AdminUpdateAttendanceRequest $request, int|string $id): RedirectResponse
    {
        $attendance = Attendance::findOrFail($id);

        $hasPending = AttendanceRequest::query()
            ->where('attendance_id', $attendance->id)
            ->where('approval_status', true)
            ->exists();

        if ($hasPending) {
            return redirect()->back()->withErrors([
                'error' => '承認待ちの修正申請が存在するため、現在は直接修正できません。',
            ]);
        }

        $attendanceDate = Carbon::parse($attendance->date)->format('Y-m-d');

        $clockInInput = $request->input('new_clock_in');
        $clockOutInput = $request->input('new_clock_out');

        $clockIn = (!empty($clockInInput) && $clockInInput !== 'null')
            ? "{$attendanceDate} {$clockInInput}:00"
            : null;

        $clockOut = (!empty($clockOutInput) && $clockOutInput !== 'null')
            ? "{$attendanceDate} {$clockOutInput}:00"
            : null;

        $breakIns = collect($request->input('new_break_in', []));
        $breakOuts = $request->input('new_break_out', []);
        $comment = $request->input('comment');

        DB::transaction(function () use ($attendance, $attendanceDate, $clockIn, $clockOut, $breakIns, $breakOuts, $comment): void {
            $attendance->update([
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
            ]);

            Rest::where('attendance_id', $attendance->id)->delete();

            $breakIns->each(function (mixed $breakIn, int|string $index) use ($breakOuts, $attendance, $attendanceDate): void {
                $breakOut = $breakOuts[$index] ?? null;

                if (
                    empty($breakIn) || $breakIn === 'null'
                    || empty($breakOut) || $breakOut === 'null'
                    || $breakIn === $breakOut
                ) {
                    return;
                }

                Rest::create([
                    'attendance_id' => $attendance->id,
                    'break_start' => "{$attendanceDate} {$breakIn}:00",
                    'break_end' => "{$attendanceDate} {$breakOut}:00",
                ]);
            });

            if (!empty($comment)) {
                AttendanceRequest::create([
                    'attendance_id' => $attendance->id,
                    'user_id' => $attendance->user_id,
                    'requested_clock_in' => $clockIn ?? "{$attendanceDate} 00:00:00",
                    'requested_clock_out' => $clockOut ?? "{$attendanceDate} 00:00:00",
                    'reason' => $comment,
                    'approval_status' => false,
                ]);
            }
        });

        return redirect()
            ->route('admin.attendance.list', ['date' => $attendanceDate])
            ->with('success', '勤怠情報を修正しました。');
    }
}