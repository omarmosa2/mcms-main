<?php

namespace App\Actions\Patients;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Patient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeletePatientAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $patientId, int $userId): void
    {
        $patient = Patient::query()
            ->forClinic($clinicId)
            ->findOrFail($patientId);

        $oldValues = $patient->only([
            'file_number',
            'first_name',
            'last_name',
            'phone',
            'email',
        ]);

        $attachmentFiles = $patient->attachments()
            ->where('clinic_id', $clinicId)
            ->get(['disk', 'path'])
            ->map(fn ($attachment): array => [
                'disk' => (string) $attachment->disk,
                'path' => (string) $attachment->path,
            ]);

        DB::transaction(function () use ($clinicId, $patientId, $patient): void {
            $invoiceIds = DB::table('invoices')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->pluck('id');

            $appointmentIds = DB::table('appointments')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->pluck('id');

            $medicalRecordIds = DB::table('medical_records')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->pluck('id');

            $prescriptionIds = DB::table('prescriptions')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->pluck('id');

            $labOrderIds = DB::table('lab_orders')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->pluck('id');

            $radiologyOrderIds = DB::table('radiology_orders')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->pluck('id');

            $this->deleteWhereIn('installments', 'invoice_id', $invoiceIds);
            $this->deleteWhereIn('payments', 'invoice_id', $invoiceIds);
            $this->deleteWhereIn('invoice_items', 'invoice_id', $invoiceIds);
            $this->deleteWhereIn('invoices', 'id', $invoiceIds);

            $this->deleteWhereIn('prescription_items', 'prescription_id', $prescriptionIds);
            $this->deleteWhereIn('pharmacy_dispenses', 'prescription_id', $prescriptionIds);
            $this->deleteWhereIn('prescriptions', 'id', $prescriptionIds);

            $this->deleteWhereIn('lab_results', 'lab_order_id', $labOrderIds);
            $this->deleteWhereIn('lab_orders', 'id', $labOrderIds);

            $this->deleteWhereIn('radiology_reports', 'radiology_order_id', $radiologyOrderIds);
            $this->deleteWhereIn('radiology_images', 'radiology_order_id', $radiologyOrderIds);
            $this->deleteWhereIn('radiology_orders', 'id', $radiologyOrderIds);

            $this->deletePatientMedicalRecordDependents('treatment_plans', $clinicId, $patientId, $medicalRecordIds);
            $this->deletePatientMedicalRecordDependents('follow_ups', $clinicId, $patientId, $medicalRecordIds);
            $this->deleteWhereIn('medical_records', 'id', $medicalRecordIds);

            $this->deleteWhereIn('appointment_reminders', 'appointment_id', $appointmentIds);
            $this->deleteWhereIn('doctor_appointment_entitlements', 'appointment_id', $appointmentIds);
            $this->deleteWhereIn('appointments', 'id', $appointmentIds);

            DB::table('patient_chronic_conditions')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->delete();
            DB::table('patient_allergies')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->delete();
            DB::table('patient_medications')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->delete();
            DB::table('patient_attachments')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->delete();
            DB::table('patient_portal_tokens')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->delete();
            DB::table('sensitive_access_logs')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->delete();

            $patient->forceDelete();
        });

        $attachmentFiles->each(
            fn (array $file): bool => Storage::disk($file['disk'])->delete($file['path'])
        );

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'patients.delete',
            auditable: $patient,
            oldValues: $oldValues,
        );
    }

    /**
     * @param  Collection<int, mixed>  $ids
     */
    private function deleteWhereIn(string $table, string $column, Collection $ids): void
    {
        if ($ids->isEmpty()) {
            return;
        }

        DB::table($table)
            ->whereIn($column, $ids)
            ->delete();
    }

    /**
     * @param  Collection<int, mixed>  $medicalRecordIds
     */
    private function deletePatientMedicalRecordDependents(
        string $table,
        int $clinicId,
        int $patientId,
        Collection $medicalRecordIds,
    ): void {
        DB::table($table)
            ->where('clinic_id', $clinicId)
            ->where(function ($query) use ($patientId, $medicalRecordIds): void {
                $query->where('patient_id', $patientId);

                if ($medicalRecordIds->isNotEmpty()) {
                    $query->orWhereIn('medical_record_id', $medicalRecordIds);
                }
            })
            ->delete();
    }
}
