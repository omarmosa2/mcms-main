<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>وصفة طبية - {{ $prescription->prescription_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; color: #1a1a1a; background: #fff; padding: 20px; font-size: 14px; line-height: 1.6; }

        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0ea5e9; padding-bottom: 15px; margin-bottom: 20px; }
        .clinic-info { text-align: right; }
        .clinic-name { font-size: 22px; font-weight: bold; color: #0ea5e9; margin-bottom: 4px; }
        .clinic-details { font-size: 12px; color: #666; }
        .logo { max-height: 80px; max-width: 120px; }
        .rx-badge { font-size: 48px; font-weight: bold; color: #0ea5e9; opacity: 0.3; }

        .patient-section { background: #f8fafc; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid #e2ecf6; }
        .patient-section h3 { color: #0ea5e9; font-size: 14px; margin-bottom: 8px; border-bottom: 1px solid #e2ecf6; padding-bottom: 5px; }
        .patient-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .patient-grid div { font-size: 13px; }
        .patient-grid strong { color: #333; }

        .diagnosis-section { background: #fff7ed; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid #fed7aa; }
        .diagnosis-section h3 { color: #ea580c; font-size: 14px; margin-bottom: 8px; }

        .prescription-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .prescription-table th { background: #0ea5e9; color: white; padding: 10px 12px; text-align: right; font-size: 13px; }
        .prescription-table td { padding: 10px 12px; border-bottom: 1px solid #e2ecf6; font-size: 13px; }
        .prescription-table tr:nth-child(even) { background: #f8fafc; }
        .med-name { font-weight: bold; color: #1a1a1a; }
        .med-details { color: #555; font-size: 12px; }

        .footer { margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end; }
        .doctor-signature { text-align: center; }
        .signature-line { border-top: 2px solid #333; width: 200px; margin: 0 auto 5px; padding-top: 5px; }
        .doctor-name { font-weight: bold; font-size: 14px; }
        .doctor-specialty { font-size: 12px; color: #666; }
        .rx-number { font-size: 12px; color: #999; }

        .notes-section { margin-bottom: 20px; }
        .notes-section h3 { color: #0ea5e9; font-size: 14px; margin-bottom: 5px; }
        .notes-section p { font-size: 13px; color: #555; }

        @media print {
            body { padding: 10px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="clinic-info">
            <div class="clinic-name">{{ $clinic->name ?? 'المجمع الطبي' }}</div>
            <div class="clinic-details">
                @if($clinic->phone) <div>هاتف: {{ $clinic->phone }}</div> @endif
                @if($clinic->email) <div>بريد: {{ $clinic->email }}</div> @endif
                @if($clinic->address) <div>عنوان: {{ $clinic->address }}</div> @endif
            </div>
        </div>
        <div>
            @if($branding?->logo_path)
                <img src="{{ public_path('storage/' . $branding->logo_path) }}" class="logo" alt="logo">
            @else
                <div class="rx-badge">Rx</div>
            @endif
        </div>
    </div>

    <div style="text-align: center; margin-bottom: 20px;">
        <h2 style="color: #0ea5e9; font-size: 18px;">وصفة طبية</h2>
        <p style="font-size: 12px; color: #666;">
            رقم الوصفة: {{ $prescription->prescription_number }} |
            التاريخ: {{ $prescription->issued_at?->format('Y/m/d') ?? now()->format('Y/m/d') }}
        </p>
    </div>

    <div class="patient-section">
        <h3>بيانات المريض</h3>
        <div class="patient-grid">
            <div><strong>الاسم:</strong> {{ $prescription->patient->first_name }} {{ $prescription->patient->last_name }}</div>
            <div><strong>رقم الملف:</strong> {{ $prescription->patient->file_number }}</div>
            <div><strong>العمر:</strong>
                @php
                    $age = $prescription->patient->date_of_birth ? now()->diffInYears($prescription->patient->date_of_birth) : null;
                @endphp
                {{ $age ? $age . ' سنة' : '—' }}
            </div>
            <div><strong>الجنس:</strong>
                @if($prescription->patient->gender === 'male') ذكر
                @elseif($prescription->patient->gender === 'female') أنثى
                @else — @endif
            </div>
            @if($prescription->patient->phone)
                <div><strong>الهاتف:</strong> {{ $prescription->patient->phone }}</div>
            @endif
        </div>
    </div>

    @if($prescription->diagnosis)
        <div class="diagnosis-section">
            <h3>التشخيص</h3>
            <p>{{ $prescription->diagnosis }}</p>
        </div>
    @endif

    <table class="prescription-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 25%;">اسم الدواء</th>
                <th style="width: 15%;">الجرعة</th>
                <th style="width: 15%;">التكرار</th>
                <th style="width: 15%;">المدة</th>
                <th style="width: 10%;">الكمية</th>
                <th style="width: 15%;">تعليمات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prescription->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><span class="med-name">{{ $item->medication_name }}</span></td>
                    <td>{{ $item->dosage }}</td>
                    <td>{{ $item->frequency }}</td>
                    <td>{{ $item->duration ?? '—' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td class="med-details">{{ $item->instructions ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($prescription->notes)
        <div class="notes-section">
            <h3>ملاحظات الطبيب</h3>
            <p>{{ $prescription->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <div class="rx-number">
            رقم الوصفة: {{ $prescription->prescription_number }}
        </div>
        <div class="doctor-signature">
            <div class="signature-line"></div>
            <div class="doctor-name">د. {{ $prescription->prescriber?->name ?? '' }}</div>
            <div class="doctor-specialty">
                {{ $prescription->prescriber?->doctorProfile?->specialty ?? '' }}
                @if($prescription->prescriber?->doctorProfile?->license_number)
                    | ترخيص: {{ $prescription->prescriber->doctorProfile->license_number }}
                @endif
            </div>
        </div>
    </div>
</body>
</html>
