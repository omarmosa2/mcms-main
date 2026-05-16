<?php

namespace App\Http\Controllers\Diagnostics;

use App\Actions\Diagnostics\CreateLabTestTemplateAction;
use App\Actions\Diagnostics\CreateRadiologyStudyTypeAction;
use App\Actions\Diagnostics\ListLabTestTemplatesAction;
use App\Actions\Diagnostics\ListRadiologyStudyTypesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DiagnosticsController extends Controller
{
    public function __construct(
        private CreateLabTestTemplateAction $createLabTestTemplateAction,
        private ListLabTestTemplatesAction $listLabTestTemplatesAction,
        private CreateRadiologyStudyTypeAction $createRadiologyStudyTypeAction,
        private ListRadiologyStudyTypesAction $listRadiologyStudyTypesAction,
    ) {}

    public function labTemplates(Request $request): JsonResponse|InertiaResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $isActive = $request->get('is_active');
        $category = $request->get('category');

        $templates = $this->listLabTestTemplatesAction->handle(
            clinicId: $clinicId,
            perPage: $perPage,
            isActive: $isActive !== null ? (bool) $isActive : null,
            category: $category,
            search: $search,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $templates]);
        }

        return Inertia::render('diagnostics/LabTemplates/Index', [
            'templates' => $templates,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'is_active' => $isActive,
                'category' => $category,
            ],
        ]);
    }

    public function storeLabTemplate(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:lab_test_templates,code'],
            'category' => ['nullable', 'string'],
            'unit' => ['nullable', 'string'],
            'min_reference' => ['nullable', 'numeric'],
            'max_reference' => ['nullable', 'numeric'],
        ]);

        $template = $this->createLabTestTemplateAction->handle(
            clinicId: $clinicId,
            name: $validated['name'],
            code: $validated['code'],
            category: $validated['category'] ?? null,
            unit: $validated['unit'] ?? null,
            minReference: $validated['min_reference'] ?? null,
            maxReference: $validated['max_reference'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $template], 201);
        }

        return redirect()->back()->with('toast', ['message' => 'Lab test template created.', 'type' => 'success']);
    }

    public function radiologyStudyTypes(Request $request): JsonResponse|InertiaResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $isActive = $request->get('is_active');

        $studyTypes = $this->listRadiologyStudyTypesAction->handle(
            clinicId: $clinicId,
            perPage: $perPage,
            isActive: $isActive !== null ? (bool) $isActive : null,
            search: $search,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $studyTypes]);
        }

        return Inertia::render('diagnostics/RadiologyStudyTypes/Index', [
            'studyTypes' => $studyTypes,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'is_active' => $isActive,
            ],
        ]);
    }

    public function storeRadiologyStudyType(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:radiology_study_types,code'],
            'description' => ['nullable', 'string'],
            'requires_contrast' => ['boolean'],
        ]);

        $studyType = $this->createRadiologyStudyTypeAction->handle(
            clinicId: $clinicId,
            name: $validated['name'],
            code: $validated['code'],
            description: $validated['description'] ?? null,
            requiresContrast: $validated['requires_contrast'] ?? false,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $studyType], 201);
        }

        return redirect()->back()->with('toast', ['message' => 'Radiology study type created.', 'type' => 'success']);
    }
}
