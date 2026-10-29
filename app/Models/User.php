<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // --- ロール定義 ---
    const ROLE_ADMIN = 0;   // 管理者
    const ROLE_USER = 1;    // 一般ユーザー

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed', // パスワードの自動ハッシュ化
        ];
    }

    /**
     * ロール番号を人間が読みやすい文字列に変換するアクセサ
     */
    protected function roleName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['role'] === self::ROLE_ADMIN ? '管理者' : '一般ユーザー'
        );
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }
}