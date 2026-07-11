<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetData extends Model
{
    protected $table = 'budget_data';

    protected $fillable = [
        'user_id',
        'key',
        'period',
        'value',
    ];
}
