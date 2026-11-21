<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id06ClockInTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email): User
    {
        return User::create([
            'name' => '出勤テストユーザー',
            'email' => $email,
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function 出勤ボタンが表示され出勤処理後に勤務中になる()
    {
        $user = $this->makeUser('clockin1@example.com');

        $this->actingAs($user)
             ->get('/attendance')
             ->assertSee('出勤', false);

        $this->actingAs($user)
             ->post('/attendance', ['action' => 'clock_in'])
             ->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertSee('出勤中', false);
    }

    /** @test */
    public function 退勤済のユーザーには出勤ボタンが表示されない()
    {
        $user = $this->makeUser('clockin2@example.com');
        $today = Carbon::today();

        Attendance::create([
            'user_id' => $user->id,
            'date' => $today->toDateString(),
            'clock_in' => $today->copy()->setTime(9, 0, 0),
            'clock_out' => $today->copy()->setTime(18, 0, 0),
        ]);

        $this->actingAs($user)
             ->get('/attendance')
             ->assertDontSee('value="clock_in"', false);
    }

    /** @test */
    public function 出勤時刻が勤怠一覧画面で確認できる()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 9, 5, 0));
        $user = $this->makeUser('clockin3@example.com');

        $this->actingAs($user)
             ->post('/attendance', ['action' => 'clock_in'])
             ->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance/list')
             ->assertStatus(200)
             ->assertSee('09:05', false);

        Carbon::setTestNow();
    }
}