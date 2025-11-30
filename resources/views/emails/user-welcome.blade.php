<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao iEDU</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #2d3748;
            background-color: #f7fafc;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #EEA71E 0%, #49AF45 100%);
            padding: 40px 30px;
            text-align: center;
        }

        .header h1 {
            color: white;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
        }

        .content {
            padding: 40px 30px;
        }

        .welcome-text {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            border-left: 4px solid #49AF45;
        }

        .info-card h3 {
            color: #2d3748;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .info-card p {
            color: #4a5568;
            line-height: 1.6;
        }

        .cta-section {
            text-align: center;
            margin: 35px 0;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #EEA71E 0%, #49AF45 100%);
            color: white;
            text-decoration: none;
            padding: 18px 36px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(238, 167, 30, 0.4);
        }

        .footer {
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
            color: #e2e8f0;
            padding: 30px;
            text-align: center;
        }

        .footer p {
            margin: 5px 0;
            font-size: 14px;
        }

        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }

            .header, .content, .footer {
                padding: 20px 15px;
            }

            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>🎉 Bem-vindo ao iEDU</h1>
            <p class="header-subtitle">Sistema de Gestão Educacional</p>
        </div>

        <div class="content">
            <div class="welcome-text">
                <p>Olá <strong>{{ $user->name }}</strong>!</p>
                <p style="margin-top: 15px;">
                    É com grande prazer que damos as boas-vindas ao iEDU, a plataforma completa de gestão educacional.
                </p>
            </div>

            <div class="info-card">
                <h3>📚 Sobre o iEDU</h3>
                <p>
                    O iEDU é uma plataforma moderna e intuitiva que facilita a gestão educacional,
                    conectando estudantes, professores, pais e administradores em um único ambiente.
                </p>
            </div>

            <div class="cta-section">
                <p style="margin-bottom: 20px; color: #4a5568;">
                    Acesse sua conta e comece a explorar todas as funcionalidades disponíveis:
                </p>
                <a href="{{ config('app.url') }}/login" class="cta-button">
                    Acessar Portal
                </a>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <p style="color: #4a5568;">
                    Se tiver alguma dúvida, nossa equipe de suporte está sempre disponível para ajudar.
                </p>
                <p style="color: #2d3748; font-weight: 500; margin-top: 15px;">
                    Atenciosamente,<br>
                    <strong>Equipe iEDU</strong>
                </p>
            </div>
        </div>

        <div class="footer">
            <p>Este é um email automático do <strong>iEDU</strong></p>
            <p>&copy; {{ date('Y') }} iEDU. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>

