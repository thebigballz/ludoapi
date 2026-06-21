<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'avatar',
        'kyc_status',
        'is_banned',
        'is_admin',
    ];
    

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'   => 'hashed',
            'is_banned'  => 'boolean',
            'is_admin'  => 'boolean',
        ];
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
}