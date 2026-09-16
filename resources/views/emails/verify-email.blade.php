<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmation de votre adresse e-mail Spotlight</title>
</head>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#02040c;padding:20px 28px;color:#ffffff;font-size:24px;font-weight:700;">
                            <span style="color:#e31e24;">S</span>potlight
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 28px;">
                            <p style="margin:0 0 18px;font-size:18px;font-weight:700;">Bienvenue {{ $notifiable->name }},</p>
                            <p style="margin:0 0 16px;line-height:1.6;color:#4b5563;">
                                Votre compte citoyen Spotlight a bien été créé.
                            </p>
                            <p style="margin:0 0 24px;line-height:1.6;color:#4b5563;">
                                Confirmez votre adresse e-mail pour accéder au tableau de bord et publier vos déclarations.
                            </p>
                            <p style="margin:0 0 24px;text-align:center;">
                                <a href="{{ $verificationUrl }}" style="display:inline-block;background:#e31e24;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:6px;">
                                    Confirmer mon adresse e-mail
                                </a>
                            </p>
                            <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#6b7280;">
                                Ce lien expire dans {{ $expiresIn }} minutes.
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
                                Si vous n’avez pas créé ce compte, ignorez simplement cet e-mail.
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
