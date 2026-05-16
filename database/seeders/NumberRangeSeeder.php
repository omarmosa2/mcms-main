<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\NumberRange;
use Illuminate\Database\Seeder;

class NumberRangeSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = Clinic::all();

        foreach ($clinics as $clinic) {
            $ranges = [
                [
                    'entity_type' => 'patient',
                    'prefix' => 'MRN',
                    'current_sequence' => 0,
                    'format_pattern' => 'MRN-YYYYMMDD-0000',
                    'is_active' => true,
                ],
                [
                    'entity_type' => 'appointment',
                    'prefix' => 'APT',
                    'current_sequence' => 0,
                    'format_pattern' => 'APT-YYYYMMDD-0000',
                    'is_active' => true,
                ],
                [
                    'entity_type' => 'visit',
                    'prefix' => 'VIS',
                    'current_sequence' => 0,
                    'format_pattern' => 'VIS-YYYYMMDD-0000',
                    'is_active' => true,
                ],
                [
                    'entity_type' => 'invoice',
                    'prefix' => 'INV',
                    'current_sequence' => 0,
                    'format_pattern' => 'INV-YYYYMMDD-0000',
                    'is_active' => true,
                ],
                [
                    'entity_type' => 'prescription',
                    'prefix' => 'RX',
                    'current_sequence' => 0,
                    'format_pattern' => 'RX-YYYYMMDD-0000',
                    'is_active' => true,
                ],
            ];

            foreach ($ranges as $range) {
                NumberRange::updateOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'entity_type' => $range['entity_type'],
                    ],
                    $range,
                );
            }
        }
    }
}
