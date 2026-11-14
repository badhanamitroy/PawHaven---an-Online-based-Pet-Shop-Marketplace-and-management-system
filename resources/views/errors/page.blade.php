@php
    $brandName = \App\Facades\Settings::get('brand.name', config('app.name', 'PawHaven'));
    $isAdminRequest = request()->is('admin*');
    $error = match ($status) {
        403 => [
            'title' => 'Access denied',
            'message' => 'You don’t have permission to view this page.',
            'action' => 'Return to PawHaven',
            'url' => url('/'),
        ],
        404 => [
            'title' => 'Page not found',
            'message' => 'The page you’re looking for may have moved or is no longer available.',
            'action' => 'Return to PawHaven',
            'url' => url('/'),
        ],
        419 => [
            'title' => 'Your session has expired',
            'message' => 'For your security, please sign in again before continuing.',
            'action' => 'Sign in again',
            'url' => $isAdminRequest ? route('admin.login') : route('login'),
        ],
        429 => [
            'title' => 'Too many requests',
            'message' => 'You’ve made too many requests in a short time. Please wait a moment and try again.',
            'action' => 'Return to PawHaven',
            'url' => url('/'),
        ],
        503 => [
            'title' => 'Service temporarily unavailable',
            'message' => 'PawHaven is temporarily unavailable. Please try again in a little while.',
            'action' => 'Return to PawHaven',
            'url' => url('/'),
        ],
        default => [
            'title' => 'Internal server error',
            'message' => 'We couldn’t complete your request. Please try again shortly.',
            'action' => 'Return to PawHaven',
            'url' => url('/'),
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @include('partials.favicon')
    <title>{{ $error['title'] }} — {{ $brandName }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #202820;
            background: #faf8f2;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 28px 18px;
            background:
                radial-gradient(ellipse at 12% 8%, rgba(36, 92, 69, .09), transparent 34rem),
                radial-gradient(ellipse at 92% 92%, rgba(242, 140, 117, .10), transparent 30rem),
                #faf8f2;
        }

        main {
            width: min(100%, 560px);
            overflow: hidden;
            border: 1px solid rgba(32, 40, 32, .10);
            border-radius: 28px;
            background: #fff;
            box-shadow: 0 24px 70px rgba(23, 63, 50, .12);
            text-align: center;
        }

        .brand {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 78px;
            padding: 18px 24px;
            border-bottom: 1px solid rgba(32, 40, 32, .08);
        }

        .brand img { display: block; width: auto; height: 42px; max-width: 190px; object-fit: contain; }
        .brand a { display: flex; align-items: center; }
        .brand a:focus-visible { outline: 3px solid #f28c75; outline-offset: 3px; border-radius: 4px; }
        .content { padding: clamp(32px, 8vw, 58px) clamp(22px, 7vw, 52px) 48px; }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border: 1px solid rgba(36, 92, 69, .14);
            border-radius: 999px;
            background: #f0f6f1;
            color: #245c45;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
        }

        .status svg { width: 15px; height: 15px; }
        h1 { margin: 22px 0 10px; color: #173f32; font-size: clamp(27px, 6vw, 36px); line-height: 1.15; letter-spacing: -.035em; }
        p { max-width: 390px; margin: 0 auto; color: #69736b; font-size: 15px; line-height: 1.7; }

        a.action {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            min-height: 48px;
            margin-top: 30px;
            padding: 0 22px;
            border-radius: 12px;
            background: #173f32;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: background-color .15s ease, transform .15s ease;
        }

        a.action:hover { background: #245c45; transform: translateY(-1px); }
        a.action:focus-visible { outline: 3px solid #f28c75; outline-offset: 3px; }
        a.action svg { width: 17px; height: 17px; }

        @media (max-width: 420px) {
            main { border-radius: 20px; }
            .brand { min-height: 68px; }
            .brand img { height: 36px; }
        }
    </style>
</head>
<body>
    <main>
        <div class="brand">
            <a href="{{ url('/') }}" aria-label="PawHaven home">
                <img src="{{ asset(ltrim((string) \App\Facades\Settings::get('brand.logo_light_path', 'images/pawhaven-logo-green.png'), '/')) }}" alt="{{ $brandName }}">
            </a>
        </div>
        <section class="content" aria-labelledby="error-title">
            <div class="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5 19 6v5.5c0 4.3-2.8 7.6-7 9-4.2-1.4-7-4.7-7-9V6l7-2.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" />
                </svg>
                {{ strtoupper($brandName) }} · {{ $status }}
            </div>
            <h1 id="error-title">{{ $error['title'] }}</h1>
            <p>{{ $error['message'] }}</p>
            <a class="action" href="{{ $error['url'] }}">
                {{ $error['action'] }}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                </svg>
            </a>
        </section>
    </main>
</body>
</html>
