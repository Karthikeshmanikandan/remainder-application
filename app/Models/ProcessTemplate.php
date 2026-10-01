<?php

namespace App\Models;

use App\Enums\ProcessFrequency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'department_id',
        'name',
        'code',
        'description',
        'frequency_default',
        'is_active',
        'is_system_template',
    ];

    protected $casts = [
        'frequency_default' => ProcessFrequency::class,
        'is_active' => 'boolean',
        'is_system_template' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcessTemplateItem::class)->orderBy('sort_order');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class);
    }
}
