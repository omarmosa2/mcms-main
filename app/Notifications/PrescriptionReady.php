<?php

namespace App\Notifications;

use App\Models\Prescription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrescriptionReady extends Notification
{
    use Queueable;

    public function __construct(
        public Prescription $prescription,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email !== null && $notifiable->prefersEmailNotification('prescription_ready')) {
            $channels[] = 'mail';
        }

        if ($notifiable->prefersSmsNotification('prescription_ready')) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Prescription Ready - '.config('app.name'))
            ->greeting('Hello '.($notifiable->name ?? 'Patient').',')
            ->line('Your prescription is ready for pickup.')
            ->line('Prescription Number: '.$this->prescription->prescription_number)
            ->line('Prescribed by: Dr. '.($this->prescription->doctor?->name ?? 'TBD'))
            ->line('Date: '.$this->prescription->issued_at?->format('F j, Y') ?? 'N/A')
            ->action('View Prescription', url('/prescriptions/'.$this->prescription->id))
            ->line('Please bring your ID when picking up your prescription.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'prescription_ready',
            'prescription_id' => $this->prescription->id,
            'prescription_number' => $this->prescription->prescription_number,
            'doctor_name' => $this->prescription->doctor?->name ?? 'TBD',
            'issued_at' => $this->prescription->issued_at?->toIso8601String(),
            'message' => "Your prescription #{$this->prescription->prescription_number} by Dr. ".($this->prescription->doctor?->name ?? 'TBD').' is ready for pickup.',
        ];
    }

    public function toSms(object $notifiable): string
    {
        return "Your prescription #{$this->prescription->prescription_number} at ".config('app.name').' is ready for pickup. Dr. '.($this->prescription->doctor?->name ?? 'TBD');
    }
}
