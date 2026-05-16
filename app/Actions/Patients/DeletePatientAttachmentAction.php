<?php

namespace App\Actions\Patients;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Patient;
use Illuminate\Support\Facades\Storage;

class DeletePatientAttachmentAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $patientId, int $attachmentId, int $userId): void
    {
        $patient = Patient::query()
            ->forClinic($clinicId)
            ->findOrFail($patientId);

        $attachment = $patient->attachments()
            ->where('clinic_id', $clinicId)
            ->findOrFail($attachmentId);

        $oldValues = $attachment->only([
            'patient_id',
            'disk',
            'path',
            'original_name',
            'mime_type',
            'size_bytes',
        ]);

        Storage::disk((string) $attachment->disk)->delete((string) $attachment->path);
        $attachment->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'patients.attachment.delete',
            auditable: $attachment,
            oldValues: $oldValues,
        );
    }
}
