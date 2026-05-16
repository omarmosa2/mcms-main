<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabResult extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'resulted_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resulted_by');
    }
}
