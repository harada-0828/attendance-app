<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Rest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id20AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    private function createVerifiedGeneralUser(string $email = 'report_user@example.com'): User
    {
        return User::create([
            'name' => 'レポートテスト太郎',
            'email' => $email,
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function 未認証ユーザーはマイ勤怠レポートにアクセスするとログイン画面へリダイレクトされる()
    {
        $this->get('/attendance/report')
            ->assertRedirect('/login');
    }

    /** @test */
    public function 勤怠データが0件のユーザーでもマイ勤怠レポート画面が正常に表示され0で集計される()
    {
        $user = $this->createVerifiedGeneralUser('zero@example.com');

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertStatus(200)
            ->assertSee('マイ勤怠レポート')
            ->assertSee('0h 0m')
            ->assertSee('0 回')
            ->assertSee('0 日');
    }

    /** @test */
    public function 過去6ヶ月の基本サマリーと月次推移および当月の異常検知が正しく集計表示される()
    {
        $now = Carbon::create(2026, 11, 23, 12, 0, 0);
        Carbon::setTestNow($now);

        try {
            $user = $this->createVerifiedGeneralUser('stats@example.com');

            // 前月（2026-10）：通常勤務 09:00-18:00、休憩 12:00-13:00（1時間） => 実働 8h (480分)、残業 0m
            $prevAttendance = Attendance::create([
                'user_id' => $user->id,
                'date' => '2026-10-15',
                'clock_in' => '2026-10-15 09:00:00',
                'clock_out' => '2026-10-15 18:00:00',
            ]);
            Rest::create([
                'attendance_id' => $prevAttendance->id,
                'break_start' => '2026-10-15 12:00:00',
                'break_end' => '2026-10-15 13:00:00',
            ]);

            // 当月（2026-11）①：遅刻＋残業 09:30-20:00、休憩 12:00-13:00 => 実働 9h30m (570分)、残業 1h30m (90分)、遅刻 1回
            $lateOvertimeAttendance = Attendance::create([
                'user_id' => $user->id,
                'date' => '2026-11-10',
                'clock_in' => '2026-11-10 09:30:00',
                'clock_out' => '2026-11-10 20:00:00',
            ]);
            Rest::create([
                'attendance_id' => $lateOvertimeAttendance->id,
                'break_start' => '2026-11-10 12:00:00',
                'break_end' => '2026-11-10 13:00:00',
            ]);

            // 当月（2026-11）②：早退 09:00-17:00、休憩 12:00-13:00 => 実働 7h (420分)、残業 0m、早退 1回
            $earlyLeaveAttendance = Attendance::create([
                'user_id' => $user->id,
                'date' => '2026-11-11',
                'clock_in' => '2026-11-11 09:00:00',
                'clock_out' => '2026-11-11 17:00:00',
            ]);
            Rest::create([
                'attendance_id' => $earlyLeaveAttendance->id,
                'break_start' => '2026-11-11 12:00:00',
                'break_end' => '2026-11-11 13:00:00',
            ]);

            // 当月（2026-11）③：長時間労働（10時間超） 08:00-21:00、休憩 12:00-13:00 => 実働 12h (720分)、残業 4h (240分)、長時間労働 1日
            $longWorkAttendance = Attendance::create([
                'user_id' => $user->id,
                'date' => '2026-11-12',
                'clock_in' => '2026-11-12 08:00:00',
                'clock_out' => '2026-11-12 21:00:00',
            ]);
            Rest::create([
                'attendance_id' => $longWorkAttendance->id,
                'break_start' => '2026-11-12 12:00:00',
                'break_end' => '2026-11-12 13:00:00',
            ]);

            // 合計：実働 480 + 570 + 420 + 720 = 2190分 (36h 30m)
            // 残業：0 + 90 + 0 + 240 = 330分 (5h 30m)
            // 平均（4日分）：2190 / 4 = 547分 (9h 7m)
            $response = $this->actingAs($user)
                ->get('/attendance/report');

            $response->assertStatus(200)
                ->assertSee('36h 30m')
                ->assertSee('5h 30m')
                ->assertSee('9h 7m')
                ->assertSee('2026-06')
                ->assertSee('2026-10')
                ->assertSee('2026-11')
                ->assertSee('1 回')
                ->assertSee('1 日');
        } finally {
            Carbon::setTestNow();
        }
    }
}