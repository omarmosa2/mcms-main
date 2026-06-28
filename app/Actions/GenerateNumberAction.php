<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\NumberRange;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;

class GenerateNumberAction extends BaseAction
{
    public const ENTITY_PATIENT = 'patient';

    public const ENTITY_APPOINTMENT = 'appointment';

    public const ENTITY_INVOICE = 'invoice';

    public const ENTITY_PRESCRIPTION = 'prescription';

    public const ENTITY_PURCHASE_ORDER = 'purchase_order';

    public const ENTITY_EXPENSE = 'expense';

    public const ENTITY_SALARY = 'salary';

    public const ENTITY_VISIT = 'visit';

    /**
     * Generate a unique number for the given entity type.
     * Uses database locking to ensure thread-safety.
     */
    public function handle(int $clinicId, string $entityType, ?string $providedNumber = null): string|int
    {
        if ($providedNumber !== null && trim($providedNumber) !== '') {
            if ($entityType === self::ENTITY_PATIENT) {
                return (int) $providedNumber;
            }

            return trim($providedNumber);
        }

        return DB::transaction(function () use ($clinicId, $entityType): string|int {
            $numberRange = NumberRange::query()
                ->where('clinic_id', $clinicId)
                ->where('entity_type', $entityType)
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
                ->lockForUpdate()
                ->first();

            if ($numberRange === null) {
                $fallback = $this->generateFallbackNumber($clinicId, $entityType);

                return $entityType === self::ENTITY_PATIENT ? (int) $fallback : $fallback;
            }

            $number = $this->generateFromRange($numberRange);

            return $entityType === self::ENTITY_PATIENT ? (int) $number : $number;
        });
    }

    private function generateFromRange(NumberRange $numberRange): string
    {
        $sequence = $numberRange->generateSequence();
        $numberRange->save();

        return $numberRange->formatNumber($sequence);
    }

    private function generateFallbackNumber(int $clinicId, string $entityType): string
    {
        if ($entityType === self::ENTITY_PATIENT) {
            $maxFileNumber = (int) Patient::withTrashed()
                ->where('clinic_id', $clinicId)
                ->max('file_number');

            return (string) ($maxFileNumber + 1);
        }

        $prefix = match ($entityType) {
            self::ENTITY_PATIENT => 'MRN',
            self::ENTITY_APPOINTMENT => 'APT',
            self::ENTITY_VISIT => 'VIS',
            self::ENTITY_INVOICE => 'INV',
            self::ENTITY_PRESCRIPTION => 'RX',
            self::ENTITY_PURCHASE_ORDER => 'PO',
            self::ENTITY_EXPENSE => 'EXP',
            self::ENTITY_SALARY => 'SAL',
            default => strtoupper(substr($entityType, 0, 3)),
        };

        $today = now()->toDateString();
        $sequence = $this->getNextSequence($clinicId, $entityType, $today);

        return sprintf('%s-%s-%04d', $prefix, str_replace('-', '', $today), $sequence);
    }

    private function getNextSequence(int $clinicId, string $entityType, string $date): int
    {
        $model = match ($entityType) {
            self::ENTITY_PATIENT => Patient::class,
            self::ENTITY_APPOINTMENT => Appointment::class,
            self::ENTITY_INVOICE => Invoice::class,
            self::ENTITY_PRESCRIPTION => Prescription::class,
            default => null,
        };

        if (! $model) {
            return 1;
        }

        $column = match ($entityType) {
            self::ENTITY_PATIENT => 'file_number',
            self::ENTITY_APPOINTMENT => 'appointment_number',
            self::ENTITY_VISIT => 'visit_number',
            self::ENTITY_INVOICE => 'invoice_number',
            self::ENTITY_PRESCRIPTION => 'prescription_number',
            default => 'number',
        };

        $latest = $model::withTrashed()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where($column, 'like', '%'.str_replace('-', '', $date).'%')
            ->pluck($column)
            ->map(fn (mixed $number): int => $this->sequenceFromNumber($number))
            ->max() ?? 0;

        return $latest + 1;
    }

    private function sequenceFromNumber(mixed $number): int
    {
        if (is_int($number)) {
            return $number;
        }

        if (! is_string($number) || trim($number) === '') {
            return 0;
        }

        $lastDashPosition = strrpos($number, '-');
        $sequence = $lastDashPosition === false
            ? $number
            : substr($number, $lastDashPosition + 1);

        return ctype_digit($sequence) ? (int) $sequence : 0;
    }
}
