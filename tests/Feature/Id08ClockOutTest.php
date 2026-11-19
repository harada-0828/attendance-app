<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id08ClockOutTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkingUser(string $email): User
    {
        $user = User::create([
            'name' => '退勤テストユーザー',
            'email' => $email,
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $today = Carbon::today();
        Attendance::create([
            'user_id' => $user->id,
            'date' => $today->toDateString(),
            'clock_in' => $today->copy()->setTime(9, 0, 0),
            'clock_out' => null,
        ]);

        return $user;
    }

    /** @test */
    public function 退勤ボタンが表示され退勤処理後に退勤済になる()
    {
        $user = $this->makeWorkingUser('clockout1@example.com');

        $this->actingAs($user)
             ->get('/attendance')
             ->assertSee('退勤', false);

        $this->actingAs($user)
             ->post('/attendance', ['action' => 'clock_out'])
             ->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertSee('退勤済', false);
    }

    /** @test */
    public function 退勤時刻が勤怠一覧画面で確認できる()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 18, 10, 0));
        $user = $this->makeWorkingUser('clockout2@example.com');

        $this->actingAs($user)
             ->post('/attendance', ['action' => 'clock_out'])
             ->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance/list')
             ->assertStatus(200)
             ->assertSee('18:10', false);

        Carbon::setTestNow();
    }
}