<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameVersion extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function game(){
        return $this->belongsTo(Game::class, 'id');
    }

    public function scores(){
        return $this->hasMany(Scor::class, 'game_version_id');
    }
}
