<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convite para Equipe - {{ $tenant->name }}</title>
    <style>
        /* Reset and base style ss */
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
        
        /* Container and layout */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
        }
        
        /* Header with SGAS branding */
        .header {
            background: linear-gradient(135deg, #EEA71E 0%, #49AF45 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        .header h1 {
            color: white;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .header-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            font-weight: 400;
        }
        
        /* Main content area */
        .content {
            padding: 40px 30px;
        }
        
        .welcome-section {
            margin-bottom: 30px;
        }
        
        .welcome-text {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            line-height: 1.7;
        }
        
        /* Invitation details card */
        .invite-card {
            background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            border-left: 4px solid #49AF45;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .invite-card h3 {
            color: #2d3748;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .invite-card h3::before {
            content: '📋';
            margin-right: 10px;
            font-size: 24px;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #4a5568;
            min-width: 120px;
            margin-right: 15px;
        }
        
        .detail-value {
            color: #2d3748;
            flex: 1;
        }
        
        /* Role badge */
        .role-badge {
            display: inline-block;
            background: linear-gradient(135deg, #EEA71E, #49AF45);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(238, 167, 30, 0.3);
        }
        
        /* Organization info section */
        .org-info {
            background: linear-gradient(135deg, #e6fffa 0%, #b2f5ea 100%);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            border-left: 4px solid #38b2ac;
        }
        
        .org-info h4 {
            color: #2c7a7b;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .org-info h4::before {
            content: '🏢';
            margin-right: 10px;
            font-size: 20px;
        }
        
        .org-info p {
            color: #2c7a7b;
            line-height: 1.6;
        }
        
        /* CTA Button */
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(238, 167, 30, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .cta-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .cta-button:hover::before {
            left: 100%;
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(238, 167, 30, 0.5);
        }
        
        /* Expiry notice */
        .expiry-notice {
            background: linear-gradient(135deg, #fffaf0 0%, #fef5e7 100%);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            border-left: 4px solid #f6ad55;
            position: relative;
        }
        
        .expiry-notice::before {
            content: '⏰';
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 20px;
        }
        
        .expiry-notice .content {
            padding-left: 35px;
        }
        
        .expiry-notice strong {
            color: #c05621;
            font-weight: 600;
        }
        
        .expiry-notice p {
            color: #744210;
            font-size: 14px;
            margin: 0;
        }
        
        /* Footer */
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
        
        .footer .brand {
            font-weight: 600;
            color: #EEA71E;
        }
        
        /* Responsive design */
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
            
            .invite-card, .org-info, .expiry-notice {
                padding: 20px;
            }
            
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .detail-label {
                min-width: auto;
                margin-right: 0;
                margin-bottom: 5px;
            }
            
            .cta-button {
                padding: 15px 30px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header Section -->
        <div class="header">
            <div class="header-content">
                <h1>🎉 Convite para Equipe</h1>
                <p class="header-subtitle">Sistema de Gestão Ambiental e Social</p>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="content">
            <div class="welcome-section">
                <p class="welcome-text">
                    Olá! Temos o prazer de convidá-lo(a) para fazer parte da nossa equipe de colaboração.
                </p>
            </div>
            
            <!-- Invitation Details -->
            <div class="invite-card">
                <h3>Detalhes do Convite</h3>
                
                <div class="detail-row">
                    <span class="detail-label">Organização:</span>
                    <span class="detail-value"><strong>{{ $tenant->name }}</strong></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Função:</span>
                    <span class="detail-value">
                        <span class="role-badge">{{ $roleDisplayName }}</span>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Convidado por:</span>
                    <span class="detail-value">{{ $inviter->name ?? $inviter->first_name . ' ' . $inviter->last_name }}</span>
                </div>
                
                @if($invitation->message)
                <div class="detail-row">
                    <span class="detail-label">Mensagem:</span>
                    <span class="detail-value">{{ $invitation->message }}</span>
                </div>
                @endif
            </div>
            
            <!-- Organization Information -->
            <div class="org-info">
                <h4>Sobre a Organização</h4>
                <p>{{ $tenant->description ?? 'Uma organização comprometida com a excelência na gestão ambiental e social, focada em inovação e sustentabilidade.' }}</p>
            </div>
            
            <!-- Call to Action -->
            <div class="cta-section">
                <p style="margin-bottom: 20px; color: #4a5568; font-size: 16px;">
                    Para aceitar este convite e começar a colaborar com a nossa equipe, clique no botão abaixo:
                </p>
                
                <a href="{{ $acceptUrl }}" class="cta-button">
                    ✅ Aceitar Convite
                </a>
            </div>
            
            <!-- Expiry Notice -->
            <div class="expiry-notice">
                <div class="content">
                    <p><strong>⚠️ Importante:</strong> Este convite expira em <strong>{{ $expiryDate }}</strong>. 
                    Após essa data, será necessário solicitar um novo convite.</p>
                </div>
            </div>
            
            <!-- Closing -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <p style="color: #4a5568; margin-bottom: 10px;">
                    Se tiver alguma dúvida ou não reconhecer este convite, entre em contacto connosco.
                </p>
                
                <p style="color: #2d3748; font-weight: 500;">
                    Atenciosamente,<br>
                    <strong>Equipe {{ $tenant->name }}</strong>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Este é um email automático do <span class="brand">SGAS</span> - Sistema de Gestão Ambiental e Social</p>
            <p>Por favor, não responda diretamente a este email</p>
            <p>&copy; {{ date('Y') }} {{ $tenant->name }}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
