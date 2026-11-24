<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id04AttendanceDateTimeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 現在の日時情報が_u_iと同じ形式で出力されている()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 13, 45, 0));

        $user = User::create([
            'name' => '日時テストユーザー',
            'email' => 'datetime@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/attendance')
            ->assertStatus(200)
            ->assertSee('2026年11月19日', false);

        Carbon::setTestNow();
    }
}
