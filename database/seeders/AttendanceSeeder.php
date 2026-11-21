<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class AttendanceSeeder extends Seeder
{
    /**
     * 勤怠データの初期シードを実行する。
     *
     * @return void
     */
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        $user2 = User::where('email', 'user2@example.com')->first();
        $user3 = User::where('email', 'user3@example.com')->first();

        if ($user1) {
            $this->seedUser1IntentionalData($user1);
        }

        collect([$user2, $user3])
            ->filter()
            ->each(function (User $user): void {
                $this->seedGeneralSampleData($user);
            });
    }

    /**
     * 検証用の意図的な勤怠データ（過去5ヶ月および当月分）を登録する。
     *
     * @param  User  $user
     * @return void
     */
    private function seedUser1IntentionalData(User $user): void
    {
        for ($monthOffset = 5; $monthOffset >= 1; $monthOffset--) {
            $targetMonth = Carbon::now()->subMonthsNoOverflow($monthOffset)->startOfMonth();
            $weekdays = $this->getWeekdaysInMonth($targetMonth)->take(15);

            $weekdays->each(function (Carbon $date) use ($user): void {
                $this->upsertAttendance($user->id, $date, '09:00:00', '18:00:00');
            });
        }

        $currentMonthWeekdays = $this->getWeekdaysInMonth(Carbon::now()->startOfMonth())
            ->take(17)
            ->values();

        $patterns = collect([
            ...array_fill(0, 10, ['09:00:00', '18:00:00']),
            ...array_fill(0, 3, ['09:00:00', '20:00:00']),
            ...array_fill(0, 2, ['09:30:00', '18:00:00']),
            ...array_fill(0, 1, ['09:00:00', '17:00:00']),
            ...array_fill(0, 1, ['08:00:00', '21:00:00']),
        ]);

        $currentMonthWeekdays
            ->zip($patterns)
            ->each(function (Collection $pair) use ($user): void {
                /** @var Carbon|null $date */
                $date = $pair->get(0);
                /** @var array{0: string, 1: string}|null $times */
                $times = $pair->get(1);

                if (!$date || !$times) {
                    return;
                }

                [$clockIn, $clockOut] = $times;
                $this->upsertAttendance($user->id, $date, $clockIn, $clockOut);
            });
    }

    /**
     * 一般ユーザー向けのサンプル勤怠データを登録する。
     *
     * @param  User  $user
     * @return void
     */
    private function seedGeneralSampleData(User $user): void
    {
        $start = Carbon::now()->subMonthsNoOverflow(3)->startOfMonth();
        $end = Carbon::today();

        collect(CarbonPeriod::create($start, '1 day', $end))
            ->reject(fn (Carbon $date): bool => $date->isWeekend())
            ->reject(fn (Carbon $date): bool => $date->day > 15 && !$date->isToday())
            ->each(function (Carbon $date) use ($user): void {
                $clockOut = $date->isToday() ? null : '18:00:00';
                $this->upsertAttendance($user->id, $date, '09:00:00', $clockOut);
            });
    }

    /**
     * 指定した月の平日一覧をコレクションで取得する。
     *
     * @param  Carbon  $monthStart
     * @return Collection<int, Carbon>
     */
    private function getWeekdaysInMonth(Carbon $monthStart): Collection
    {
        $endOfMonth = $monthStart->copy()->endOfMonth();

        return collect(CarbonPeriod::create($monthStart, '1 day', $endOfMonth))
            ->filter(fn (Carbon $date): bool => !$date->isWeekend())
            ->values();
    }

    /**
     * ユーザーと日付をキーに勤怠レコードを作成または更新する。
     *
     * @param  int  $userId
     * @param  Carbon  $date
     * @param  string  $clockInTime
     * @param  string|null  $clockOutTime
     * @return void
     */
    private function upsertAttendance(int $userId, Carbon $date, string $clockInTime, ?string $clockOutTime): void
    {
        $dateStr = $date->toDateString();

        Attendance::updateOrCreate(
            [
                'user_id' => $userId,
                'date' => $dateStr,
            ],
            [
                'clock_in' => Carbon::parse("{$dateStr} {$clockInTime}"),
                'clock_out' => $clockOutTime ? Carbon::parse("{$dateStr} {$clockOutTime}") : null,
            ]
        );
    }
}