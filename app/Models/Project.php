<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => ProjectStatus::class,
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function progress(): array
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            return ['total' => 0, 'completed' => 0, 'percentage' => 0];
        }

        $completed = $this->tasks()->where('status', 'completed')->count();
        $percentage = round(($completed / $total) * 100);

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $percentage,
        ];
    }
}
