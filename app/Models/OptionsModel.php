<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionsModel extends Model
{
    //
    protected $table = 'options';
    protected $fillable = ['label', 'value'];
}

