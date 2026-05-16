<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentReminder extends Notification
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email !== null && $notifiable->prefersEmailNotification('appointment_reminder')) {
            $channels[] = 'mail';
        }

        if ($notifiable->prefersSmsNotification('appointment_reminder')) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Appointment Reminder - '.config('app.name'))
            ->greeting('Hello '.($notifiable->name ?? 'Patient').',')
            ->line('This is a reminder for your upcoming appointment.')
            ->line('Date: '.$this->appointment->scheduled_for->format('l, F j, Y'))
            ->line('Time: '.$this->appointment->scheduled_for->format('g:i A'))
            ->line('Doctor: '.($this->appointment->doctor?->name ?? 'TBD'))
            ->action('View Appointment', url('/appointments/'.$this->appointment->id))
            ->line('Please arrive 15 minutes before your scheduled time.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_reminder',
            'appointment_id' => $this->appointment->id,
            'appointment_number' => $this->appointment->appointment_number,
            'scheduled_for' => $this->appointment->scheduled_for->toIso8601String(),
            'doctor_name' => $this->appointment->doctor?->name ?? 'TBD',
            'message' => "Reminder: Your appointment is scheduled for {$this->appointment->scheduled_for->format('l, F j, Y')} at {$this->appointment->scheduled_for->format('g:i A')} with Dr. ".($this->appointment->doctor?->name ?? 'TBD'),
        ];
    }

    public function toSms(object $notifiable): string
    {
        return 'Reminder: Your appointment at '.config('app.name')." is on {$this->appointment->scheduled_for->format('M j')} at {$this->appointment->scheduled_for->format('g:i A')} with Dr. ".($this->appointment->doctor?->name ?? 'TBD');
    }
}
