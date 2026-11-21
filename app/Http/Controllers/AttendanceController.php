<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Rest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    /**
     * 指定された年月の勤怠一覧画面を表示する。
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $dateStr = (string) $request->input('date', Carbon::now()->format('Y-m'));
        $date = Carbon::parse($dateStr);

        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        /** @var Collection<string, Attendance> $attendances */
        $attendances = Attendance::query()
            ->with('rests')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn (Attendance $item): string => Carbon::parse($item->date)->format('Y-m-d'));

        /** @var Collection<int, array<string, mixed>> $formattedAttendanceRecords */
        $formattedAttendanceRecords = collect(CarbonPeriod::create($startDate, '1 day', $endDate))
            ->map(function (Carbon $periodDate) use ($attendances): array {
                $dateKey = $periodDate->format('Y-m-d');
                $attendance = $attendances->get($dateKey);

                $clockIn = $attendance?->clock_in ? Carbon::parse($attendance->clock_in) : null;
                $clockOut = $attendance?->clock_out ? Carbon::parse($attendance->clock_out) : null;

                /** @var int $totalBreakMinutes */
                $totalBreakMinutes = $attendance
                    ? $attendance->rests
                        ->filter(fn (Rest $rest): bool => $rest->break_start !== null && $rest->break_end !== null)
                        ->sum(fn (Rest $rest): int => Carbon::parse($rest->break_start)
                            ->diffInMinutes(Carbon::parse($rest->break_end)))
                    : 0;

                $totalBreakFormatted = $totalBreakMinutes > 0
                    ? sprintf('%d:%02d', intdiv($totalBreakMinutes, 60), $totalBreakMinutes % 60)
                    : '';

                $totalTimeFormatted = ($clockIn && $clockOut)
                    ? sprintf(
                        '%d:%02d',
                        intdiv(max(0, $clockIn->diffInMinutes($clockOut) - $totalBreakMinutes), 60),
                        max(0, $clockIn->diffInMinutes($clockOut) - $totalBreakMinutes) % 60
                    )
                    : '';

                return [
                    'id' => $attendance?->id,
                    'date' => $periodDate->isoFormat('MM/DD(ddd)'),
                    'clock_in' => $clockIn?->format('H:i') ?? '',
                    'clock_out' => $clockOut?->format('H:i') ?? '',
                    'total_break_time' => $totalBreakFormatted,
                    'total_time' => $totalTimeFormatted,
                ];
            })
            ->values();

        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    /**
     * 出勤登録画面（打刻画面）を表示する。
     *
     * @return View
     */
    public function create(): View
    {
        $user = Auth::user();
        $today = Carbon::today();

        /** @var Attendance|null $attendance */
        $attendance = Attendance::query()
            ->with('rests')
            ->where('user_id', $user->id)
            ->where('date', $today->toDateString())
            ->first();

        $status = $attendance?->status ?? '勤務外';

        $formatter = new \IntlDateFormatter(
            'ja_JP',
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            'Asia/Tokyo',
            \IntlDateFormatter::GREGORIAN,
            'yyyy年M月d日(E)'
        );
        $formattedDate = (string) $formatter->format(now());
        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact(
            'user',
            'attendance',
            'status',
            'formattedDate',
            'formattedTime'
        ));
    }

    /**
     * 打刻処理（出勤・休憩入・休憩戻・退勤）を実行して打刻画面へリダイレクトする。
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $today = Carbon::today();
        $now = Carbon::now();

        $action = (string) $request->input('action');

        /** @var Attendance $attendance */
        $attendance = Attendance::query()
            ->with('rests')
            ->firstOrNew([
                'user_id' => $user->id,
                'date' => $today->toDateString(),
            ]);

        match ($action) {
            'clock_in' => $this->handleClockIn($attendance, $now),
            'rest_in' => $this->handleRestIn($attendance, $now),
            'rest_out' => $this->handleRestOut($attendance, $now),
            'clock_out' => $this->handleClockOut($attendance, $now),
            default => null,
        };

        return redirect()->route('attendance.create');
    }

    /**
     * 出勤打刻を保存する。
     *
     * @param  Attendance  $attendance
     * @param  Carbon  $now
     * @return void
     */
    private function handleClockIn(Attendance $attendance, Carbon $now): void
    {
        if (!$attendance->exists) {
            $attendance->clock_in = $now;
            $attendance->save();
        }
    }

    /**
     * 休憩入打刻を保存する。
     *
     * @param  Attendance  $attendance
     * @param  Carbon  $now
     * @return void
     */
    private function handleRestIn(Attendance $attendance, Carbon $now): void
    {
        if ($attendance->exists && $attendance->status === '出勤中') {
            Rest::create([
                'attendance_id' => $attendance->id,
                'break_start' => $now,
            ]);
        }
    }

    /**
     * 休憩戻打刻を保存する。
     *
     * @param  Attendance  $attendance
     * @param  Carbon  $now
     * @return void
     */
    private function handleRestOut(Attendance $attendance, Carbon $now): void
    {
        if ($attendance->exists && $attendance->status === '休憩中') {
            /** @var Rest|null $latestRest */
            $latestRest = $attendance->rests
                ->sortByDesc('id')
                ->first();

            if ($latestRest && is_null($latestRest->break_end)) {
                $latestRest->break_end = $now;
                $latestRest->save();
            }
        }
    }

    /**
     * 退勤打刻を保存する。
     *
     * @param  Attendance  $attendance
     * @param  Carbon  $now
     * @return void
     */
    private function handleClockOut(Attendance $attendance, Carbon $now): void
    {
        if ($attendance->exists && $attendance->status === '出勤中') {
            $attendance->clock_out = $now;
            $attendance->save();
        }
    }
}