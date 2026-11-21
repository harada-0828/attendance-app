<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id01RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    /** @test */
    public function 名前が未入力の場合お名前を入力してくださいが表示される()
    {
        $this->followingRedirects()
             ->from('/register')
             ->post('/register', $this->validPayload(['name' => '']))
             ->assertSee('お名前を入力してください', false);
    }

    /** @test */
    public function メールアドレスが未入力の場合メールアドレスを入力してくださいが表示される()
    {
        $this->followingRedirects()
             ->from('/register')
             ->post('/register', $this->validPayload(['email' => '']))
             ->assertSee('メールアドレスを入力してください', false);
    }

    /** @test */
    public function パスワードが8文字未満の場合パスワードは8文字以上で入力してくださいが表示される()
    {
        $this->followingRedirects()
             ->from('/register')
             ->post('/register', $this->validPayload([
                 'password' => 'pass123',
                 'password_confirmation' => 'pass123',
             ]))
             ->assertSee('パスワードは8文字以上で入力してください', false);
    }

    /** @test */
    public function パスワードが一致しない場合パスワードと一致しませんが表示される()
    {
        $this->followingRedirects()
             ->from('/register')
             ->post('/register', $this->validPayload([
                 'password' => 'password123',
                 'password_confirmation' => 'different123',
             ]))
             ->assertSee('パスワードと一致しません', false);
    }

    /** @test */
    public function パスワードが未入力の場合パスワードを入力してくださいが表示される()
    {
        $this->followingRedirects()
             ->from('/register')
             ->post('/register', $this->validPayload([
                 'password' => '',
                 'password_confirmation' => '',
             ]))
             ->assertSee('パスワードを入力してください', false);
    }

    /** @test */
    public function フォームに内容が入力されていた場合データベースに保存される()
    {
        $this->post('/register', $this->validPayload([
            'name' => '登録確認ユーザー',
            'email' => 'saved@example.com',
        ]));

        $this->assertDatabaseHas('users', [
            'name' => '登録確認ユーザー',
            'email' => 'saved@example.com',
        ]);
    }
}