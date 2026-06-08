<?php

namespace App\Http\Controllers\MedicalRecords;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MedicalRecordExportController extends Controller
{
    public function export(Request $request, int $recordId): Response
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        $record = MedicalRecord::query()
            ->forClinic((int) $clinicId)
            ->with([
                'patient:id,clinic_id,first_name,last_name,file_number,phone,date_of_birth,gender',
                'department:id,clinic_id,name,clinic_type',
                'doctor:id,clinic_id,name',
                'creator:id,clinic_id,name',
                'treatmentPlans',
                'treatmentPlans.doctor:id,clinic_id,name',
                'followUps',
                'followUps.doctor:id,clinic_id,name',
            ])
            ->whereKey($recordId)
            ->firstOrFail();

        $pdf = Pdf::loadView('exports.medical-record', [
            'record' => $record,
            'clinicTypeLabels' => [
                'internal_medicine' => 'باطنية',
                'pediatrics' => 'أطفال',
                'gynecology' => 'نسائية وتوليد',
                'orthopedics' => 'عظام',
                'dermatology' => 'جلدية',
                'ophthalmology' => 'عيون',
                'ent' => 'أنف وأذن وحنجرة',
                'cardiology' => 'قلب',
                'neurology' => 'أعصاب',
                'psychiatry' => 'نفسية',
                'general_surgery' => 'جراحة عامة',
                'urology' => 'مسالك بولية',
                'dental' => 'أسنان',
                'other' => 'أخرى',
            ],
            'statusLabels' => [
                'draft' => 'مسودة',
                'active' => 'نشط',
                'completed' => 'مكتمل',
                'cancelled' => 'ملغي',
                'new' => 'جديد',
                'in_progress' => 'قيد التنفيذ',
                'scheduled' => 'مجدول',
                'missed' => 'فائت',
            ],
        ]);

        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $fileName = sprintf(
            'medical-record-%s-%s.pdf',
            $record->record_number,
            now()->format('Y-m-d'),
        );

        return $pdf->download($fileName);
    }
}
