<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao Portal do Professor - iEDU</title>
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

        .credentials-card {
            background: linear-gradient(135deg, #fffaf0 0%, #fef5e7 100%);
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            border-left: 4px solid #f6ad55;
        }

        .credentials-card h3 {
            color: #2d3748;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .credential-row {
            display: flex;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .credential-label {
            font-weight: 600;
            color: #4a5568;
            min-width: 100px;
        }

        .credential-value {
            color: #2d3748;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        .warning-box {
            background: linear-gradient(135deg, #fed7d7 0%, #fc8181 100%);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            border-left: 4px solid #e53e3e;
        }

        .warning-box p {
            color: #742a2a;
            font-weight: 500;
            margin: 0;
        }

        .features {
            background: linear-gradient(135deg, #e6fffa 0%, #b2f5ea 100%);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
        }

        .features h4 {
            color: #2c7a7b;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .features ul {
            list-style: none;
            padding: 0;
        }

        .features li {
            color: #2c7a7b;
            margin-bottom: 10px;
            padding-left: 25px;
            position: relative;
        }

        .features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #38b2ac;
            font-weight: bold;
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

            .credential-row {
                flex-direction: column;
            }

            .credential-label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>👨‍🏫 Bem-vindo ao Portal do Professor</h1>
            <p class="header-subtitle">iEDU - Sistema de Gestão Educacional</p>
        </div>

        <div class="content">
            <div class="welcome-text">
                <p>Olá <strong>{{ $teacher->first_name }} {{ $teacher->last_name }}</strong>!</p>
                <p style="margin-top: 15px;">
                    É com grande prazer que damos as boas-vindas ao iEDU! Sua conta de professor foi criada com sucesso.
                </p>
            </div>

            <div class="credentials-card">
                <h3>🔐 Suas Credenciais de Acesso</h3>
                <div class="credential-row">
                    <span class="credential-label">Email:</span>
                    <span class="credential-value">{{ $user->identifier }}</span>
                </div>
                <div class="credential-row">
                    <span class="credential-label">Senha:</span>
                    <span class="credential-value">{{ $password }}</span>
                </div>
            </div>

            <div class="warning-box">
                <p>⚠️ <strong>Importante:</strong> Por favor, altere sua senha no primeiro acesso por questões de segurança.</p>
            </div>

            <div class="features">
                <h4>✨ Funcionalidades do Portal do Professor:</h4>
                <ul>
                    <li>Gerenciar suas turmas e estudantes</li>
                    <li>Lançar notas e avaliações</li>
                    <li>Registrar frequência dos alunos</li>
                    <li>Acessar seu horário de aulas</li>
                    <li>Comunicar-se com estudantes e pais</li>
                    <li>Visualizar relatórios e estatísticas</li>
                </ul>
            </div>

            <div class="cta-section">
                <p style="margin-bottom: 20px; color: #4a5568;">
                    Acesse seu portal e comece a usar o sistema:
                </p>
                <a href="{{ config('app.url') }}/teacher/portal" class="cta-button">
                    Acessar Portal do Professor
                </a>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <p style="color: #4a5568;">
                    Se tiver alguma dúvida ou precisar de ajuda, nossa equipe de suporte está sempre disponível.
                </p>
                <p style="color: #2d3748; font-weight: 500; margin-top: 15px;">
                    Boas-vindas e sucesso no trabalho!<br>
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

