<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class SpotlightVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $expiresIn = (int) config('auth.verification.expire', 60);
        $data = compact('notifiable', 'verificationUrl', 'expiresIn');

        return (new MailMessage)
            ->subject('Confirmez votre adresse e-mail Spotlight')
            ->view([
                'html' => 'emails.verify-email',
                'text' => 'emails.verify-email-text',
            ], $data);
    }
}
