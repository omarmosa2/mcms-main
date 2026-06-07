<?php

namespace App\Actions\Patients;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Actions\Compliance\LogSensitiveAccessAction;
use App\Models\Patient;

class ShowPatientAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private LogSensitiveAccessAction $logSensitiveAccessAction,
    ) {}

    public function handle(int $clinicId, int $patientId, int $userId, ?string $accessReason = null): Patient
    {
        $patient = Patient::query()
            ->forClinic($clinicId)
            ->with([
                'chronicConditions:id,clinic_id,patient_id,condition',
                'allergies:id,clinic_id,patient_id,allergy',
                'medications:id,clinic_id,patient_id,medication',
                'attachments:id,clinic_id,patient_id,uploaded_by,disk,path,original_name,mime_type,extension,size_bytes,uploaded_at,created_at',
                'attachments.uploader:id,name,email',
                'appointments' => function ($builder) use ($clinicId): void {
                    $builder
                        ->forClinic($clinicId)
                        ->select([
                            'id',
                            'clinic_id',
                            'patient_id',
                            'doctor_id',
                            'status',
                            'scheduled_for',
                            'duration_minutes',
                            'notes',
                            'created_at',
                        ])
                        ->with('doctor:id,name')
                        ->orderByDesc('scheduled_for')
                        ->limit(20);
                },
                'invoices' => function ($builder) use ($clinicId): void {
                    $builder
                        ->forClinic($clinicId)
                        ->select([
                            'id',
                            'clinic_id',
                            'patient_id',
                            'visit_id',
                            'appointment_id',
                            'status',
                            'subtotal_amount',
                            'discount_amount',
                            'tax_amount',
                            'total_amount',
                            'paid_amount',
                            'balance_amount',
                            'issued_at',
                            'created_at',
                        ])
                        ->orderByDesc('created_at')
                        ->limit(20);
                },
                'prescriptions' => function ($builder) use ($clinicId): void {
                    $builder
                        ->forClinic($clinicId)
                        ->select([
                            'id',
                            'clinic_id',
                            'patient_id',
                            'visit_id',
                            'prescribed_by',
                            'status',
                            'notes',
                            'issued_at',
                            'dispensed_at',
                            'created_at',
                        ])
                        ->with('prescriber:id,name')
                        ->orderByDesc('created_at')
                        ->limit(20);
                },
                'labOrders' => function ($builder) use ($clinicId): void {
                    $builder
                        ->forClinic($clinicId)
                        ->select([
                            'id',
                            'clinic_id',
                            'patient_id',
                            'visit_id',
                            'ordered_by',
                            'status',
                            'priority',
                            'notes',
                            'ordered_at',
                            'completed_at',
                            'created_at',
                        ])
                        ->with('orderer:id,name')
                        ->orderByDesc('created_at')
                        ->limit(20);
                },
                'radiologyOrders' => function ($builder) use ($clinicId): void {
                    $builder
                        ->forClinic($clinicId)
                        ->select([
                            'id',
                            'clinic_id',
                            'patient_id',
                            'visit_id',
                            'ordered_by',
                            'status',
                            'priority',
                            'notes',
                            'ordered_at',
                            'completed_at',
                            'created_at',
                        ])
                        ->with('orderer:id,name')
                        ->orderByDesc('created_at')
                        ->limit(20);
                },
            ])
            ->findOrFail($patientId);

        $this->logSensitiveAccessAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            resourceType: 'patient',
            resourceId: (int) $patient->id,
            patientId: (int) $patient->id,
            reason: $accessReason,
            context: [
                'source' => 'patients.show',
            ],
        );

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'patients.show',
            auditable: $patient,
        );

        return $patient;
    }
}
