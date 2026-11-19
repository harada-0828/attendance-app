<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id11GeneralAttendanceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndAttendance(): array
    {
        $user = User::create([
            'name' => '修正申請ユーザー',
            'email' => 'request_user@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-11-10',
            'clock_in' => '2026-11-10 09:00:00',
            'clock_out' => '2026-11-10 18:00:00',
        ]);

        return [$user, $attendance];
    }

    /** @test */
    public function 出勤時間が退勤時間より後の場合エラーメッセージが表示される()
    {
        [$user, $attendance] = $this->createUserAndAttendance();
        $url = '/attendance/detail/' . $attendance->id;

        $this->actingAs($user)
             ->followingRedirects()
             ->from($url)
             ->post($url, [
                 'new_clock_in' => '19:00',
                 'new_clock_out' => '18:00',
                 'comment' => '電車遅延のため',
             ])
             ->assertSee('出勤時間もしくは退勤時間が不適切な値です', false);
    }

    /** @test */
    public function 備考未入力の場合備考を記入してくださいが表示される()
    {
        [$user, $attendance] = $this->createUserAndAttendance();
        $url = '/attendance/detail/' . $attendance->id;

        $this->actingAs($user)
             ->followingRedirects()
             ->from($url)
             ->post($url, [
                 'new_clock_in' => '09:10',
                 'new_clock_out' => '18:00',
                 'comment' => '',
             ])
             ->assertSee('備考を記入してください', false);
    }

    /** @test */
    public function 修正申請処理で保存され申請一覧に表示される()
    {
        [$user, $attendance] = $this->createUserAndAttendance();
        $url = '/attendance/detail/' . $attendance->id;

        $this->actingAs($user)
             ->post($url, [
                 'new_clock_in' => '09:15',
                 'new_clock_out' => '18:00',
                 'comment' => '打刻漏れ修正申請',
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('requests', [
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'reason' => '打刻漏れ修正申請',
        ]);

        $this->actingAs($user)
             ->get('/stamp_correction_request/list')
             ->assertStatus(200)
             ->assertSee('打刻漏れ修正申請', false);
    }
}