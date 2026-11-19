<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id07BreakTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkingUser(): User
    {
        $user = User::create([
            'name' => '休憩テストユーザー',
            'email' => 'break_user@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => Carbon::now()->toDateString(),
            'clock_in' => Carbon::now()->subHour(),
            'clock_out' => null,
        ]);

        return $user;
    }

    /** @test */
    public function 休憩入ボタンが表示され処理後に休憩中になる()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 12, 0, 0));
        $user = $this->createWorkingUser();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertStatus(200)
             ->assertSee('休憩入', false);

        $this->actingAs($user)
             ->post('/attendance', ['action' => 'rest_in'])
             ->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertStatus(200)
             ->assertSee('休憩中', false);

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩入と休憩戻後に再度休憩入ボタンが表示される()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 12, 0, 0));
        $user = $this->createWorkingUser();

        $this->actingAs($user)
             ->post('/attendance', ['action' => 'rest_in'])
             ->assertRedirect();

        Carbon::setTestNow(Carbon::create(2026, 11, 19, 12, 30, 0));
        $this->actingAs($user)
             ->post('/attendance', ['action' => 'rest_out'])
             ->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertStatus(200)
             ->assertSee('休憩入', false);

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩戻ボタンが表示され処理後に出勤中に戻る()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 12, 0, 0));
        $user = $this->createWorkingUser();

        $this->actingAs($user)
             ->post('/attendance', ['action' => 'rest_in'])
             ->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertStatus(200)
             ->assertSee('休憩戻', false);

        $this->actingAs($user)
             ->post('/attendance', ['action' => 'rest_out'])
             ->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertStatus(200)
             ->assertSee('出勤中', false);

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩時刻が勤怠一覧画面で確認できる()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 9, 0, 0));

        $user = User::create([
            'name' => '休憩テストユーザー',
            'email' => 'break_user@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        // 出勤打刻（09:00）
        $this->actingAs($user)
             ->post('/attendance', ['action' => 'clock_in'])
             ->assertRedirect();

        // 休憩入（12:00）
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 12, 0, 0));
        $this->actingAs($user)
             ->post('/attendance', ['action' => 'rest_in'])
             ->assertRedirect();

        // 休憩戻（12:45）
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 12, 45, 0));
        $this->actingAs($user)
             ->post('/attendance', ['action' => 'rest_out'])
             ->assertRedirect();

        $this->assertDatabaseHas('rests', [
            'break_start' => '2026-11-19 12:00:00',
            'break_end' => '2026-11-19 12:45:00',
        ]);

        $this->actingAs($user)
             ->get('/attendance/list?date=2026-11')
             ->assertStatus(200)
             ->assertSee('11/19(木)', false)
             ->assertSee('09:00', false);

        Carbon::setTestNow();
    }
}