<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Id18PublicApiWriteTest extends TestCase
{
    use RefreshDatabase;

    private function createApiUser(string $email): User
    {
        return User::create([
            'name' => 'APIテストユーザー',
            'email' => $email,
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function POST_api_v1_attendance_recordsで勤怠が作成される()
    {
        $user = $this->createApiUser('api_store_user@example.com');
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-06-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'APIテスト登録',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.date', '2026-06-01')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00');

        $createdId = $response->json('data.id');
        $this->assertNotNull($createdId);

        $this->assertDatabaseHas('attendances', [
            'id' => $createdId,
            'user_id' => $user->id,
            'date' => '2026-06-01 00:00:00',
            'clock_in' => '2026-06-01 09:00:00',
            'clock_out' => '2026-06-01 18:00:00',
        ]);
    }

    /** @test */
    public function バリデーションエラー時に422と日本語エラーメッセージが返る()
    {
        $user = $this->createApiUser('api_validation_user@example.com');
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/attendance-records', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'clock_in'])
            ->assertJsonPath('errors.date.0', '勤怠日は必須です。')
            ->assertJsonPath('errors.clock_in.0', '出勤時刻は必須です。');

        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026/06/01',
            'clock_in' => '09:00',
            'clock_out' => '08:00:00',
            'comment' => str_repeat('あ', 256),
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', '勤怠日は YYYY-MM-DD 形式で指定してください。')
            ->assertJsonPath('errors.clock_in.0', '出勤時刻は HH:MM:SS 形式で指定してください。')
            ->assertJsonPath('errors.clock_out.0', '退勤時刻は出勤時刻より後の時刻を指定してください。')
            ->assertJsonPath('errors.comment.0', '備考は 255 文字以内で入力してください。');
    }

    /** @test */
    public function PUT_api_v1_attendance_records_idで勤怠が更新され存在しないIDは404を返す()
    {
        $user = $this->createApiUser('api_update_user@example.com');
        Sanctum::actingAs($user, ['*']);

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-06-02',
            'clock_in' => '2026-06-02 09:00:00',
            'clock_out' => '2026-06-02 18:00:00',
        ]);

        $this->putJson('/api/v1/attendance-records/' . $record->id, [
            'date' => '2026-06-02',
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.clock_out', '19:00:00');

        $updated = AttendanceRecord::find($record->id);
        $this->assertNotNull($updated);
        $this->assertSame('19:00:00', optional($updated->clock_out)->format('H:i:s'));

        $this->assertDatabaseHas('attendances', [
            'id' => $record->id,
            'clock_out' => '2026-06-02 19:00:00',
        ]);

        $this->putJson('/api/v1/attendance-records/99999', [
            'date' => '2026-06-03',
            'clock_in' => '09:00:00',
        ])
            ->assertStatus(404)
            ->assertJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    /** @test */
    public function DELETE_api_v1_attendance_records_idで勤怠が削除され存在しないIDは404を返す()
    {
        $user = $this->createApiUser('api_delete_user@example.com');
        Sanctum::actingAs($user, ['*']);

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-06-04',
            'clock_in' => '2026-06-04 09:00:00',
            'clock_out' => '2026-06-04 18:00:00',
        ]);

        $this->deleteJson('/api/v1/attendance-records/' . $record->id)
            ->assertStatus(204);

        $this->assertDatabaseMissing('attendances', [
            'id' => $record->id,
        ]);

        $this->deleteJson('/api/v1/attendance-records/99999')
            ->assertStatus(404)
            ->assertJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }
}