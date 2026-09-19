<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You've been added as a cashier" with a link to set a password (a password-reset token).
 */
class StaffInvitation extends Notification
{
    use Queueable;

    public function __construct(public Business $business, public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject("You're on the team at {$this->business->business_name}")
            ->greeting("Hi {$notifiable->name}!")
            ->line("{$this->business->business_name} added you as a cashier on iPOSa.")
            ->line('Set your password to start using the register.')
            ->action('Set my password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes. Ask the owner to resend the invite if it runs out.');
    }
}
