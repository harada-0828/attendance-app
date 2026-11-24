<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * ユーザーデータの初期シードを実行する。
     */
    public function run(): void
    {
        $users = [
            [
                'name' => '一般ユーザー1',
                'email' => 'user1@example.com',
                'admin_status' => false,
            ],
            [
                'name' => '一般ユーザー2',
                'email' => 'user2@example.com',
                'admin_status' => false,
            ],
            [
                'name' => '管理者ユーザー3',
                'email' => 'user3@example.com',
                'admin_status' => true,
            ],
        ];

        collect($users)->each(function (array $userData): void {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'admin_status' => $userData['admin_status'],
                    'email_verified_at' => now(),
                ]
            );
        });
    }
}
