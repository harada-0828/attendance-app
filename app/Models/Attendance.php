<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    /**
     * 出退勤および休憩の状態から現在の勤務ステータスを判定して返す。
     *
     * @return string
     */
    public function getStatusAttribute(): string
    {
        if (!is_null($this->clock_out)) {
            return '退勤済';
        }

        /** @var Rest|null $latestRest */
        $latestRest = $this->relationLoaded('rests')
            ? $this->rests->sortByDesc('id')->first()
            : $this->rests()->latest('id')->first();

        if ($latestRest && is_null($latestRest->break_end)) {
            return '休憩中';
        }

        if (!is_null($this->clock_in)) {
            return '出勤中';
        }

        return '勤務外';
    }

    /**
     * 勤怠に紐づくユーザーを取得する。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 勤怠に紐づく休憩一覧を取得する。
     *
     * @return HasMany<Rest, $this>
     */
    public function rests(): HasMany
    {
        return $this->hasMany(Rest::class);
    }

    /**
     * 勤怠に紐づく修正申請一覧を取得する。
     *
     * @return HasMany<Request, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }
}