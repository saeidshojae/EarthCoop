<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailTitle ?? 'EarthCoop' }}</title>
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            src: url("{{ asset('fonts/Vazirmatn-Regular.woff2') }}") format('woff2');
            font-style: normal;
            font-weight: 400;
        }
        @font-face {
            font-family: 'Vazirmatn';
            src: url("{{ asset('fonts/Vazirmatn-Bold.woff2') }}") format('woff2');
            font-style: normal;
            font-weight: 700;
        }
        body {
            margin: 0;
            padding: 0;
            background: #f3f4f6;
            color: #1f2937;
            font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
            line-height: 1.9;
            direction: rtl;
            text-align: right;
        }
        .ec-wrap {
            width: 100%;
            padding: 24px 12px;
            box-sizing: border-box;
            direction: rtl;
            text-align: right;
        }
        .ec-card {
            max-width: 620px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
        }
        .ec-header {
            background: #e0f2fe;
            padding: 22px 24px;
            text-align: center;
            color: #075985;
            direction: rtl;
        }
        .ec-brand {
            display: inline-block;
            text-decoration: none;
            color: #075985;
            direction: ltr;
            font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
        }
        .ec-brand img {
            display: inline-block;
            vertical-align: middle;
            width: 48px;
            height: 48px;
            margin-right: 10px;
        }
        .ec-brand span {
            display: inline-block;
            vertical-align: middle;
            font-size: 24px;
            font-weight: 700;
            color: #075985;
        }
        .ec-content {
            padding: 28px 28px 24px;
            direction: rtl;
            text-align: right;
            font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
        }
        .ec-content h1, .ec-content h2 {
            color: #065f46;
            line-height: 1.6;
            direction: rtl;
            text-align: right;
            font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
        }
        .ec-content p, .ec-content li {
            direction: rtl;
            text-align: right;
            font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
        }
        .ec-content p { margin: 0 0 16px; }
        .ec-content ul, .ec-content ol {
            padding-right: 22px;
            padding-left: 0;
            direction: rtl;
            text-align: right;
        }
        .ec-content a {
            color: #047857;
            overflow-wrap: anywhere;
        }
        .ec-button {
            display: inline-block;
            background: #059669;
            color: #fff !important;
            text-decoration: none;
            border-radius: 10px;
            padding: 12px 22px;
            font-weight: 700;
            font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
            text-align: center;
        }
        .ec-box {
            background: #f0fdf4;
            border-right: 4px solid #10b981;
            border-radius: 10px;
            padding: 14px 16px;
            margin: 18px 0;
            direction: rtl;
            text-align: right;
        }
        .ec-note {
            background: #fffbeb;
            border-right: 4px solid #f59e0b;
            border-radius: 10px;
            padding: 14px 16px;
            margin: 18px 0;
            direction: rtl;
            text-align: right;
        }
        .ec-metrics {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0;
            direction: rtl;
        }
        .ec-metrics td {
            border-bottom: 1px solid #e5e7eb;
            padding: 9px 4px;
            text-align: right;
            direction: rtl;
        }
        .ec-footer {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 18px 24px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
            direction: rtl;
            font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
        }
        .ec-footer a { color: #047857; text-decoration: none; }
        @media(max-width:640px) {
            .ec-wrap { padding: 0; }
            .ec-card { border-radius: 0; border-left: 0; border-right: 0; }
            .ec-content { padding: 22px 18px; }
            .ec-header { padding: 18px; }
        }
    </style>
</head>
<body dir="rtl" style="direction:rtl;text-align:right;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;">
<div class="ec-wrap" dir="rtl" style="direction:rtl;text-align:right;">
    <div class="ec-card">
        <div class="ec-header" dir="rtl" style="background:#e0f2fe;color:#075985;text-align:center;">
            <a class="ec-brand" href="{{ url('/') }}" aria-label="EarthCoop" style="color:#075985;text-decoration:none;">
                <img src="{{ asset('icons/earthcoop-brand-192.png') }}" alt="نشان رسمی EarthCoop" width="48" height="48" style="display:inline-block;vertical-align:middle;width:48px;height:48px;">
                <span style="display:inline-block;vertical-align:middle;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;font-size:24px;font-weight:700;color:#075985;">EarthCoop</span>
            </a>
        </div>
        <div class="ec-content" dir="rtl" align="right" style="direction:rtl;text-align:right;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;">
            @hasSection('content')
                @yield('content')
            @else
                {!! $bodyHtml ?? '' !!}
            @endif
        </div>
        <div class="ec-footer" dir="rtl" style="direction:rtl;text-align:center;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;">
            <div>EarthCoop</div>
            <div><a href="{{ url('/') }}">ورود به EarthCoop</a></div>
        </div>
    </div>
</div>
</body>
</html>
