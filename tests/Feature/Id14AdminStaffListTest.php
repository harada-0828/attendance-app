<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id14AdminStaffListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function スタッフ一覧に全一般ユーザーの氏名とメールアドレスが表示される()
    {
        $admin = User::create([
            'name' => '管理者',
            'email' => 'admin_staff@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => '一般太郎',
            'email' => 'taro_general@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => '一般花子',
            'email' => 'hanako_general@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
             ->get('/admin/staff/list')
             ->assertStatus(200)
             ->assertSee('一般太郎', false)
             ->assertSee('taro_general@example.com', false)
             ->assertSee('一般花子', false)
             ->assertSee('hanako_general@example.com', false);
    }

    /** @test */
    public function 選択したユーザーの勤怠一覧が表示され前月翌月切替と詳細遷移ができる()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 10, 0, 0));

        $admin = User::create([
            'name' => '管理者',
            'email' => 'admin_staff2@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => true,
            'email_verified_at' => now(),
        ]);

        $staff = User::create([
            'name' => '月次確認スタッフ',
            'email' => 'monthly_staff@example.com',
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

        Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-10-15',
            'clock_in' => '2026-10-15 09:15:00',
            'clock_out' => '2026-10-15 18:15:00',
        ]);

        Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-12-05',
            'clock_in' => '2026-12-05 09:20:00',
            'clock_out' => '2026-12-05 18:20:00',
        ]);

        $this->actingAs($admin)
             ->get('/admin/attendance/staff/' . $staff->id)
             ->assertStatus(200)
             ->assertSee('09:00', false)
             ->assertSee('18:00', false);

        $this->actingAs($admin)
             ->get('/admin/attendance/staff/' . $staff->id . '?date=2026-10')
             ->assertStatus(200)
             ->assertSee('09:15', false);

        $this->actingAs($admin)
             ->get('/admin/attendance/staff/' . $staff->id . '?date=2026-12')
             ->assertStatus(200)
             ->assertSee('09:20', false);

        $this->actingAs($admin)
             ->get('/admin/attendance/' . $attendance->id)
             ->assertStatus(200);

        Carbon::setTestNow();
    }
}