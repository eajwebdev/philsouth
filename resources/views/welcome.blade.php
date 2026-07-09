<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'PhilSouth') }} — Inventory</title>

        <link rel="icon" type="image/jpeg" href="{{ asset('logo.jpg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        <style>
            :root {
                --gold: #E0A93C;
                --gold-soft: #F8B803;
                --brown: #6E4B25;
                --ink: #1b1b18;
                --muted: #706f6c;
                --surface: #ffffff;
                --page: #FDFBF6;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
                background:
                    radial-gradient(1200px 600px at 100% -10%, rgba(224,169,60,0.14), transparent 60%),
                    var(--page);
                color: var(--ink);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }
            .card {
                width: 100%;
                max-width: 960px;
                display: grid;
                grid-template-columns: 1fr 1fr;
                background: var(--surface);
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 20px 60px -20px rgba(110, 75, 37, 0.35),
                            0 0 0 1px rgba(26,26,0,0.06);
            }
            .brand {
                background:
                    radial-gradient(600px 400px at 30% 20%, rgba(248,184,3,0.18), transparent 70%),
                    linear-gradient(160deg, #fff 0%, #FBF3E2 100%);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 48px;
                gap: 20px;
                border-right: 1px solid rgba(110,75,37,0.10);
            }
            .brand img {
                width: 220px;
                max-width: 100%;
                height: auto;
                filter: drop-shadow(0 8px 24px rgba(110,75,37,0.25));
            }
            .brand .tag {
                font-size: 13px;
                letter-spacing: 0.18em;
                text-transform: uppercase;
                color: var(--brown);
                font-weight: 600;
            }
            .panel {
                padding: 56px 48px;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
            .panel h1 {
                font-size: 30px;
                font-weight: 700;
                line-height: 1.15;
                margin-bottom: 10px;
            }
            .panel h1 span { color: var(--gold); }
            .panel p {
                color: var(--muted);
                font-size: 15px;
                line-height: 1.6;
                margin-bottom: 28px;
            }
            .actions { display: flex; gap: 12px; flex-wrap: wrap; }
            .btn {
                display: inline-flex;
                align-items: center;
                padding: 11px 22px;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
                transition: transform .12s ease, box-shadow .12s ease;
            }
            .btn:hover { transform: translateY(-1px); }
            .btn-primary {
                background: linear-gradient(135deg, var(--gold-soft), var(--gold));
                color: #3a2708;
                box-shadow: 0 8px 20px -8px rgba(224,169,60,0.9);
            }
            .btn-ghost {
                background: transparent;
                color: var(--ink);
                border: 1px solid rgba(26,26,0,0.16);
            }
            .meta {
                margin-top: 36px;
                font-size: 12px;
                color: var(--muted);
                letter-spacing: 0.02em;
            }
            @media (max-width: 760px) {
                .card { grid-template-columns: 1fr; max-width: 440px; }
                .brand { border-right: none; border-bottom: 1px solid rgba(110,75,37,0.10); padding: 40px; }
                .panel { padding: 40px 32px; }
            }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="brand">
                <img src="{{ asset('logo.jpg') }}" alt="{{ config('app.name', 'PhilSouth') }} logo">
                <div class="tag">Inventory Management</div>
            </div>
            <div class="panel">
                <h1>Welcome to <span>{{ config('app.name', 'PhilSouth') }}</span> Inventory</h1>
                <p>Track stock, manage items, and keep your operations running smoothly — all in one place.</p>
                <div class="actions">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary">Log in</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="btn btn-ghost">Register</a>
                            @endif
                        @endauth
                    @else
                        <a href="{{ url('/') }}" class="btn btn-primary">Get started</a>
                    @endif
                </div>
                <div class="meta">Laravel {{ Illuminate\Foundation\Application::VERSION }} · PHP {{ PHP_VERSION }}</div>
            </div>
        </div>
    </body>
</html>
