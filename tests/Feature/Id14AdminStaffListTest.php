<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
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
    public function 選択したユーザーの勤怠一覧が表示される()
    {
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

        Attendance::create([
            'user_id' => $staff->id,
            'date' => '2026-11-19',
            'clock_in' => '2026-11-19 09:00:00',
            'clock_out' => '2026-11-19 18:00:00',
        ]);

        $this->actingAs($admin)
             ->get('/admin/attendance/staff/' . $staff->id)
             ->assertStatus(200)
             ->assertSee('09:00', false)
             ->assertSee('18:00', false);
    }
}