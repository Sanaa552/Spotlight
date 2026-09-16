<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class SpotlightResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $role = $notifiable->isModerateur() ? 'modérateur' : 'citoyen';
        $expiresIn = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        $data = compact('notifiable', 'resetUrl', 'role', 'expiresIn');

        return (new MailMessage)
            ->subject('Réinitialisez votre mot de passe Spotlight')
            ->view([
                'html' => 'emails.password-reset',
                'text' => 'emails.password-reset-text',
            ], $data);
    }
}
