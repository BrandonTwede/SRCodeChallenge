<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{

    protected $key = 'id';
    public $timestamps = false;

    public $table = "node";
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'level', 'lft', 'rgt'
    ];
}
