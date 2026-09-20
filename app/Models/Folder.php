<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    protected $fillable = ['name', 'created_by'];

    public function subjects(): HasMany { return $this->hasMany(Subject::class); }
}
