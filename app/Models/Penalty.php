<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'penalties';

    public function user() {
        return $this->belongsTo(User::class);
    }
}
