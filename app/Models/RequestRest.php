<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestRest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'break_start',
        'break_end',
    ];

    
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}