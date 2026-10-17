<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailTitle ?? 'EarthCoop' }}</title>
    <style>
        body{margin:0;padding:0;background:#f3f4f6;color:#1f2937;font-family:Tahoma,Arial,sans-serif;line-height:1.8}
        .ec-wrap{width:100%;padding:24px 12px;box-sizing:border-box}
        .ec-card{max-width:620px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden}
        .ec-header{background:#047857;padding:22px 24px;text-align:center;color:#fff}
        .ec-brand{display:inline-table;text-decoration:none;color:#fff}
        .ec-brand img{display:inline-block;vertical-align:middle;width:48px;height:auto;margin-left:10px}
        .ec-brand span{display:inline-block;vertical-align:middle;font-size:24px;font-weight:700;color:#fff}
        .ec-content{padding:28px 28px 24px}
        .ec-content h1,.ec-content h2{color:#065f46;line-height:1.45}
        .ec-content p{margin:0 0 16px}
        .ec-content ul,.ec-content ol{padding-right:22px}
        .ec-button{display:inline-block;background:#059669;color:#fff!important;text-decoration:none;border-radius:10px;padding:12px 22px;font-weight:700}
        .ec-box{background:#f0fdf4;border-right:4px solid #10b981;border-radius:10px;padding:14px 16px;margin:18px 0}
        .ec-note{background:#fffbeb;border-right:4px solid #f59e0b;border-radius:10px;padding:14px 16px;margin:18px 0}
        .ec-metrics{width:100%;border-collapse:collapse;margin:18px 0}
        .ec-metrics td{border-bottom:1px solid #e5e7eb;padding:9px 4px}
        .ec-footer{background:#f9fafb;border-top:1px solid #e5e7eb;padding:18px 24px;text-align:center;color:#6b7280;font-size:13px}
        .ec-footer a{color:#047857;text-decoration:none}
        @media(max-width:640px){.ec-wrap{padding:0}.ec-card{border-radius:0;border-left:0;border-right:0}.ec-content{padding:22px 18px}.ec-header{padding:18px}}
    </style>
</head>
<body>
<div class="ec-wrap">
    <div class="ec-card">
        <div class="ec-header">
            <a class="ec-brand" href="{{ url('/') }}" aria-label="EarthCoop">
                <img src="{{ asset('images/logo.png') }}" alt="EarthCoop">
                <span>EarthCoop</span>
            </a>
        </div>
        <div class="ec-content">
            @hasSection('content')
                @yield('content')
            @else
                {!! $bodyHtml ?? '' !!}
            @endif
        </div>
        <div class="ec-footer">
            <div>EarthCoop</div>
            <div><a href="{{ url('/') }}">ورود به EarthCoop</a></div>
        </div>
    </div>
</div>
</body>
</html>
