<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir palavra-passe</title>
</head>
<body style="font-family: 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #2d3748; background: #f7fafc; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
        <h1 style="font-size: 22px; margin-bottom: 16px;">Redefinir palavra-passe</h1>
        <p>Olá {{ $user->name }},</p>
        <p>Recebemos um pedido para redefinir a palavra-passe da sua conta iEDU. Clique no botão abaixo para continuar. O link expira em 60 minutos.</p>
        <p style="margin: 28px 0;">
            <a href="{{ $resetUrl }}" style="display: inline-block; background: linear-gradient(135deg, #EEA71E 0%, #49AF45 100%); color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600;">Redefinir palavra-passe</a>
        </p>
        <p style="font-size: 13px; color: #718096;">Se não pediu este email, pode ignorar esta mensagem.</p>
        <p style="font-size: 12px; color: #a0aec0; word-break: break-all;">{{ $resetUrl }}</p>
    </div>
</body>
</html>
