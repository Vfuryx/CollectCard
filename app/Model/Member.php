<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'name','open_id','card1','card2',
        'card3','card4','card5','level'
    ];
}
