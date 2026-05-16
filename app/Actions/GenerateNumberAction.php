<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\NumberRange;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class GenerateNumberAction extends BaseAction
{
    public const ENTITY_PATIENT = 'patient';

    public const ENTITY_APPOINTMENT = 'appointment';

    public const ENTITY_VISIT = 'visit';

    public const ENTITY_INVOICE = 'invoice';

    public const ENTITY_PRESCRIPTION = 'prescription';

    public const ENTITY_PURCHASE_ORDER = 'purchase_order';

    public const ENTITY_EXPENSE = 'expense';

    public const ENTITY_SALARY = 'salary';

    /**
     * Generate a unique number for the given entity type.
     * Uses database locking to ensure thread-safety.
     */
    public function handle(int $clinicId, string $entityType, ?string $providedNumber = null): string
    {
        if ($providedNumber !== null && trim($providedNumber) !== '') {
            return trim($providedNumber);
        }

        $numberRange = NumberRange::getForEntity($clinicId, $entityType);

        if (! $numberRange) {
            return $this->generateFallbackNumber($entityType);
        }

        return DB::transaction(fn () => $this->generateFromRange($numberRange));
    }

    private function generateFromRange(NumberRange $numberRange): string
    {
        $numberRange->lockForUpdate()->find($numberRange->id);

        $sequence = $numberRange->generateSequence();
        $numberRange->save();

        return $numberRange->formatNumber($sequence);
    }

    private function generateFallbackNumber(string $entityType): string
    {
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
        $sequence = $this->getNextSequence($entityType, $today);

        return sprintf('%s-%s-%04d', $prefix, str_replace('-', '', $today), $sequence);
    }

    private function getNextSequence(string $entityType, string $date): int
    {
        $model = match ($entityType) {
            self::ENTITY_PATIENT => Patient::class,
            self::ENTITY_APPOINTMENT => Appointment::class,
            self::ENTITY_VISIT => Visit::class,
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

        return (int) $model::query()
            ->where('created_at', '>=', $date)
            ->count() + 1;
    }
}
