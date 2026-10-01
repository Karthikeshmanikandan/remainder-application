<?php

namespace App\Models;

use App\Enums\ProcessResponseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_id',
        'template_item_id',
        'question',
        'description',
        'response_type',
        'sort_order',
        'is_required',
        'is_enabled',
    ];

    protected $casts = [
        'response_type' => ProcessResponseType::class,
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplateItem::class, 'template_item_id');
    }

    public function executionItems(): HasMany
    {
        return $this->hasMany(ProcessExecutionItem::class);
    }
}
