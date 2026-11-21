<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * データベースの初期シード処理を実行する。
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AttendanceSeeder::class,
            RestSeeder::class,
            RequestSeeder::class,
        ]);
    }
}