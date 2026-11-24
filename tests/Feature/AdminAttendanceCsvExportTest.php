<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Rest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAttendanceCsvExportTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(string $email = 'admin_csv@example.com'): User
    {
        return User::create([
            'name' => '管理者CSV',
            'email' => $email,
            'password' => Hash::make('password123'),
            'admin_status' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function createGeneralUser(string $name = '一般ユーザーCSV', string $email = 'staff_csv@example.com'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function 未認証ユーザーは_cs_v出力を実行できない()
    {
        $staff = $this->createGeneralUser();

        $this->post('/export', [
            'user_id' => $staff->id,
            'year_month' => '2026-11',
        ])->assertRedirect('/login');
    }

    /** @test */
    public function 管理者が指定したユーザーと月の勤怠一覧を_cs_vダウンロードできる()
    {
        $admin = $this->createAdminUser();
        $targetStaff = $this->createGeneralUser('対象スタッフ', 'target@example.com');
        $otherStaff = $this->createGeneralUser('別スタッフ', 'other@example.com');

        // 対象月の勤怠（2026-11-10：09:00-18:00、休憩 12:00-13:00 => 実働 8:00）
        $targetAttendance = Attendance::create([
            'user_id' => $targetStaff->id,
            'date' => '2026-11-10',
            'clock_in' => '2026-11-10 09:00:00',
            'clock_out' => '2026-11-10 18:00:00',
        ]);
        Rest::create([
            'attendance_id' => $targetAttendance->id,
            'break_start' => '2026-11-10 12:00:00',
            'break_end' => '2026-11-10 13:00:00',
        ]);

        // 別月の勤怠（2026-10-15：CSVに含まれないこと）
        Attendance::create([
            'user_id' => $targetStaff->id,
            'date' => '2026-10-15',
            'clock_in' => '2026-10-15 09:00:00',
            'clock_out' => '2026-10-15 18:00:00',
        ]);

        // 別ユーザーの勤怠（CSVに含まれないこと）
        Attendance::create([
            'user_id' => $otherStaff->id,
            'date' => '2026-11-10',
            'clock_in' => '2026-10-10 10:00:00',
            'clock_out' => '2026-11-10 19:00:00',
        ]);

        $response = $this->actingAs($admin)->post('/export', [
            'user_id' => $targetStaff->id,
            'year_month' => '2026-11',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            "attendance_user{$targetStaff->id}_2026-11.csv",
            (string) $response->headers->get('Content-Disposition')
        );

        // StreamedResponse の出力内容を取得（UTF-8 BOM を除去して検証）
        ob_start();
        $response->sendContent();
        $rawContent = (string) ob_get_clean();
        $content = ltrim($rawContent, "\xEF\xBB\xBF");

        $this->assertStringContainsString('日付,出勤,退勤,休憩,合計', $content);
        $this->assertStringContainsString('11月10日（火）,09:00,18:00,1:00,8:00', $content);
        $this->assertStringNotContainsString('10月15日', $content);
    }

    /** @test */
    public function 不正なパラメータの場合はバリデーションエラーになる()
    {
        $admin = $this->createAdminUser('admin_invalid@example.com');

        $this->actingAs($admin)
            ->post('/export', [
                'user_id' => 99999,
                'year_month' => 'invalid-month',
            ])
            ->assertSessionHasErrors(['user_id', 'year_month']);
    }
}
