<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Rest;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RestSeeder extends Seeder
{
    public function run(): void
    {
        $attendances = Attendance::all();

        foreach ($attendances as $attendance) {
            $baseDate = Carbon::parse($attendance->date);

            // 退勤済みまたは前月・昨日の勤怠には終了済みの休憩データを入れる
            if ($attendance->clock_out !== null || $baseDate->lt(Carbon::today())) {
                Rest::firstOrCreate(
                    [
                        'attendance_id' => $attendance->id,
                        'break_start' => $baseDate->copy()->setTime(12, 0, 0),
                    ],
                    [
                        'break_end' => $baseDate->copy()->setTime(13, 0, 0),
                    ]
                );
            }
        }
    }
}