<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Rest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id10GeneralAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤怠詳細画面に氏名日付出勤退勤休憩が表示される()
    {
        $user = User::create([
            'name' => '詳細確認太郎',
            'email' => 'detail_user@example.com',
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

        Rest::create([
            'attendance_id' => $attendance->id,
            'break_start' => '2026-11-10 12:00:00',
            'break_end' => '2026-11-10 13:00:00',
        ]);

        $this->actingAs($user)
             ->get('/attendance/detail/' . $attendance->id)
             ->assertStatus(200)
             ->assertSee('詳細確認太郎', false)
             ->assertSee('09:00', false)
             ->assertSee('18:00', false)
             ->assertSee('12:00', false)
             ->assertSee('13:00', false);
    }
}