<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Estudante - iEDU</title>
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

        .student-card {
            background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            border-left: 4px solid #49AF45;
        }

        .student-card h3 {
            color: #2d3748;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #4a5568;
            min-width: 140px;
        }

        .info-value {
            color: #2d3748;
        }

        .relationship-badge {
            display: inline-block;
            background: linear-gradient(135deg, #EEA71E, #49AF45);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 600;
            text-transform: capitalize;
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

            .info-row {
                flex-direction: column;
            }

            .info-label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>📚 Cadastro de Estudante</h1>
            <p class="header-subtitle">iEDU - Sistema de Gestão Educacional</p>
        </div>

        <div class="content">
            <div class="welcome-text">
                <p>Olá <strong>{{ $guardian->name }}</strong>!</p>
                <p style="margin-top: 15px;">
                    Informamos que <strong>{{ $student->first_name }} {{ $student->last_name }}</strong>
                    foi cadastrado(a) com sucesso no sistema iEDU.
                </p>
            </div>

            <div class="student-card">
                <h3>📋 Informações do Estudante</h3>
                <div class="info-row">
                    <span class="info-label">Nome Completo:</span>
                    <span class="info-value"><strong>{{ $student->first_name }} {{ $student->middle_name }} {{ $student->last_name }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Número de Estudante:</span>
                    <span class="info-value">{{ $student->student_number }}</span>
                </div>
                @if($student->current_grade_level)
                <div class="info-row">
                    <span class="info-label">Nível/Grau:</span>
                    <span class="info-value">{{ $student->current_grade_level }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">Seu Relacionamento:</span>
                    <span class="info-value">
                        <span class="relationship-badge">{{ str_replace('_', ' ', $relationship->relationship_type) }}</span>
                    </span>
                </div>
            </div>

            <div class="features">
                <h4>✨ No Portal do Responsável, você pode:</h4>
                <ul>
                    <li>Acompanhar o desempenho acadêmico do estudante</li>
                    <li>Visualizar notas e avaliações</li>
                    <li>Verificar a frequência escolar</li>
                    <li>Consultar taxas e pagamentos</li>
                    <li>Comunicar-se com professores e escola</li>
                    <li>Justificar faltas e solicitações</li>
                </ul>
            </div>

            <div class="cta-section">
                <p style="margin-bottom: 20px; color: #4a5568;">
                    Acesse o Portal do Responsável para acompanhar o progresso do estudante:
                </p>
                <a href="{{ config('app.url') }}/parent/portal" class="cta-button">
                    Acessar Portal do Responsável
                </a>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <p style="color: #4a5568;">
                    Se tiver alguma dúvida ou precisar de mais informações, entre em contato com a secretaria da escola.
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

