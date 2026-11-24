<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id02UserLoginTest extends TestCase
{
    use RefreshDatabase;

    private function createGeneralUser(): User
    {
        return User::create([
            'name' => '一般ユーザー',
            'email' => 'general@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function メールアドレスが未入力の場合メールアドレスを入力してくださいが表示される()
    {
        $this->createGeneralUser();

        $this->followingRedirects()
            ->from('/login')
            ->post('/login', [
                'email' => '',
                'password' => 'password123',
            ])
            ->assertSee('メールアドレスを入力してください', false);
    }

    /** @test */
    public function パスワードが未入力の場合パスワードを入力してくださいが表示される()
    {
        $this->createGeneralUser();

        $this->followingRedirects()
            ->from('/login')
            ->post('/login', [
                'email' => 'general@example.com',
                'password' => '',
            ])
            ->assertSee('パスワードを入力してください', false);
    }

    /** @test */
    public function 登録内容と一致しない場合ログイン情報が登録されていませんが表示される()
    {
        $this->createGeneralUser();

        $this->followingRedirects()
            ->from('/login')
            ->post('/login', [
                'email' => 'wrong@example.com',
                'password' => 'password123',
            ])
            ->assertSee('ログイン情報が登録されていません', false);
    }
}
