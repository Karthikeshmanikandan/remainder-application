<?php

namespace App\Models;

use App\Enums\ProcessResponseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_template_id',
        'question',
        'description',
        'response_type',
        'sort_order',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'response_type' => ProcessResponseType::class,
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class);
    }
}
