<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size_bytes' => $this->size_bytes,
            'uploaded_at' => $this->uploaded_at?->toISOString() ?? $this->created_at?->toISOString(),
            'uploaded_by' => $this->whenLoaded('uploader', fn (): ?array => $this->uploader !== null ? [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
                'email' => $this->uploader->email,
            ] : null),
            'download_url' => route('patients.attachments.download', [
                'patientId' => $this->patient_id,
                'attachmentId' => $this->id,
            ]),
        ];
    }
}
