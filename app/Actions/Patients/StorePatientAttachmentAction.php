<?php

namespace App\Actions\Patients;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Patient;
use App\Models\PatientAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorePatientAttachmentAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $patientId, int $userId, UploadedFile $file): PatientAttachment
    {
        $patient = Patient::query()
            ->forClinic($clinicId)
            ->findOrFail($patientId);

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $safeExtension = $extension !== '' ? $extension : null;
        $storedName = (string) Str::uuid();

        if ($safeExtension !== null) {
            $storedName .= '.'.$safeExtension;
        }

        $directory = sprintf('patients/%d/attachments', $patient->id);
        $storedPath = Storage::disk('local')->putFileAs($directory, $file, $storedName);

        if ($storedPath === false) {
            abort(500, 'Unable to store attachment.');
        }

        $attachment = PatientAttachment::query()->create([
            'clinic_id' => $clinicId,
            'patient_id' => $patient->id,
            'uploaded_by' => $userId,
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'extension' => $safeExtension,
            'size_bytes' => (int) $file->getSize(),
            'uploaded_at' => now(),
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'patients.attachment.store',
            auditable: $attachment,
            metadata: [
                'patient_id' => $patient->id,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
            ],
        );

        return $attachment;
    }
}
