<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Rest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id05AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email): User
    {
        return User::create([
            'name' => 'ステータス確認ユーザー',
            'email' => $email,
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function 勤務外の場合勤怠ステータスが勤務外と表示される()
    {
        $user = $this->makeUser('off@example.com');

        $this->actingAs($user)
             ->get('/attendance')
             ->assertStatus(200)
             ->assertSee('勤務外', false);
    }

    /** @test */
    public function 出勤中の場合勤怠ステータスが出勤中と表示される()
    {
        $user = $this->makeUser('working@example.com');
        $today = Carbon::today();

        Attendance::create([
            'user_id' => $user->id,
            'date' => $today->toDateString(),
            'clock_in' => $today->copy()->setTime(9, 0, 0),
            'clock_out' => null,
        ]);

        $this->actingAs($user)
             ->get('/attendance')
             ->assertStatus(200)
             ->assertSee('出勤中', false);
    }

    /** @test */
    public function 休憩中の場合勤怠ステータスが休憩中と表示される()
    {
        $user = $this->makeUser('break@example.com');
        $today = Carbon::today();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $today->toDateString(),
            'clock_in' => $today->copy()->setTime(9, 0, 0),
            'clock_out' => null,
        ]);

        Rest::create([
            'attendance_id' => $attendance->id,
            'break_start' => $today->copy()->setTime(12, 0, 0),
            'break_end' => null,
        ]);

        $this->actingAs($user)
             ->get('/attendance')
             ->assertStatus(200)
             ->assertSee('休憩中', false);
    }

    /** @test */
    public function 退勤済の場合勤怠ステータスが退勤済と表示される()
    {
        $user = $this->makeUser('done@example.com');
        $today = Carbon::today();

        Attendance::create([
            'user_id' => $user->id,
            'date' => $today->toDateString(),
            'clock_in' => $today->copy()->setTime(9, 0, 0),
            'clock_out' => $today->copy()->setTime(18, 0, 0),
        ]);

        $this->actingAs($user)
             ->get('/attendance')
             ->assertStatus(200)
             ->assertSee('退勤済', false);
    }
}