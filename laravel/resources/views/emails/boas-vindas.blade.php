<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo!</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        .wrapper {
            max-width: 580px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        /* Header */
        .header {
            background: #1a202c;
            padding: 36px 40px;
            text-align: center;
        }

        .header-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 16px;
        }

        .header h1 {
            color: #48bb78;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .header p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            margin-top: 6px;
        }

        /* Body */
        .body {
            padding: 36px 40px;
        }

        .greeting {
            font-size: 16px;
            color: #1a202c;
            margin-bottom: 16px;
        }

        .intro {
            font-size: 14px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        /* Credenciais */
        .credentials {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 28px;
        }

        .credentials h3 {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #718096;
            margin-bottom: 16px;
        }

        .credential-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .credential-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .credential-label {
            font-size: 13px;
            color: #718096;
        }

        .credential-value {
            font-size: 14px;
            font-weight: 600;
            color: #1a202c;
            font-family: 'Courier New', monospace;
            background: #edf2f7;
            padding: 4px 10px;
            border-radius: 4px;
        }

        /* Alerta de segurança */
        .alert {
            background: #fffbeb;
            border: 1px solid #f6d860;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 28px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .alert-text {
            font-size: 13px;
            color: #744210;
            line-height: 1.5;
        }

        .alert-text strong {
            display: block;
            margin-bottom: 4px;
        }

        /* Botão */
        .btn-wrapper {
            text-align: center;
            margin-bottom: 28px;
        }

        .btn {
            display: inline-block;
            background: #48bb78;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        /* Footer */
        .footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 24px 40px;
            text-align: center;
        }

        .footer p {
            font-size: 12px;
            color: #a0aec0;
            line-height: 1.6;
        }
    </style>
</head>

<body>
    <div class="wrapper">

        <!-- Header -->
        <div class="header">
            <!-- Ícone SVG inline -->
            <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                <rect width="100" height="100" rx="24" fill="#1a202c" />
                <circle cx="50" cy="50" r="28" fill="none" stroke="#48bb78" stroke-width="8" />
                <path d="M35 52 L45 62 L65 38" fill="none" stroke="#48bb78" stroke-width="8"
                    stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <h1>{{ $nomeEmpresa }}</h1>
            <p>Sistema de PDV</p>
        </div>

        <!-- Body -->
        <div class="body">
            <p class="greeting">Olá, <strong>{{ $user->name }}</strong>! 👋</p>

            <p class="intro">
                Sua conta foi criada com sucesso no sistema
                <strong>{{ $nomeEmpresa }}</strong>.
                Abaixo estão suas credenciais de acesso iniciais.
            </p>

            <!-- Credenciais -->
            <div class="credentials">
                <h3>Suas credenciais de acesso</h3>
                <div class="credential-row">
                    <span class="credential-label">E-mail</span>
                    <span class="credential-value">{{ $user->email }}</span>
                </div>
                <div class="credential-row">
                    <span class="credential-label">Senha temporária</span>
                    <span class="credential-value">{{ $senhaTemporaria }}</span>
                </div>
            </div>

            <!-- Alerta -->
            <div class="alert">
                <span class="alert-icon">⚠️</span>
                <div class="alert-text">
                    <strong>Troque sua senha no primeiro acesso</strong>
                    Por segurança, você será solicitado a criar uma nova senha
                    ao fazer login pela primeira vez. Não compartilhe essas credenciais.
                </div>
            </div>

            <!-- Botão -->
            <div class="btn-wrapper">
                <a href="{{ config('app.url') }}" class="btn">
                    Acessar o sistema →
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                Este e-mail foi enviado automaticamente pelo sistema {{ $nomeEmpresa }}.<br>
                Se você não esperava receber este e-mail, entre em contato com o administrador.
            </p>
        </div>

    </div>
</body>

</html>