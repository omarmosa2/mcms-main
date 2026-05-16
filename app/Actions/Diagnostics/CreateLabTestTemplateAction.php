<?php

namespace App\Actions\Diagnostics;

use App\Actions\BaseAction;
use App\Models\LabTestTemplate;

class CreateLabTestTemplateAction extends BaseAction
{
    public function handle(
        int $clinicId,
        string $name,
        string $code,
        ?string $category = null,
        ?string $unit = null,
        ?float $minReference = null,
        ?float $maxReference = null,
    ): LabTestTemplate {
        return LabTestTemplate::query()->create([
            'clinic_id' => $clinicId,
            'name' => $name,
            'code' => $code,
            'category' => $category,
            'unit' => $unit,
            'min_reference' => $minReference,
            'max_reference' => $maxReference,
            'is_active' => true,
        ]);
    }
}
