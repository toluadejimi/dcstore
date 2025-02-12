<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWallet extends Model
{
    protected $table = 'user_wallets';
    
    protected $fillable = [
        'user_id', 'balance', 'created_at', 'updated_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function totalWalletBalance()
    {
        return UserWallet::sum('balance');
    }
     
    // public function history()
    // {
    //     return $this->hasMany(WalletHistory::class, 'id', 'wallet_id');
    // }
}