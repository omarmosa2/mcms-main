<?php

namespace App\Actions\Diagnostics;

use App\Actions\BaseAction;
use App\Models\RadiologyStudyType;

class CreateRadiologyStudyTypeAction extends BaseAction
{
    public function handle(
        int $clinicId,
        string $name,
        string $code,
        ?string $description = null,
        bool $requiresContrast = false,
    ): RadiologyStudyType {
        return RadiologyStudyType::query()->create([
            'clinic_id' => $clinicId,
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'requires_contrast' => $requiresContrast,
            'is_active' => true,
        ]);
    }
}
