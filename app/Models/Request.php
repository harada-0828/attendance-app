<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Request extends Model
{
    use HasFactory;

    public const STATUS_UNAPPLIED = false;

    public const STATUS_PENDING = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'attendance_id',
        'user_id',
        'requested_clock_in',
        'requested_clock_out',
        'reason',
        'approval_status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'approval_status' => 'boolean',
        'requested_clock_in' => 'datetime',
        'requested_clock_out' => 'datetime',
    ];

    /**
     * 修正申請が申請済み（承認待ち）かどうかを判定する。
     */
    public function isPending(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    /**
     * 修正申請に紐づく勤怠レコードを取得する。
     *
     * @return BelongsTo<Attendance, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * 修正申請を行ったユーザーを取得する。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 修正申請に紐づく休憩修正レコード一覧を取得する。
     *
     * @return HasMany<RequestRest, $this>
     */
    public function requestRests(): HasMany
    {
        return $this->hasMany(RequestRest::class);
    }
}
