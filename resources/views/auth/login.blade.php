<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In - {{ config('app.name', 'KPFC Fleet Tracker') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
            box-sizing: border-box;
        }
        .login-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 1rem;
            padding: 2.5rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
            text-align: center;
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #1e1b4b;
            color: #818cf8;
            border: 1px solid #3730a3;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1.25rem;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 0.5rem 0;
        }
        .subtitle {
            font-size: 0.875rem;
            color: #94a3b8;
            margin: 0 0 2rem 0;
            line-height: 1.4;
        }
        .sso-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            background: #4f46e5;
            color: #ffffff;
            border: none;
            padding: 0.85rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.1s ease;
            box-sizing: border-box;
        }
        .sso-btn:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }
        .sso-btn:active {
            transform: translateY(0);
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .alert-status {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981;
            color: #6ee7b7;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .footer-note {
            margin-top: 2rem;
            font-size: 0.75rem;
            color: #64748b;
            line-height: 1.4;
        }
        .footer-note a {
            color: #818cf8;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand-badge">
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
            </svg>
            KPFC Identity Federation
        </div>

        <h1 class="title">KPFC Fleet Management</h1>
        <p class="subtitle">Securely sign in with your centralized KPFC Admin credentials via Single Sign-On.</p>

        @if ($errors->has('sso'))
            <div class="alert-error" role="alert">
                <strong>Authentication Error:</strong> {{ $errors->first('sso') }}
            </div>
        @endif

        @if (session('status'))
            <div class="alert-status">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('auth.kpfc.redirect') }}" class="sso-btn" id="sso-sign-in-button">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0110 0v4"></path>
            </svg>
            Sign in with KPFC Admin
        </a>

        <div class="footer-note">
            Fleet Management is an authorized OAuth 2.0 client.<br>
            Tokens and sessions are authenticated against <a href="{{ config('services.kpfc_sso.issuer') }}" target="_blank">{{ config('services.kpfc_sso.issuer') }}</a>.
        </div>
    </div>
</body>
</html>
