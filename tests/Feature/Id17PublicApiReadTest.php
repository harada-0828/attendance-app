<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id17PublicApiReadTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function GET_api_v1_attendance_recordsで勤怠一覧がJSONで取得できる()
    {
        $user = User::create([
            'name' => '一覧確認ユーザー',
            'email' => 'api_list_user@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '2026-05-01 09:00:00',
            'clock_out' => '2026-05-01 18:00:00',
        ]);

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-05-02',
            'clock_in' => '2026-05-02 09:30:00',
            'clock_out' => '2026-05-02 18:30:00',
        ]);

        $this->getJson('/api/v1/attendance-records')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonFragment([
                'user_id' => $user->id,
                'date' => '2026-05-01',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);
    }

    /** @test */
    public function GET_api_v1_attendance_records_idで勤怠詳細がJSONで取得できる()
    {
        $user = User::create([
            'name' => '詳細確認ユーザー',
            'email' => 'api_show_user@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-05-05',
            'clock_in' => '2026-05-05 09:00:00',
            'clock_out' => '2026-05-05 18:00:00',
        ]);

        $this->getJson('/api/v1/attendance-records/' . $record->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.date', '2026-05-05')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'date',
                    'clock_in',
                    'clock_out',
                ],
            ]);
    }

    /** @test */
    public function 存在しないIDでは404とエラーJSONが返る()
    {
        $this->getJson('/api/v1/attendance-records/999999')
            ->assertStatus(404)
            ->assertJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }
}