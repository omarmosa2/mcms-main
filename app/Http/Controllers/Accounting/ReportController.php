<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function outstandingByClinic(Request $request)
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(403, 'Clinic context is required.');
        }

        $invoices = Invoice::query()
            ->where('clinic_id', $clinicId)
            ->where('balance_amount', '>', 0)
            ->get(['id', 'invoice_number', 'balance_amount', 'date', 'due_date', 'status']);

        return response()->json(['clinic_id' => $clinicId, 'outstanding' => $invoices]);
    }

    public function revenueByClinic(Request $request)
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(403, 'Clinic context is required.');
        }
        $start = $request->query('start');
        $end = $request->query('end');
        $query = Invoice::query()->where('clinic_id', $clinicId);
        if ($start) {
            $query->where('date', '>=', $start);
        }
        if ($end) {
            $query->where('date', '<=', $end);
        }
        $revenue = $query->sum('total_amount');

        return response()->json(['clinic_id' => $clinicId, 'start' => $start, 'end' => $end, 'revenue' => $revenue]);
    }
}
