<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Request as AttendanceRequest; // モデル名が Request の場合はエイリアス調整（App\Models\CorrectionRequest 等ならそちらに変更）
use App\Models\RequestRest;
use App\Models\Rest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AttendanceSeeder::class,
            RestSeeder::class,
        ]);
        
        // 1. ユーザー1（一般・メール認証済み）
        $user1 = User::updateOrCreate(
            ['email' => 'user1@example.com'],
            [
                'name' => '一般ユーザー1',
                'password' => Hash::make('password'),
                'admin_status' => false,
                'email_verified_at' => now(),
            ]
        );

        // 2. ユーザー2（一般・メール認証済み）
        $user2 = User::updateOrCreate(
            ['email' => 'user2@example.com'],
            [
                'name' => '一般ユーザー2',
                'password' => Hash::make('password'),
                'admin_status' => false,
                'email_verified_at' => now(),
            ]
        );

        // 3. ユーザー3（管理者・メール認証済み）
        User::updateOrCreate(
            ['email' => 'user3@example.com'],
            [
                'name' => '管理者ユーザー',
                'password' => Hash::make('password'),
                'admin_status' => true,
                'email_verified_at' => now(),
            ]
        );

        // 当日・前日・前月の勤怠サンプルデータを作成（一般ユーザー1・2）
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $lastMonthDay = Carbon::now()->subMonth()->startOfMonth()->addDays(9);

        foreach ([$user1, $user2] as $index => $targetUser) {
            // 昨日の退勤済み勤怠＋休憩
            $attYesterday = Attendance::updateOrCreate(
                [
                    'user_id' => $targetUser->id,
                    'date' => $yesterday->toDateString(),
                ],
                [
                    'clock_in' => $yesterday->copy()->setTime(9, 0, 0),
                    'clock_out' => $yesterday->copy()->setTime(18, 0, 0),
                ]
            );

            Rest::firstOrCreate(
                [
                    'attendance_id' => $attYesterday->id,
                    'break_start' => $yesterday->copy()->setTime(12, 0, 0),
                ],
                [
                    'break_end' => $yesterday->copy()->setTime(13, 0, 0),
                ]
            );

            // 前月の勤怠データ（前月切替確認用）
            Attendance::updateOrCreate(
                [
                    'user_id' => $targetUser->id,
                    'date' => $lastMonthDay->toDateString(),
                ],
                [
                    'clock_in' => $lastMonthDay->copy()->setTime(9, 30, 0),
                    'clock_out' => $lastMonthDay->copy()->setTime(18, 30, 0),
                ]
            );

            // 当日の勤怠データ（user1は出勤中・休憩終了済み、user2は退勤済みにするなど変化をつけると確認しやすい）
            if ($index === 0) {
                $attToday = Attendance::updateOrCreate(
                    [
                        'user_id' => $targetUser->id,
                        'date' => $today->toDateString(),
                    ],
                    [
                        'clock_in' => $today->copy()->setTime(9, 0, 0),
                        'clock_out' => null,
                    ]
                );

                // 修正申請（承認待ち: approval_status = true）サンプル
                $req = AttendanceRequest::firstOrCreate(
                    [
                        'attendance_id' => $attToday->id,
                        'user_id' => $targetUser->id,
                        'reason' => '打刻時刻の修正申請（初期データ）',
                    ],
                    [
                        'requested_clock_in' => $today->copy()->setTime(8, 50, 0),
                        'requested_clock_out' => $today->copy()->setTime(18, 0, 0),
                        'approval_status' => true,
                    ]
                );

                RequestRest::firstOrCreate(
                    [
                        'request_id' => $req->id,
                        'break_start' => $today->copy()->setTime(12, 0, 0),
                    ],
                    [
                        'break_end' => $today->copy()->setTime(12, 45, 0),
                    ]
                );
            } else {
                Attendance::updateOrCreate(
                    [
                        'user_id' => $targetUser->id,
                        'date' => $today->toDateString(),
                    ],
                    [
                        'clock_in' => $today->copy()->setTime(9, 15, 0),
                        'clock_out' => $today->copy()->setTime(18, 15, 0),
                    ]
                );
            }
        }
    }
}