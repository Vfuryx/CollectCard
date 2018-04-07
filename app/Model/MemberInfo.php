<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class MemberInfo extends Model
{
    protected $fillable =[
        'uid','sex','city','province'
    ];
}
