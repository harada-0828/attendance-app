<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id09GeneralAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithAttendances(): User
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 10, 0, 0));

        $user = User::create([
            'name' => '一覧テストユーザー',
            'email' => 'list_user@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-11-10',
            'clock_in' => '2026-11-10 09:00:00',
            'clock_out' => '2026-11-10 18:00:00',
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-15',
            'clock_in' => '2026-10-15 09:30:00',
            'clock_out' => '2026-10-15 18:30:00',
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-12-05',
            'clock_in' => '2026-12-05 08:45:00',
            'clock_out' => '2026-12-05 17:45:00',
        ]);

        return $user;
    }

    /** @test */
    public function 勤怠一覧画面に現在の月と自分の勤怠情報が表示される()
    {
        $user = $this->createUserWithAttendances();

        $this->actingAs($user)
            ->get('/attendance/list')
            ->assertStatus(200)
            ->assertSee('2026/11', false)
            ->assertSee('09:00', false);

        Carbon::setTestNow();
    }

    /** @test */
    public function 前月指定時に前月の勤怠情報が表示される()
    {
        $user = $this->createUserWithAttendances();

        $this->actingAs($user)
            ->get('/attendance/list?date=2026-10')
            ->assertStatus(200)
            ->assertSee('2026/10', false)
            ->assertSee('09:30', false);

        Carbon::setTestNow();
    }

    /** @test */
    public function 翌月指定時に翌月の勤怠情報が表示される()
    {
        $user = $this->createUserWithAttendances();

        $this->actingAs($user)
            ->get('/attendance/list?date=2026-12')
            ->assertStatus(200)
            ->assertSee('2026/12', false)
            ->assertSee('08:45', false);

        Carbon::setTestNow();
    }

    /** @test */
    public function 詳細画面へ遷移できる()
    {
        $user = $this->createUserWithAttendances();
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', '2026-11-10')
            ->first();

        $this->actingAs($user)
            ->get('/attendance/detail/'.$attendance->id)
            ->assertStatus(200);

        Carbon::setTestNow();
    }
}
