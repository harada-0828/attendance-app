<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
    ];


    /**
     * 時間のデータ状態から現在のステータスを動的に判定して返すアクセサ
     * （「勤務外」「出勤中」「休憩中」「退勤済」）
     */
    public function getStatusAttribute(): string
    {
        // 1. 退勤時間が記録されている場合
        if (!is_null($this->clock_out)) {
            return '退勤済';
        }

        // 2. 出勤中で、最後の休憩がまだ終了していない（break_endがnullの）場合
        $latestRest = $this->rests()->latest('id')->first();
        if ($latestRest && is_null($latestRest->break_end)) {
            return '休憩中';
        }

        // 3. 出勤時間が記録されていて、退勤しておらず、休憩中でもない場合
        if (!is_null($this->clock_in)) {
            return '出勤中';
        }

        // 4. それ以外
        return '勤務外';
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    
    public function rests(): HasMany
    {
        return $this->hasMany(Rest::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

}