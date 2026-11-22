<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Id19SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 未認証時に書き込み系APIで401が返る()
    {
        $owner = User::create([
            'name' => '勤怠所有者',
            'email' => 'owner_unauth@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $record = AttendanceRecord::create([
            'user_id' => $owner->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:00:00',
            'clock_out' => '2026-06-10 18:00:00',
        ]);

        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-06-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ])
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $this->putJson('/api/v1/attendance-records/' . $record->id, [
            'date' => '2026-06-10',
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00',
        ])
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $this->deleteJson('/api/v1/attendance-records/' . $record->id)
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    /** @test */
    public function 認証済みユーザーは自分の勤怠を更新削除できる()
    {
        $user = User::create([
            'name' => '一般ユーザー',
            'email' => 'auth_user@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-06-12',
            'clock_in' => '2026-06-12 09:00:00',
            'clock_out' => '2026-06-12 18:00:00',
        ]);

        $this->putJson('/api/v1/attendance-records/' . $record->id, [
            'date' => '2026-06-12',
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.clock_in', '09:30:00')
            ->assertJsonPath('data.clock_out', '18:30:00');

        $this->deleteJson('/api/v1/attendance-records/' . $record->id)
            ->assertStatus(204);

        $this->assertDatabaseMissing('attendances', [
            'id' => $record->id,
        ]);
    }

    /** @test */
    public function 他ユーザーの勤怠を更新削除しようとすると403が返る()
    {
        $owner = User::create([
            'name' => '対象ユーザー',
            'email' => 'target_owner@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $otherUser = User::create([
            'name' => '別ユーザー',
            'email' => 'other_user@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($otherUser, ['*']);

        $record = AttendanceRecord::create([
            'user_id' => $owner->id,
            'date' => '2026-06-13',
            'clock_in' => '2026-06-13 09:00:00',
            'clock_out' => '2026-06-13 18:00:00',
        ]);

        $this->putJson('/api/v1/attendance-records/' . $record->id, [
            'date' => '2026-06-13',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ])
            ->assertStatus(403)
            ->assertJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);

        $this->deleteJson('/api/v1/attendance-records/' . $record->id)
            ->assertStatus(403)
            ->assertJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $record->id,
        ]);
    }
}