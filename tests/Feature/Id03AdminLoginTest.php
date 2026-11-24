<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Id03AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        return User::create([
            'name' => '管理者ユーザー',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => true,
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function メールアドレスが未入力の場合メールアドレスを入力してくださいが表示される()
    {
        $this->createAdminUser();

        $this->followingRedirects()
            ->from('/admin/login')
            ->post('/admin/login', [
                'email' => '',
                'password' => 'password123',
            ])
            ->assertSee('メールアドレスを入力してください', false);
    }

    /** @test */
    public function パスワードが未入力の場合パスワードを入力してくださいが表示される()
    {
        $this->createAdminUser();

        $this->followingRedirects()
            ->from('/admin/login')
            ->post('/admin/login', [
                'email' => 'admin@example.com',
                'password' => '',
            ])
            ->assertSee('パスワードを入力してください', false);
    }

    /** @test */
    public function 登録内容と一致しない場合ログイン情報が登録されていませんが表示される()
    {
        $this->createAdminUser();

        $this->followingRedirects()
            ->from('/admin/login')
            ->post('/admin/login', [
                'email' => 'wrong-admin@example.com',
                'password' => 'password123',
            ])
            ->assertSee('ログイン情報が登録されていません', false);
    }
}
