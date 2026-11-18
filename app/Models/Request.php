<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Request extends Model
{
    use HasFactory;

    // ステータス用の定数定義（0:false: , 1:true: ）
    const STATUS_UNAPPLIED = false;  // 未申請/申請なし（「修正」ボタン表示）
    const STATUS_PENDING = true;     // 申請済み / 承認待ち（修正不可メッセージ表示）

    protected $fillable = [
        'attendance_id',
        'user_id',
        'requested_clock_in',
        'requested_clock_out',
        'reason',
        'approval_status',
    ];

    protected $casts = [
        'approval_status' => 'boolean', 
        'requested_clock_in' => 'datetime',
        'requested_clock_out' => 'datetime',
    ];

    /**
     * 申請済み（承認待ち）かどうかを判定するメソッド
     */
    public function isPending(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestRests(): HasMany
    {
        return $this->hasMany(RequestRest::class);
    }
}