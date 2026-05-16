<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplianceRun extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'ran_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function ranBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ran_by');
    }
}
