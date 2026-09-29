<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دانلود اپلیکیشن EarthCoop</title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: Tahoma, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #f4f6f8;
            color: #17202a;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .card {
            width: min(100%, 620px);
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, .08);
        }
        h1 { margin: 0 0 10px; font-size: 1.8rem; }
        p { line-height: 1.9; }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            background: #fff3cd;
            color: #664d03;
            font-size: .9rem;
            margin-bottom: 14px;
        }
        .meta {
            margin: 18px 0;
            padding: 14px 16px;
            background: #f8fafc;
            border-radius: 12px;
            line-height: 1.9;
        }
        .download {
            display: block;
            text-decoration: none;
            text-align: center;
            font-weight: 700;
            padding: 14px 18px;
            border-radius: 12px;
            background: #155eef;
            color: #fff;
            margin: 22px 0 14px;
        }
        .note { font-size: .92rem; color: #52606d; }
        ol { padding-right: 22px; line-height: 2; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #111827; border-color: #263244; }
            .meta { background: #172033; }
            .note { color: #b8c2cc; }
            .badge { background: #4b3b07; color: #fff2b3; }
        }
    </style>
</head>
<body>
<main class="card">
    <span class="badge">نسخه آزمایشی / UAT</span>
    <h1>اپلیکیشن EarthCoop</h1>
    <p>
        این نسخه برای آزمایش عملی M6 روی دستگاه واقعی Android ارائه شده است و هنوز نسخه نهایی انتشار در فروشگاه‌ها نیست.
    </p>

    <div class="meta">
        <strong>Android UAT</strong><br>
        نسخه برنامه: 0.1.0<br>
        حداقل Android: 7.0
    </div>

    <a class="download" href="/downloads/mobile/earthcoop-android-uat.apk" download>
        دانلود نسخه Android
    </a>

    <p class="note">
        این فایل مخصوص آزمون پذیرش روی دستگاه واقعی است. در صورت درخواست Android/Huawei، اجازه نصب برنامه از این منبع را فقط برای همین نصب فعال کنید.
    </p>

    <h2>روش نصب</h2>
    <ol>
        <li>فایل APK را دانلود کنید.</li>
        <li>فایل دانلودشده را باز کنید.</li>
        <li>در صورت نمایش پیام امنیتی، اجازه نصب از مرورگر یا File Manager را برای همین منبع فعال کنید.</li>
        <li>پس از نصب، EarthCoop را باز کنید و آزمون UAT را آغاز کنید.</li>
    </ol>
</main>
</body>
</html>
