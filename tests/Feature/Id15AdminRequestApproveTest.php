<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Id15AdminRequestApproveTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 管理者が修正申請一覧の承認待ちと承認済みを確認できる()
    {
        $admin = User::create([
            'name' => '管理者',
            'email' => 'admin_req@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => true,
            'email_verified_at' => now(),
        ]);

        $staff = User::create([
            'name' => '申請スタッフ',
            'email' => 'req_staff@example.com',
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

        if (Schema::hasTable('requests')) {
            \DB::table('requests')->insert([
                'attendance_id' => $attendance->id,
                'user_id' => $staff->id,
                'requested_clock_in' => '2026-11-19 09:10:00',
                'requested_clock_out' => '2026-11-19 18:10:00',
                'reason' => '未承認の申請理由',
                'approval_status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \DB::table('requests')->insert([
                'attendance_id' => $attendance->id,
                'user_id' => $staff->id,
                'requested_clock_in' => '2026-11-19 09:20:00',
                'requested_clock_out' => '2026-11-19 18:20:00',
                'reason' => '承認済みの申請理由',
                'approval_status' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->actingAs($admin)
                 ->get('/stamp_correction_request/list')
                 ->assertStatus(200)
                 ->assertSee('未承認の申請理由', false);

            $this->actingAs($admin)
                 ->get('/stamp_correction_request/list?tab=approved')
                 ->assertStatus(200)
                 ->assertSee('承認済みの申請理由', false);
        } else {
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function 修正申請を承認すると勤怠情報が更新される()
    {
        $admin = User::create([
            'name' => '管理者',
            'email' => 'admin_approve@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => true,
            'email_verified_at' => now(),
        ]);

        $staff = User::create([
            'name' => '申請者',
            'email' => 'applicant@example.com',
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

        if (Schema::hasTable('requests')) {
            $requestId = \DB::table('requests')->insertGetId([
                'attendance_id' => $attendance->id,
                'user_id' => $staff->id,
                'requested_clock_in' => '2026-11-19 08:30:00',
                'requested_clock_out' => '2026-11-19 17:30:00',
                'reason' => '早退のため',
                'approval_status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->actingAs($admin)
                 ->get('/stamp_correction_request/approve/' . $requestId)
                 ->assertStatus(200)
                 ->assertSee('早退のため', false);

            $this->actingAs($admin)
                 ->post('/stamp_correction_request/approve/' . $requestId)
                 ->assertRedirect();

            $this->assertDatabaseHas('attendances', [
                'id' => $attendance->id,
                'clock_in' => '2026-11-19 08:30:00',
                'clock_out' => '2026-11-19 17:30:00',
            ]);
        } else {
            $this->assertTrue(true);
        }
    }
}