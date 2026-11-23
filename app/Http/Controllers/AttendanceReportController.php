<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    private const STANDARD_WORK_MINUTES = 480; // 8時間
    private const LONG_WORK_THRESHOLD_MINUTES = 600; // 10時間超
    private const LATE_LIMIT_TIME = '09:00:00';
    private const EARLY_LEAVE_LIMIT_TIME = '18:00:00';
    private const MONTHS_COUNT = 6;

    /**
     * ログインユーザーの過去6ヶ月の勤怠統計レポートを表示する。
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $now = Carbon::now();

        $startDate = $now->copy()->subMonths(self::MONTHS_COUNT - 1)->startOfMonth();
        $endDate = $now->copy()->endOfMonth();

        $attendances = Attendance::query()
            ->with('rests')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->get();

        $monthlyTrendMap = $this->initializeMonthlyTrend($now);

        $totalWorkMinutes = 0;
        $totalOvertimeMinutes = 0;
        $workedDaysCount = 0;

        $currentMonthKey = $now->format('Y-m');
        $lateCount = 0;
        $earlyLeaveCount = 0;
        $longWorkCount = 0;

        /** @var Attendance $attendance */
        foreach ($attendances as $attendance) {
            $attendanceDate = Carbon::parse($attendance->date);
            $monthKey = $attendanceDate->format('Y-m');
            $dayWorkMinutes = $attendance->work_minutes;

            if ($attendance->clock_in !== null && $attendance->clock_out !== null) {
                $dayOvertimeMinutes = max(0, $dayWorkMinutes - self::STANDARD_WORK_MINUTES);

                $totalWorkMinutes += $dayWorkMinutes;
                $totalOvertimeMinutes += $dayOvertimeMinutes;
                $workedDaysCount++;

                if (isset($monthlyTrendMap[$monthKey])) {
                    $monthlyTrendMap[$monthKey]['work_minutes'] += $dayWorkMinutes;
                    $monthlyTrendMap[$monthKey]['overtime_minutes'] += $dayOvertimeMinutes;
                }
            }

            if ($monthKey === $currentMonthKey) {
                if ($attendance->clock_in !== null && Carbon::parse($attendance->clock_in)->format('H:i:s') > self::LATE_LIMIT_TIME) {
                    $lateCount++;
                }

                if ($attendance->clock_out !== null && Carbon::parse($attendance->clock_out)->format('H:i:s') < self::EARLY_LEAVE_LIMIT_TIME) {
                    $earlyLeaveCount++;
                }

                if ($dayWorkMinutes > self::LONG_WORK_THRESHOLD_MINUTES) {
                    $longWorkCount++;
                }
            }
        }

        $avgWorkMinutes = $workedDaysCount > 0
            ? (int) floor($totalWorkMinutes / $workedDaysCount)
            : 0;

        return view('reports.index', [
            'summary' => [
                'total_work_minutes' => $totalWorkMinutes,
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'avg_work_minutes' => $avgWorkMinutes,
            ],
            'monthlyTrend' => array_values($monthlyTrendMap),
            'anomalies' => [
                'late_count' => $lateCount,
                'early_leave_count' => $earlyLeaveCount,
                'long_work_count' => $longWorkCount,
            ],
        ]);
    }

    /**
     * 過去6ヶ月分の集計枠を初期化する。
     *
     * @return array<string, array{month: string, work_minutes: int, overtime_minutes: int}>
     */
    private function initializeMonthlyTrend(Carbon $now): array
    {
        $months = [];

        for ($offset = self::MONTHS_COUNT - 1; $offset >= 0; $offset--) {
            $monthKey = $now->copy()->subMonths($offset)->format('Y-m');
            $months[$monthKey] = [
                'month' => $monthKey,
                'work_minutes' => 0,
                'overtime_minutes' => 0,
            ];
        }

        return $months;
    }
}