<?php

namespace App\Services\Accounting;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Str;

class InvoiceService
{
    public function createWithItems(int $clinicId, array $payload): Invoice
    {
        return \DB::transaction(function () use ($clinicId, $payload) {
            $invoice = Invoice::query()->create([
                'clinic_id' => $clinicId,
                'patient_id' => $payload['patient_id'] ?? null,
                'date' => $payload['date'],
                'due_date' => $payload['due_date'] ?? null,
                'invoice_number' => 'INV-'.date('Ymd').'-'.Str::random(4),
                'status' => 'draft',
                'total_amount' => 0,
                'paid_amount' => 0,
                'balance_amount' => 0,
            ]);

            $total = 0;
            foreach ($payload['items'] as $item) {
                $lineTotal = (float) $item['quantity'] * (float) $item['unit_price'];
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $lineTotal,
                ]);
                $total += $lineTotal;
            }

            $invoice->update(['total_amount' => $total, 'balance_amount' => $total]);

            return $invoice->fresh('items');
        });
    }
}
