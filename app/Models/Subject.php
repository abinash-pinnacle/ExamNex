<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = ['folder_id', 'name'];

    public function folder(): BelongsTo { return $this->belongsTo(Folder::class); }
    public function topics(): HasMany   { return $this->hasMany(Topic::class); }
}
