<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceIssued extends Notification
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email !== null && $notifiable->prefersEmailNotification('invoice_issued')) {
            $channels[] = 'mail';
        }

        if ($notifiable->prefersSmsNotification('invoice_issued')) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Invoice Issued - #'.$this->invoice->invoice_number)
            ->greeting('Hello '.($notifiable->name ?? 'Patient').',')
            ->line('A new invoice has been issued for your account.')
            ->line('Invoice Number: '.$this->invoice->invoice_number)
            ->line('Amount: '.number_format((float) $this->invoice->total_amount, 2))
            ->line('Due Date: '.$this->invoice->due_date?->format('F j, Y') ?? 'N/A')
            ->action('View Invoice', url('/billing/invoices/'.$this->invoice->id))
            ->line('Please ensure payment is made before the due date.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_issued',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'total_amount' => $this->invoice->total_amount,
            'due_date' => $this->invoice->due_date?->toIso8601String(),
            'message' => "Invoice #{$this->invoice->invoice_number} has been issued for ".number_format((float) $this->invoice->total_amount, 2),
        ];
    }

    public function toSms(object $notifiable): string
    {
        return "Invoice #{$this->invoice->invoice_number} issued at ".config('app.name').' for '.number_format((float) $this->invoice->total_amount, 2).'. Due: '.($this->invoice->due_date?->format('M j') ?? 'N/A');
    }
}
