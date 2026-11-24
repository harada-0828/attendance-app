<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class Id16EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 新規会員登録時に認証メールが送信されメール認証誘導画面へ遷移する()
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => '認証テスト太郎',
            'email' => 'verify_test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'verify_test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);

        $response->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertStatus(200)
            ->assertSee('認証はこちらから')
            ->assertSee('認証メールを再送する');
    }

    /** @test */
    public function 未認証ユーザーが勤怠画面にアクセスするとメール認証誘導画面へ遷移する()
    {
        $unverifiedUser = User::create([
            'name' => '未認証ユーザー',
            'email' => 'unverified@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => null,
        ]);

        $this->actingAs($unverifiedUser)
            ->get('/attendance')
            ->assertRedirect(route('verification.notice'));
    }

    /** @test */
    public function 認証メールを再送するボタンを押すと認証メールが再送信される()
    {
        Notification::fake();

        $unverifiedUser = User::create([
            'name' => '再送テストユーザー',
            'email' => 'resend@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => null,
        ]);

        $this->actingAs($unverifiedUser)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($unverifiedUser, VerifyEmail::class);
    }

    /** @test */
    public function 署名付き認証リンクにアクセスするとメール認証が完了し勤怠登録画面へ遷移する()
    {
        $unverifiedUser = User::create([
            'name' => '認証完了ユーザー',
            'email' => 'complete_verify@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $unverifiedUser->id,
                'hash' => sha1($unverifiedUser->getEmailForVerification()),
            ]
        );

        $this->actingAs($unverifiedUser)
            ->get($verificationUrl)
            ->assertRedirect('/attendance?verified=1');

        $this->assertNotNull($unverifiedUser->fresh()->email_verified_at);

        $this->actingAs($unverifiedUser->fresh())
            ->get('/attendance')
            ->assertStatus(200);
    }
}
