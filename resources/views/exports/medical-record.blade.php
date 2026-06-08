<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>أضبارة المريض - {{ $record->patient->first_name }} {{ $record->patient->last_name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Amiri', 'DejaVu Sans', serif;
            font-size: 12px;
            line-height: 1.6;
            color: #1a1a1a;
            direction: rtl;
        }

        .container {
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #0ea5e9;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 22px;
            color: #0ea5e9;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 11px;
            color: #666;
        }

        .section {
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }

        .section-header {
            background: #f0f9ff;
            padding: 10px 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .section-header h2 {
            font-size: 14px;
            color: #0369a1;
            font-weight: bold;
        }

        .section-body {
            padding: 15px;
        }

        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .info-row {
            display: table-row;
        }

        .info-cell {
            display: table-cell;
            padding: 8px 10px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        .info-label {
            font-weight: bold;
            color: #4b5563;
            width: 35%;
            background: #f9fafb;
        }

        .info-value {
            color: #111827;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }

        .badge-draft { background: #f3f4f6; color: #4b5563; }
        .badge-active { background: #dbeafe; color: #1d4ed8; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .badge-new { background: #dbeafe; color: #1d4ed8; }
        .badge-in_progress { background: #fef3c7; color: #92400e; }
        .badge-scheduled { background: #dbeafe; color: #1d4ed8; }
        .badge-missed { background: #fee2e2; color: #991b1b; }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.data-table th {
            background: #f0f9ff;
            padding: 8px 10px;
            text-align: right;
            font-size: 11px;
            color: #0369a1;
            border-bottom: 2px solid #0ea5e9;
        }

        table.data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }

        .text-muted {
            color: #6b7280;
        }

        .text-small {
            font-size: 10px;
        }

        .whitespace-pre {
            white-space: pre-wrap;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #9ca3af;
        }

        .page-break {
            page-break-after: always;
        }

        .no-break {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>أضبارة المريض الطبية</h1>
            <p>
                {{ $record->clinic->name ?? 'العيادة' }} |
                رقم السجل: {{ $record->record_number }} |
                تاريخ الطباعة: {{ now()->format('Y/m/d H:i') }}
            </p>
        </div>

        <!-- Patient Information -->
        <div class="section no-break">
            <div class="section-header">
                <h2>البيانات الشخصية للمريض</h2>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-cell info-label">الاسم الكامل</div>
                        <div class="info-cell info-value">{{ $record->patient->first_name }} {{ $record->patient->last_name }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">رقم الملف</div>
                        <div class="info-cell info-value">{{ $record->patient->file_number }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">رقم الهاتف</div>
                        <div class="info-cell info-value">{{ $record->patient->phone ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">تاريخ الميلاد</div>
                        <div class="info-cell info-value">{{ $record->patient->date_of_birth ? $record->patient->date_of_birth->format('Y/m/d') : '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">الجنس</div>
                        <div class="info-cell info-value">{{ $record->patient->gender === 'male' ? 'ذكر' : ($record->patient->gender === 'female' ? 'أنثى' : '—') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visit Information -->
        <div class="section no-break">
            <div class="section-header">
                <h2>بيانات الزيارة</h2>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-cell info-label">رقم السجل</div>
                        <div class="info-cell info-value">{{ $record->record_number }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">تاريخ الزيارة</div>
                        <div class="info-cell info-value">{{ $record->visit_date ? $record->visit_date->format('Y/m/d') : '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">العيادة / القسم</div>
                        <div class="info-cell info-value">{{ $record->department->name ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">نوع العيادة</div>
                        <div class="info-cell info-value">{{ $clinicTypeLabels[$record->clinic_type] ?? $record->clinic_type ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">الطبيب المعالج</div>
                        <div class="info-cell info-value">{{ $record->doctor->name ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">الحالة</div>
                        <div class="info-cell info-value">
                            <span class="badge badge-{{ $record->status }}">{{ $statusLabels[$record->status] ?? $record->status }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clinical Information -->
        <div class="section">
            <div class="section-header">
                <h2>الفحص والتشخيص</h2>
            </div>
            <div class="section-body">
                @if($record->chief_complaint)
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-cell info-label">الشكوى الرئيسية</div>
                            <div class="info-cell info-value whitespace-pre">{{ $record->chief_complaint }}</div>
                        </div>
                    </div>
                @endif

                @if($record->examination)
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-cell info-label">الفحص السريري</div>
                            <div class="info-cell info-value whitespace-pre">{{ $record->examination }}</div>
                        </div>
                    </div>
                @endif

                @if($record->form_data && count($record->form_data) > 0)
                    @foreach($record->form_data as $key => $value)
                        @if($value)
                            <div class="info-grid">
                                <div class="info-row">
                                    <div class="info-cell info-label">{{ str_replace('_', ' ', $key) }}</div>
                                    <div class="info-cell info-value whitespace-pre">{{ $value }}</div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif

                @if($record->primary_diagnosis)
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-cell info-label" style="background: #fee2e2; color: #991b1b;">التشخيص الرئيسي</div>
                            <div class="info-cell info-value whitespace-pre">{{ $record->primary_diagnosis }}</div>
                        </div>
                    </div>
                @endif

                @if($record->secondary_diagnosis)
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-cell info-label" style="background: #fef3c7; color: #92400e;">التشخيص الثانوي</div>
                            <div class="info-cell info-value whitespace-pre">{{ $record->secondary_diagnosis }}</div>
                        </div>
                    </div>
                @endif

                @if($record->clinical_notes)
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-cell info-label">ملاحظات سريرية</div>
                            <div class="info-cell info-value whitespace-pre">{{ $record->clinical_notes }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Treatment Plans -->
        @if($record->treatmentPlans && $record->treatmentPlans->count() > 0)
            <div class="section page-break">
                <div class="section-header">
                    <h2>خطط العلاج ({{ $record->treatmentPlans->count() }})</h2>
                </div>
                <div class="section-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>العنوان</th>
                                <th>الوصف</th>
                                <th>الطبيب</th>
                                <th>البداية</th>
                                <th>النهاية</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($record->treatmentPlans as $index => $plan)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $plan->title }}</td>
                                    <td class="text-small">{{ Str::limit($plan->description, 50) }}</td>
                                    <td>{{ $plan->doctor->name ?? '—' }}</td>
                                    <td>{{ $plan->start_date ? $plan->start_date->format('Y/m/d') : '—' }}</td>
                                    <td>{{ $plan->end_date ? $plan->end_date->format('Y/m/d') : '—' }}</td>
                                    <td><span class="badge badge-{{ $plan->status }}">{{ $statusLabels[$plan->status] ?? $plan->status }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Follow Ups -->
        @if($record->followUps && $record->followUps->count() > 0)
            <div class="section">
                <div class="section-header">
                    <h2>المتابعات ({{ $record->followUps->count() }})</h2>
                </div>
                <div class="section-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>ملاحظات</th>
                                <th>الإجراء الموصى به</th>
                                <th>الطبيب</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($record->followUps as $index => $followUp)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $followUp->follow_up_date ? $followUp->follow_up_date->format('Y/m/d') : '—' }}</td>
                                    <td class="text-small">{{ Str::limit($followUp->notes, 50) }}</td>
                                    <td class="text-small">{{ $followUp->recommended_action ?? '—' }}</td>
                                    <td>{{ $followUp->doctor->name ?? '—' }}</td>
                                    <td><span class="badge badge-{{ $followUp->status }}">{{ $statusLabels[$followUp->status] ?? $followUp->status }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Record Metadata -->
        <div class="section no-break">
            <div class="section-header">
                <h2>معلومات السجل</h2>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-cell info-label">أنشئ بواسطة</div>
                        <div class="info-cell info-value">{{ $record->creator->name ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">تاريخ الإنشاء</div>
                        <div class="info-cell info-value">{{ $record->created_at ? $record->created_at->format('Y/m/d H:i') : '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-cell info-label">آخر تحديث</div>
                        <div class="info-cell info-value">{{ $record->updated_at ? $record->updated_at->format('Y/m/d H:i') : '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>تم إنشاء هذا المستند إلكترونياً بواسطة نظام إدارة العيادات (MCMS)</p>
            <p>هذا المستند سري ولا يجوز مشاركته دون إذن رسمي</p>
        </div>
    </div>
</body>
</html>
