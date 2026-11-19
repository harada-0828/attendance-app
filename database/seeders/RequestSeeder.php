<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Request as AttendanceRequest;
use App\Models\RequestRest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RequestSeeder extends Seeder
{
    /**
     * 修正申請および休憩修正データの初期シードを実行する。
     *
     * @return void
     */
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        if (!$user1) {
            return;
        }

        $targetAttendance = Attendance::where('user_id', $user1->id)
            ->orderByDesc('date')
            ->first();

        if (!$targetAttendance) {
            return;
        }

        $dateStr = Carbon::parse($targetAttendance->date)->toDateString();

        $request = AttendanceRequest::updateOrCreate(
            [
                'attendance_id' => $targetAttendance->id,
                'user_id' => $user1->id,
            ],
            [
                'requested_clock_in' => Carbon::parse("{$dateStr} 08:50:00"),
                'requested_clock_out' => Carbon::parse("{$dateStr} 18:00:00"),
                'reason' => '打刻時刻の修正申請（初期データ）',
                'approval_status' => true,
            ]
        );

        RequestRest::updateOrCreate(
            [
                'request_id' => $request->id,
                'break_start' => Carbon::parse("{$dateStr} 12:00:00"),
            ],
            [
                'break_end' => Carbon::parse("{$dateStr} 12:45:00"),
            ]
        );
    }
}