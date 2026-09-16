<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Réinitialisation du mot de passe Spotlight</title>
</head>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#02040c;padding:20px 28px;color:#ffffff;font-size:24px;font-weight:700;">
                            <span style="color:#e31e24;">S</span>potlight
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 28px;">
                            <p style="margin:0 0 18px;font-size:18px;font-weight:700;">Bonjour {{ $notifiable->name }},</p>
                            <p style="margin:0 0 16px;line-height:1.6;color:#4b5563;">
                                Une demande de réinitialisation a été effectuée pour votre compte {{ $role }} Spotlight.
                            </p>
                            <p style="margin:0 0 24px;line-height:1.6;color:#4b5563;">
                                Utilisez le bouton ci-dessous pour définir un nouveau mot de passe sécurisé.
                            </p>
                            <p style="margin:0 0 24px;text-align:center;">
                                <a href="{{ $resetUrl }}" style="display:inline-block;background:#e31e24;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:6px;">
                                    Réinitialiser mon mot de passe
                                </a>
                            </p>
                            <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#6b7280;">
                                Ce lien expire dans {{ $expiresIn }} minutes et ne peut être utilisé qu’une seule fois.
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
                                Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet e-mail.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #e5e7eb;padding:18px 28px;font-size:12px;color:#6b7280;">
                            Spotlight, plateforme citoyenne de déclaration des pertes et découvertes.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
