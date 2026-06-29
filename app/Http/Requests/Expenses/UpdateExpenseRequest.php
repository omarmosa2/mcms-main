<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'expense_date' => ['required', 'date', 'date_format:Y-m-d'],
            'payment_method' => ['required', 'string', 'in:cash,transfer,card,other'],
            'status' => ['required', 'string', 'in:pending,paid,cancelled'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'paid_to' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان المصروف مطلوب.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'expense_date.required' => 'تاريخ المصروف مطلوب.',
            'payment_method.required' => 'طريقة الدفع مطلوبة.',
            'status.required' => 'الحالة مطلوبة.',
            'status.in' => 'الحالة يجب أن تكون واحدة من: معلق، مدفوع، ملغي.',
            'attachment.mimes' => 'المرفق يجب أن يكون PDF أو صورة فقط.',
            'attachment.max' => 'حجم المرفق يجب ألا يتجاوز 5 ميجابايت.',
        ];
    }
}
