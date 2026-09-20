<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active',
        'batch', 'student_id', 'contact',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ---- Role helpers ----
    public function isAdmin(): bool     { return $this->role === 'ADMIN'; }
    public function isCreator(): bool   { return $this->role === 'TEST_CREATOR'; }
    public function isStaff(): bool     { return in_array($this->role, ['ADMIN', 'TEST_CREATOR'], true); }
    public function isCandidate(): bool { return $this->role === 'CANDIDATE'; }

    public function attempts(): HasMany     { return $this->hasMany(Attempt::class, 'candidate_id'); }
    public function assignments(): HasMany  { return $this->hasMany(TestAssignment::class); }
}
