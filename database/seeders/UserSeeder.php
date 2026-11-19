<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 管理者ユーザー
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '管理者ユーザー',
                'password' => Hash::make('password'),
                'admin_status' => true,
                'email_verified_at' => now(),
            ]
        );

        // 一般ユーザー（スタッフ）
        $users = [
            [
                'name' => '一般ユーザー1',
                'email' => 'user1@example.com',
            ],
            [
                'name' => '一般ユーザー2',
                'email' => 'user2@example.com',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'admin_status' => false,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}