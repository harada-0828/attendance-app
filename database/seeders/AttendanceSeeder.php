<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $generalUsers = User::where('admin_status', false)->get();

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $lastMonthDate = Carbon::now()->subMonth()->startOfMonth()->addDays(9);

        foreach ($generalUsers as $index => $user) {
            // 前月の勤怠データ（一覧の前月切替確認用）
            Attendance::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => $lastMonthDate->toDateString(),
                ],
                [
                    'clock_in' => $lastMonthDate->copy()->setTime(9, 0, 0),
                    'clock_out' => $lastMonthDate->copy()->setTime(18, 0, 0),
                ]
            );

            // 昨日の勤怠データ（退勤済み）
            Attendance::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => $yesterday->toDateString(),
                ],
                [
                    'clock_in' => $yesterday->copy()->setTime(9, 0, 0),
                    'clock_out' => $yesterday->copy()->setTime(18, 0, 0),
                ]
            );

            // 当日の勤怠データ（1人目は出勤中、2人目は退勤済みの例）
            Attendance::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => $today->toDateString(),
                ],
                [
                    'clock_in' => $today->copy()->setTime(9, 0, 0),
                    'clock_out' => $index === 1 ? $today->copy()->setTime(18, 0, 0) : null,
                ]
            );
        }
    }
}