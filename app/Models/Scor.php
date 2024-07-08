<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scor extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'scores';

    public function version(){
        return $this->belongsTo(GameVersion::class, 'id');
    }
}
