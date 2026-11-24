<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id12AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminAndUsers(): User
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 10, 0, 0));

        $admin = User::create([
            'name' => '管理者',
            'email' => 'admin_list@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => true,
            'email_verified_at' => now(),
        ]);

        $userA = User::create([
            'name' => 'スタッフA',
            'email' => 'staff_a@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $userB = User::create([
            'name' => 'スタッフB',
            'email' => 'staff_b@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        // 当日（2026-11-19）
        Attendance::create([
            'user_id' => $userA->id,
            'date' => '2026-11-19',
            'clock_in' => '2026-11-19 09:00:00',
            'clock_out' => '2026-11-19 18:00:00',
        ]);
        Attendance::create([
            'user_id' => $userB->id,
            'date' => '2026-11-19',
            'clock_in' => '2026-11-19 09:30:00',
            'clock_out' => '2026-11-19 18:30:00',
        ]);

        // 前日（2026-11-18）
        Attendance::create([
            'user_id' => $userA->id,
            'date' => '2026-11-18',
            'clock_in' => '2026-11-18 08:50:00',
            'clock_out' => '2026-11-18 17:50:00',
        ]);

        // 翌日（2026-11-20）
        Attendance::create([
            'user_id' => $userA->id,
            'date' => '2026-11-20',
            'clock_in' => '2026-11-20 09:10:00',
            'clock_out' => '2026-11-20 18:10:00',
        ]);

        return $admin;
    }

    /** @test */
    public function 管理者の日次勤怠一覧に当日の全ユーザー勤怠と現在の日付が表示される()
    {
        $admin = $this->createAdminAndUsers();

        $this->actingAs($admin)
            ->get('/admin/attendance/list')
            ->assertStatus(200)
            ->assertSee('スタッフA', false)
            ->assertSee('スタッフB', false)
            ->assertSee('09:00', false)
            ->assertSee('09:30', false);

        Carbon::setTestNow();
    }

    /** @test */
    public function 前日指定時に前日の勤怠情報が表示される()
    {
        $admin = $this->createAdminAndUsers();

        $this->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-11-18')
            ->assertStatus(200)
            ->assertSee('08:50', false);

        Carbon::setTestNow();
    }

    /** @test */
    public function 翌日指定時に翌日の勤怠情報が表示される()
    {
        $admin = $this->createAdminAndUsers();

        $this->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-11-20')
            ->assertStatus(200)
            ->assertSee('09:10', false);

        Carbon::setTestNow();
    }
}
