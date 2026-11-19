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

    private function makeWorkingUser(string $email): User
    {
        $user = User::create([
            'name' => '休憩テストユーザー',
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
    public function 休憩入ボタンが表示され処理後に休憩中になる()
    {
        $user = $this->makeWorkingUser('break1@example.com');

        $this->actingAs($user)
             ->get('/attendance')
             ->assertSee('休憩入', false);

        $this->actingAs($user)
             ->post('/attendance', ['action' => 'rest_in'])
             ->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertSee('休憩中', false);
    }

    /** @test */
    public function 休憩入と休憩戻後に再度休憩入ボタンが表示される()
    {
        $user = $this->makeWorkingUser('break2@example.com');

        $this->actingAs($user)->post('/attendance', ['action' => 'rest_in'])->assertRedirect();
        $this->actingAs($user)->post('/attendance', ['action' => 'rest_out'])->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertSee('休憩入', false);
    }

    /** @test */
    public function 休憩戻ボタンが表示され処理後に出勤中に戻る()
    {
        $user = $this->makeWorkingUser('break3@example.com');

        $this->actingAs($user)->post('/attendance', ['action' => 'rest_in'])->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertSee('休憩戻', false);

        $this->actingAs($user)->post('/attendance', ['action' => 'rest_out'])->assertRedirect();

        $this->actingAs($user)
             ->get('/attendance')
             ->assertSee('出勤中', false);
    }
}