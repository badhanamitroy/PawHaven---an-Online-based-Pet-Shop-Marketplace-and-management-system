<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name', 'PawHaven') }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #FAF8F2;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #202820;
            line-height: 1.6;
        }
        .wrapper {
            width: 100%;
            background-color: #FAF8F2;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(32, 40, 32, 0.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }
        .header {
            background-color: #173F32;
            padding: 32px 40px;
            text-align: center;
        }
        .brand-title {
            color: #ffffff;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin: 0;
            text-decoration: none;
        }
        .brand-subtitle {
            color: #A3D9C4;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 4px;
        }
        .content {
            padding: 40px;
        }
        .heading {
            font-size: 22px;
            font-weight: 800;
            color: #173F32;
            margin-top: 0;
            margin-bottom: 16px;
            line-height: 1.3;
        }
        .text {
            font-size: 15px;
            color: #4A564C;
            margin-bottom: 20px;
        }
        .otp-box {
            background: #FAF8F2;
            border: 2px dashed #245C45;
            border-radius: 14px;
            padding: 24px;
            text-align: center;
            margin: 28px 0;
        }
        .otp-code {
            font-family: 'SF Mono', Monaco, Consolas, monospace;
            font-size: 36px;
            font-weight: 800;
            color: #173F32;
            letter-spacing: 8px;
            margin: 0;
        }
        .otp-note {
            font-size: 12px;
            color: #69736B;
            margin-top: 8px;
            margin-bottom: 0;
        }
        .btn {
            display: inline-block;
            background-color: #245C45;
            color: #ffffff !important;
            padding: 14px 32px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            margin: 20px 0;
        }
        .info-card {
            background-color: #FAF8F2;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid rgba(32, 40, 32, 0.08);
            margin: 20px 0;
            font-size: 13px;
        }
        .footer {
            padding: 24px 40px;
            background-color: #FAF8F2;
            border-top: 1px solid rgba(32, 40, 32, 0.06);
            text-align: center;
            font-size: 12px;
            color: #69736B;
        }
        .footer-tagline {
            font-weight: 700;
            color: #173F32;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            {{-- Header --}}
            <div class="header">
                <h1 class="brand-title">{{ config('app.name', 'PawHaven') }}</h1>
                <div class="brand-subtitle">Pet Marketplace &amp; Ethical Pet Shop</div>
            </div>

            {{-- Main Content --}}
            <div class="content">
                @yield('content')
            </div>

            {{-- Footer --}}
            <div class="footer">
                <div class="footer-tagline">Find Pets. Give Hope. Shop with Love.</div>
                <div>Dedicated to ethical pet parenthood and lifelong companion care.</div>
                <div style="margin-top: 8px; font-size: 11px; opacity: 0.8;">
                    &copy; {{ date('Y') }} {{ config('app.name', 'PawHaven') }}. All rights reserved.
                </div>
            </div>
        </div>
    </div>
</body>
</html>

