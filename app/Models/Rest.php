<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'break_start',
        'break_end',
    ];

    /**
     * 休憩が終了しているかどうかを返すアクセサ
     */
    public function getIsFinishedAttribute(): bool
    {
        return !is_null($this->break_end);
    }

    
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}