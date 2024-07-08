<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Game extends Model
{
    use HasFactory;

    protected $guarded = ['id'];


    public function user(){
        return $this->belongsTo(User::class, 'id');
    }

    public function versions(){
        return $this->hasMany(GameVersion::class);
    }

}
