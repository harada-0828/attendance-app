<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasFactory;

    /**
     * 既存Web版と共通の勤怠テーブルを使用する。
     *
     * @var string
     */
    protected $table = 'attendances';

    /**
     * 一括代入を許可する属性。
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
    ];

    /**
     * 属性のキャスト設定。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    /**
     * 勤怠レコードに紐づくユーザーを取得する。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 勤怠レコードに紐づく休憩一覧を取得する。
     *
     * @return HasMany<Rest, $this>
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(Rest::class, 'attendance_id');
    }

    /**
     * 勤怠レコードに紐づく修正申請一覧を取得する。
     *
     * @return HasMany<Request, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Request::class, 'attendance_id');
    }

    /**
     * 合計休憩時間を算出して返す。
     */
    public function getTotalBreakTimeAttribute(): string
    {
        $rests = $this->relationLoaded('breaks')
            ? $this->breaks
            : $this->breaks()->get();

        /** @var int $totalMinutes */
        $totalMinutes = $rests
            ->filter(fn (Rest $rest): bool => $rest->break_start !== null && $rest->break_end !== null)
            ->sum(fn (Rest $rest): int => Carbon::parse($rest->break_start)
                ->diffInMinutes(Carbon::parse($rest->break_end)));

        return sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
    }

    /**
     * 実働合計時間を算出して返す。
     */
    public function getTotalTimeAttribute(): string
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return '00:00';
        }

        $rests = $this->relationLoaded('breaks')
            ? $this->breaks
            : $this->breaks()->get();

        /** @var int $totalBreakMinutes */
        $totalBreakMinutes = $rests
            ->filter(fn (Rest $rest): bool => $rest->break_start !== null && $rest->break_end !== null)
            ->sum(fn (Rest $rest): int => Carbon::parse($rest->break_start)
                ->diffInMinutes(Carbon::parse($rest->break_end)));

        $workMinutes = Carbon::parse($this->clock_in)
            ->diffInMinutes(Carbon::parse($this->clock_out));
        $netWorkMinutes = max(0, $workMinutes - $totalBreakMinutes);

        return sprintf('%02d:%02d', intdiv($netWorkMinutes, 60), $netWorkMinutes % 60);
    }

    /**
     * 最新の修正申請理由（reason）を備考（comment）として取得する。
     */
    public function getCommentAttribute(): ?string
    {
        if ($this->relationLoaded('applications')) {
            /** @var Request|null $latestApplication */
            $latestApplication = $this->applications->sortByDesc('id')->first();

            return $latestApplication?->reason;
        }

        /** @var Request|null $latestApplication */
        $latestApplication = $this->applications()->latest('id')->first();

        return $latestApplication?->reason;
    }
}
