<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function processTemplates(): HasMany
    {
        return $this->hasMany(ProcessTemplate::class);
    }

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class);
    }

    public function telegramEmployees(): HasMany
    {
        return $this->hasMany(TelegramEmployee::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
