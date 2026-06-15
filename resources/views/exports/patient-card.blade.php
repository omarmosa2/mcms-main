<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        @page {
            margin: 11mm 10mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            direction: rtl;
            font-family: Tahoma, 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.55;
            text-align: right;
            unicode-bidi: embed;
        }

        .page {
            padding: 0;
        }

        .panel {
            border: 1px solid #d7e6f3;
            border-radius: 10px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .panel-body {
            padding: 13px 15px;
        }

        .header-table,
        .info-table,
        .visits-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .logo {
            width: 44px;
            height: 44px;
            border-radius: 22px;
            background: #0ea5e9;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            line-height: 44px;
            text-align: center;
        }

        .clinic-name {
            color: #52677d;
            font-size: 11px;
            text-align: center;
        }

        .title {
            margin-top: 3px;
            color: #075985;
            font-size: 22px;
            font-weight: 700;
            text-align: center;
        }

        .page-number {
            color: #64748b;
            font-size: 10px;
            text-align: left;
        }

        .page-number strong {
            display: block;
            color: #111827;
            font-size: 14px;
        }

        .info-table {
            margin-top: 12px;
        }

        .info-table td,
        .visits-table th,
        .visits-table td {
            border: 1px solid #e0ebf5;
            vertical-align: top;
        }

        .info-table td {
            width: 25%;
            padding: 7px 8px;
        }

        .label {
            display: block;
            color: #64748b;
            font-size: 9px;
            font-weight: 400;
            margin-bottom: 3px;
        }

        .value {
            display: block;
            color: #111827;
            font-size: 11px;
            font-weight: 700;
            min-height: 15px;
        }

        .section-title {
            color: #111827;
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 10px;
            text-align: right;
        }

        .visits-table {
            table-layout: fixed;
        }

        .visits-table th {
            background: #eef8fd;
            color: #334155;
            font-size: 8.5px;
            font-weight: 700;
            padding: 6px 5px;
            text-align: center;
        }

        .visits-table td {
            color: #111827;
            font-size: 8.5px;
            padding: 6px 5px;
            text-align: right;
            word-wrap: break-word;
        }

        .empty {
            color: #64748b;
            font-size: 11px;
            padding: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    use App\Support\ArabicPdfText;

    $dash = '—';
    $pdf = fn (mixed $value, ?string $fallback = null): string => ArabicPdfText::display($value, $fallback ?? $dash);
    $fullName = trim(($patient->first_name ?? '').' '.($patient->last_name ?? ''));
    $latestVisit = $visits->first();
    $gender = match ($patient->gender) {
        'male' => 'ذكر',
        'female' => 'أنثى',
        'other' => 'آخر',
        default => null,
    };
    $age = $patient->date_of_birth ? $patient->date_of_birth->age.' سنة' : null;
@endphp

<div class="page">
    <div class="panel">
        <div class="panel-body">
            <table class="header-table">
                <tr>
                    <td style="width: 58px;"><div class="logo">MC</div></td>
                    <td>
                        <div class="clinic-name">{!! $pdf($card['clinic_name'] ?? null) !!}</div>
                        <div class="title">{!! $pdf('بطاقة مريض') !!}</div>
                    </td>
                    <td style="width: 90px;">
                        <div class="page-number">
                            {!! $pdf('رقم الصفحة') !!}
                            <strong>{!! $pdf($card['page_number'] ?? null) !!}</strong>
                        </div>
                    </td>
                </tr>
            </table>

            <table class="info-table">
                <tr>
                    <td><span class="label">{!! $pdf('اسم المشروع أو المركز') !!}</span><span class="value">{!! $pdf($card['project_name'] ?? null) !!}</span></td>
                    <td><span class="label">{!! $pdf('اسم الطفل / المريض') !!}</span><span class="value">{!! $pdf($fullName) !!}</span></td>
                    <td><span class="label">{!! $pdf('اسم الأم') !!}</span><span class="value">{!! $pdf(null) !!}</span></td>
                    <td><span class="label">{!! $pdf('العمر') !!}</span><span class="value">{!! $pdf($age) !!}</span></td>
                </tr>
                <tr>
                    <td><span class="label">{!! $pdf('الجنس') !!}</span><span class="value">{!! $pdf($gender) !!}</span></td>
                    <td><span class="label">{!! $pdf('العنوان') !!}</span><span class="value">{!! $pdf(null) !!}</span></td>
                    <td><span class="label">{!! $pdf('رقم الهاتف') !!}</span><span class="value">{!! $pdf($patient->phone) !!}</span></td>
                    <td><span class="label">{!! $pdf('التاريخ') !!}</span><span class="value">{!! $pdf($card['date'] ?? null) !!}</span></td>
                </tr>
                <tr>
                    <td><span class="label">{!! $pdf('الطبيب') !!}</span><span class="value">{!! $pdf($card['doctor'] ?? null) !!}</span></td>
                    <td><span class="label">{!! $pdf('العيادة') !!}</span><span class="value">{!! $pdf($card['department'] ?? null) !!}</span></td>
                    <td><span class="label">{!! $pdf('رقم المريض') !!}</span><span class="value">{!! $pdf($patient->file_number) !!}</span></td>
                    <td><span class="label">{!! $pdf('تاريخ الميلاد') !!}</span><span class="value">{!! $pdf($patient->date_of_birth?->toDateString()) !!}</span></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-body">
            <h2 class="section-title">{!! $pdf('معلومات الزيارة') !!}</h2>
            <table class="info-table" style="margin-top: 0;">
                <tr>
                    <td><span class="label">{!! $pdf('السبب للزيارة') !!}</span><span class="value">{!! $pdf($latestVisit?->visit_reason) !!}</span></td>
                    <td><span class="label">{!! $pdf('الشكوى الرئيسية') !!}</span><span class="value">{!! $pdf($latestVisit?->chief_complaint) !!}</span></td>
                    <td colspan="2"><span class="label">{!! $pdf('ملاحظات عامة') !!}</span><span class="value">{!! $pdf($latestVisit?->general_notes) !!}</span></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-body">
            <h2 class="section-title">{!! $pdf('الزيارات الطبية') !!}</h2>
            <table class="visits-table">
                <thead>
                <tr>
                    <th>{!! $pdf('تاريخ الزيارة') !!}</th>
                    <th>{!! $pdf('أعراض جديدة') !!}</th>
                    <th>{!! $pdf('الشكوى المرضية أو الجراحية') !!}</th>
                    <th>{!! $pdf('التشخيص') !!}</th>
                    <th>{!! $pdf('العلاج الموصوف أو الإحالة') !!}</th>
                    <th>{!! $pdf('اسم الطبيب') !!}</th>
                    <th>{!! $pdf('التوقيع') !!}</th>
                    <th>{!! $pdf('ملاحظات') !!}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($visits as $visit)
                    <tr>
                        <td>{!! $pdf($visit->visit_date?->toDateString()) !!}</td>
                        <td>{!! $pdf($visit->new_symptoms) !!}</td>
                        <td>{!! $pdf($visit->medical_or_surgical_complaint) !!}</td>
                        <td>{!! $pdf($visit->diagnosis) !!}</td>
                        <td>{!! $pdf($visit->prescribed_treatment_or_referral) !!}</td>
                        <td>{!! $pdf($visit->doctor?->name) !!}</td>
                        <td>{!! $pdf($visit->signature) !!}</td>
                        <td>{!! $pdf($visit->notes) !!}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty">{!! $pdf('لا توجد زيارات مسجلة') !!}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
