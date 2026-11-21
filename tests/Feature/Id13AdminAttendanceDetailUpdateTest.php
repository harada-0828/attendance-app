<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id13AdminAttendanceDetailUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminAndAttendance(): array
    {
        $admin = User::create([
            'name' => '管理者',
            'email' => 'admin_detail@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => true,
            'email_verified_at' => now(),
        ]);

        $staff = User::create([
            'name' => '対象スタッフ',
            'email' => 'target_staff@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-11-19',
            'clock_in' => '2026-11-19 09:00:00',
            'clock_out' => '2026-11-19 18:00:00',
        ]);

        return [$admin, $staff, $attendance];
    }

    /** @test */
    public function 管理者勤怠詳細画面に選択したデータが表示される()
    {
        [$admin, $staff, $attendance] = $this->createAdminAndAttendance();

        $this->actingAs($admin)
             ->get('/admin/attendance/' . $attendance->id)
             ->assertStatus(200)
             ->assertSee('対象スタッフ', false)
             ->assertSee('09:00', false)
             ->assertSee('18:00', false);
    }

    /** @test */
    public function 出勤時間が退勤時間より後の場合エラーメッセージが表示される()
    {
        [$admin, $staff, $attendance] = $this->createAdminAndAttendance();
        $url = '/admin/attendance/' . $attendance->id;

        $this->actingAs($admin)
             ->followingRedirects()
             ->from($url)
             ->post($url, [
                 'new_clock_in' => '19:00',
                 'new_clock_out' => '18:00',
                 'comment' => '管理者修正理由',
             ])
             ->assertSee('出勤時間もしくは退勤時間が不適切な値です', false);
    }

    /** @test */
    public function 休憩開始時間が退勤時間より後の場合エラーメッセージが表示される()
    {
        [$admin, $staff, $attendance] = $this->createAdminAndAttendance();
        $url = '/admin/attendance/' . $attendance->id;

        $this->actingAs($admin)
             ->followingRedirects()
             ->from($url)
             ->post($url, [
                 'new_clock_in' => '09:00',
                 'new_clock_out' => '18:00',
                 'new_break_in' => ['18:30'],
                 'new_break_out' => ['19:00'],
                 'comment' => '管理者休憩開始エラー確認',
             ])
             ->assertSee('休憩時間が不適切な値です', false);
    }

    /** @test */
    public function 休憩終了時間が退勤時間より後の場合エラーメッセージが表示される()
    {
        [$admin, $staff, $attendance] = $this->createAdminAndAttendance();
        $url = '/admin/attendance/' . $attendance->id;

        $this->actingAs($admin)
             ->followingRedirects()
             ->from($url)
             ->post($url, [
                 'new_clock_in' => '09:00',
                 'new_clock_out' => '18:00',
                 'new_break_in' => ['12:00'],
                 'new_break_out' => ['18:30'],
                 'comment' => '管理者休憩終了エラー確認',
             ])
             ->assertSee('休憩時間もしくは退勤時間が不適切な値です', false);
    }

    /** @test */
    public function 備考未入力の場合備考を記入してくださいが表示される()
    {
        [$admin, $staff, $attendance] = $this->createAdminAndAttendance();
        $url = '/admin/attendance/' . $attendance->id;

        $this->actingAs($admin)
             ->followingRedirects()
             ->from($url)
             ->post($url, [
                 'new_clock_in' => '09:10',
                 'new_clock_out' => '18:00',
                 'comment' => '',
             ])
             ->assertSee('備考を記入してください', false);
    }
}