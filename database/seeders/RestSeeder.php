<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Rest;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RestSeeder extends Seeder
{
    /**
     * 全勤怠レコードに対する休憩データの初期シードを実行する。
     *
     * @return void
     */
    public function run(): void
    {
        collect(Attendance::cursor())->each(function (Attendance $attendance): void {
            $dateStr = Carbon::parse($attendance->date)->toDateString();

            Rest::updateOrCreate(
                [
                    'attendance_id' => $attendance->id,
                    'break_start' => Carbon::parse("{$dateStr} 12:00:00"),
                ],
                [
                    'break_end' => Carbon::parse("{$dateStr} 13:00:00"),
                ]
            );
        });
    }
}