<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRateSnapshot extends Model
{
    protected $fillable = ['date', 'usd', 'eur'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'usd' => 'float',
        'eur' => 'float',
    ];
}
