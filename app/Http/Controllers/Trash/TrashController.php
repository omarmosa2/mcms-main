<?php

namespace App\Http\Controllers\Trash;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\DrugBatch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\LabTestTemplate;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientAttachment;
use App\Models\PatientChronicCondition;
use App\Models\PatientMedication;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\PharmacyDrug;
use App\Models\Prescription;
use App\Models\RadiologyOrder;
use App\Models\RadiologyReport;
use App\Models\RadiologyStudyType;
use App\Models\Salary;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TrashController extends Controller
{
    private const SOFT_DELETABLE_MODELS = [
        'patients' => [
            'model' => Patient::class,
            'label' => 'مرضى',
            'name_column' => 'first_name',
            'number_column' => 'file_number',
        ],
        'appointments' => [
            'model' => Appointment::class,
            'label' => 'مواعيد',
            'name_column' => 'appointment_number',
            'number_column' => null,
        ],
        'invoices' => [
            'model' => Invoice::class,
            'label' => 'فواتير',
            'name_column' => 'invoice_number',
            'number_column' => null,
        ],
        'invoice_items' => [
            'model' => InvoiceItem::class,
            'label' => 'بنود فواتير',
            'name_column' => 'description',
            'number_column' => null,
        ],
        'payments' => [
            'model' => Payment::class,
            'label' => 'مدفوعات',
            'name_column' => 'payment_number',
            'number_column' => null,
        ],
        'doctor_profiles' => [
            'model' => DoctorProfile::class,
            'label' => 'ملفات أطباء',
            'name_column' => 'specialization',
            'number_column' => null,
        ],
        'doctor_schedules' => [
            'model' => DoctorSchedule::class,
            'label' => 'جداول أطباء',
            'name_column' => 'id',
            'number_column' => null,
        ],
        'users' => [
            'model' => User::class,
            'label' => 'مستخدمون',
            'name_column' => 'name',
            'number_column' => null,
        ],
        'patient_allergies' => [
            'model' => PatientAllergy::class,
            'label' => 'حساسيات',
            'name_column' => 'allergy_name',
            'number_column' => null,
        ],
        'patient_medications' => [
            'model' => PatientMedication::class,
            'label' => 'أدوية',
            'name_column' => 'medication_name',
            'number_column' => null,
        ],
        'patient_chronic_conditions' => [
            'model' => PatientChronicCondition::class,
            'label' => 'أمراض مزمنة',
            'name_column' => 'condition_name',
            'number_column' => null,
        ],
        'patient_attachments' => [
            'model' => PatientAttachment::class,
            'label' => 'مرفقات',
            'name_column' => 'file_name',
            'number_column' => null,
        ],
        'lab_orders' => [
            'model' => LabOrder::class,
            'label' => 'طلبات مختبر',
            'name_column' => 'id',
            'number_column' => null,
        ],
        'lab_results' => [
            'model' => LabResult::class,
            'label' => 'نتائج مختبر',
            'name_column' => 'id',
            'number_column' => null,
        ],
        'lab_test_templates' => [
            'model' => LabTestTemplate::class,
            'label' => 'قوالب مختبر',
            'name_column' => 'name',
            'number_column' => null,
        ],
        'radiology_orders' => [
            'model' => RadiologyOrder::class,
            'label' => 'طلبات أشعة',
            'name_column' => 'id',
            'number_column' => null,
        ],
        'radiology_reports' => [
            'model' => RadiologyReport::class,
            'label' => 'تقارير أشعة',
            'name_column' => 'id',
            'number_column' => null,
        ],
        'radiology_study_types' => [
            'model' => RadiologyStudyType::class,
            'label' => 'أنواع أشعة',
            'name_column' => 'name',
            'number_column' => null,
        ],
        'prescriptions' => [
            'model' => Prescription::class,
            'label' => 'وصفات طبية',
            'name_column' => 'id',
            'number_column' => null,
        ],
        'pharmacy_drugs' => [
            'model' => PharmacyDrug::class,
            'label' => 'أدوية صيدلية',
            'name_column' => 'name',
            'number_column' => null,
        ],
        'drug_batches' => [
            'model' => DrugBatch::class,
            'label' => 'دفعات أدوية',
            'name_column' => 'batch_number',
            'number_column' => null,
        ],
        'suppliers' => [
            'model' => Supplier::class,
            'label' => 'موردون',
            'name_column' => 'name',
            'number_column' => null,
        ],
        'payment_plans' => [
            'model' => PaymentPlan::class,
            'label' => 'خطط دفع',
            'name_column' => 'name',
            'number_column' => null,
        ],
        'installments' => [
            'model' => Installment::class,
            'label' => 'أقساط',
            'name_column' => 'id',
            'number_column' => null,
        ],
        'expenses' => [
            'model' => Expense::class,
            'label' => 'مصروفات',
            'name_column' => 'description',
            'number_column' => null,
        ],
        'expense_categories' => [
            'model' => ExpenseCategory::class,
            'label' => 'أقسام مصروفات',
            'name_column' => 'name',
            'number_column' => null,
        ],
        'salaries' => [
            'model' => Salary::class,
            'label' => 'رواتب',
            'name_column' => 'id',
            'number_column' => null,
        ],
    ];

    public function index(Request $request): InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $trashData = [];

        foreach (self::SOFT_DELETABLE_MODELS as $table => $config) {
            $model = $config['model'];
            $count = $model::query()
                ->where('clinic_id', $clinicId)
                ->onlyTrashed()
                ->count();

            $items = $model::query()
                ->where('clinic_id', $clinicId)
                ->onlyTrashed()
                ->latest('deleted_at')
                ->limit(10)
                ->get()
                ->map(function ($item) use ($config) {
                    return [
                        'id' => $item->id,
                        'name' => $config['name_column'] ? $item->{$config['name_column']} : (string) $item->id,
                        'number' => $config['number_column'] ? $item->{$config['number_column']} : null,
                        'deleted_at' => $item->deleted_at?->format('Y-m-d H:i'),
                    ];
                });

            $trashData[$table] = [
                'label' => $config['label'],
                'count' => $count,
                'items' => $items,
            ];
        }

        return Inertia::render('trash/Index', [
            'trashData' => $trashData,
        ]);
    }

    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $config = self::SOFT_DELETABLE_MODELS[$type] ?? null;

        if ($config === null) {
            abort(404);
        }

        $model = $config['model'];
        $item = $model::query()
            ->where('clinic_id', $clinicId)
            ->onlyTrashed()
            ->find($id);

        if ($item === null) {
            abort(404);
        }

        $item->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم استرجاع العنصر بنجاح.']);

        return to_route('trash.index');
    }

    public function forceDelete(Request $request, string $type, int $id): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $config = self::SOFT_DELETABLE_MODELS[$type] ?? null;

        if ($config === null) {
            abort(404);
        }

        $model = $config['model'];
        $item = $model::query()
            ->where('clinic_id', $clinicId)
            ->onlyTrashed()
            ->find($id);

        if ($item === null) {
            abort(404);
        }

        $item->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حذف العنصر نهائياً.']);

        return to_route('trash.index');
    }

    public function emptyTrash(Request $request, string $type): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $config = self::SOFT_DELETABLE_MODELS[$type] ?? null;

        if ($config === null) {
            abort(404);
        }

        $model = $config['model'];
        $model::query()
            ->where('clinic_id', $clinicId)
            ->onlyTrashed()
            ->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم إفراغ سلة المحذوفات.']);

        return to_route('trash.index');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(403, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
