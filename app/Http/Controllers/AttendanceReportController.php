<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    private const STANDARD_WORK_MINUTES = 480;

    private const LONG_WORK_THRESHOLD_MINUTES = 600;

    private const LATE_LIMIT_TIME = '09:00:00';

    private const EARLY_LEAVE_LIMIT_TIME = '18:00:00';

    private const MONTHS_COUNT = 6;

    /**
     * ログインユーザーの過去6ヶ月の勤怠統計レポートを表示する。
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $now = Carbon::now();

        $startDate = $now->copy()->subMonths(self::MONTHS_COUNT - 1)->startOfMonth();
        $endDate = $now->copy()->endOfMonth();

        /** @var Collection<int, Attendance> $attendances */
        $attendances = Attendance::query()
            ->with('rests')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->get();

        /** @var Collection<int, array{attendance: Attendance, month: string, work_minutes: int, overtime_minutes: int}> $completedRecords */
        $completedRecords = $attendances
            ->filter(fn (Attendance $attendance): bool => $attendance->clock_in !== null && $attendance->clock_out !== null)
            ->map(function (Attendance $attendance): array {
                $workMinutes = $attendance->work_minutes;
                $overtimeMinutes = max(0, $workMinutes - self::STANDARD_WORK_MINUTES);

                return [
                    'attendance' => $attendance,
                    'month' => Carbon::parse($attendance->date)->format('Y-m'),
                    'work_minutes' => $workMinutes,
                    'overtime_minutes' => $overtimeMinutes,
                ];
            })
            ->values();

        /** @var int $totalWorkMinutes */
        $totalWorkMinutes = (int) $completedRecords->sum('work_minutes');

        /** @var int $totalOvertimeMinutes */
        $totalOvertimeMinutes = (int) $completedRecords->sum('overtime_minutes');

        $workedDaysCount = $completedRecords->count();
        $avgWorkMinutes = $workedDaysCount > 0
            ? intdiv($totalWorkMinutes, $workedDaysCount)
            : 0;

        $monthlyTrend = $this->buildMonthlyTrend($now, $completedRecords);
        $anomalies = $this->buildCurrentMonthAnomalies($now, $attendances);

        return view('reports.index', [
            'summary' => [
                'total_work_minutes' => $totalWorkMinutes,
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'avg_work_minutes' => $avgWorkMinutes,
            ],
            'monthlyTrend' => $monthlyTrend,
            'anomalies' => $anomalies,
        ]);
    }

    /**
     * 過去6ヶ月分の月次推移データ（労働時間・残業時間）を生成する。
     *
     * @param  Collection<int, array{attendance: Attendance, month: string, work_minutes: int, overtime_minutes: int}>  $completedRecords
     * @return list<array{month: string, work_minutes: int, overtime_minutes: int}>
     */
    private function buildMonthlyTrend(Carbon $now, Collection $completedRecords): array
    {
        $groupedByMonth = $completedRecords->groupBy('month');

        /** @var list<array{month: string, work_minutes: int, overtime_minutes: int}> $trend */
        $trend = collect(range(self::MONTHS_COUNT - 1, 0))
            ->map(function (int $offset) use ($now, $groupedByMonth): array {
                $monthKey = $now->copy()->subMonths($offset)->format('Y-m');
                /** @var Collection<int, array{attendance: Attendance, month: string, work_minutes: int, overtime_minutes: int}> $monthItems */
                $monthItems = $groupedByMonth->get($monthKey, collect());

                return [
                    'month' => $monthKey,
                    'work_minutes' => (int) $monthItems->sum('work_minutes'),
                    'overtime_minutes' => (int) $monthItems->sum('overtime_minutes'),
                ];
            })
            ->values()
            ->all();

        return $trend;
    }

    /**
     * 当月の異常検知（遅刻回数・早退回数・長時間労働日数）を集計する。
     *
     * @param  Collection<int, Attendance>  $attendances
     * @return array{late_count: int, early_leave_count: int, long_work_count: int}
     */
    private function buildCurrentMonthAnomalies(Carbon $now, Collection $attendances): array
    {
        $currentMonthKey = $now->format('Y-m');

        /** @var Collection<int, Attendance> $currentMonthAttendances */
        $currentMonthAttendances = $attendances
            ->filter(fn (Attendance $attendance): bool => Carbon::parse($attendance->date)->format('Y-m') === $currentMonthKey)
            ->values();

        $lateCount = $currentMonthAttendances
            ->filter(fn (Attendance $attendance): bool => $attendance->clock_in !== null
                && Carbon::parse($attendance->clock_in)->format('H:i:s') > self::LATE_LIMIT_TIME)
            ->count();

        $earlyLeaveCount = $currentMonthAttendances
            ->filter(fn (Attendance $attendance): bool => $attendance->clock_out !== null
                && Carbon::parse($attendance->clock_out)->format('H:i:s') < self::EARLY_LEAVE_LIMIT_TIME)
            ->count();

        $longWorkCount = $currentMonthAttendances
            ->filter(fn (Attendance $attendance): bool => $attendance->work_minutes > self::LONG_WORK_THRESHOLD_MINUTES)
            ->count();

        return [
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count' => $longWorkCount,
        ];
    }
}
