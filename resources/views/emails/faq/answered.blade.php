@php
    $question = $question ?? null;
@endphp

<div style="direction: rtl; font-family: 'Vazirmatn', Tahoma, sans-serif; color: #1e293b; background: #f8fafc; padding: 32px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 15px 45px rgba(15,23,42,0.12);">
        <tr>
            <td style="padding: 32px; background: linear-gradient(135deg, #10b981, #3b82f6); color: #ffffff;">
                <h1 style="margin: 0; font-size: 24px;">پاسخ به سوال شما</h1>
                <p style="margin: 12px 0 0; font-size: 14px; opacity: 0.9;">با تشکر از اعتماد شما به EarthCoop</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 24px 32px;">
                <p style="font-size: 16px; line-height: 1.8; margin: 0 0 16px;">
                    سلام {{ $question?->contact_name ?? 'دوست عزیز' }}،
                </p>
                <p style="font-size: 15px; line-height: 1.8; margin: 0 0 24px;">
                    سوال شما با عنوان <strong>«{{ $question?->title }}»</strong> توسط تیم ما بررسی شد. پاسخ آن را می‌توانید در ادامه مشاهده کنید:
                </p>

                <div style="background: #f1f5f9; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;">
                    <h2 style="font-size: 16px; margin: 0 0 12px; color: #0f172a;">پاسخ:</h2>
                    <p style="font-size: 15px; line-height: 1.8; margin: 0; white-space: pre-line;">{{ $question?->answer }}</p>
                </div>

                <p style="font-size: 14px; line-height: 1.8; margin: 0 0 24px; color: #334155;">
                    در صورتی که سوال شما همچنان باقی است یا توضیح بیشتری نیاز دارید، همواره می‌توانید از طریق صفحه سوالات متداول یا فرم تماس با ما پیام ارسال کنید.
                </p>

                <div style="text-align: center; margin-bottom: 24px;">
                    <a href="{{ url('/pages/faq') }}" style="display: inline-block; padding: 12px 28px; background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-decoration: none; border-radius: 999px; font-weight: bold;">مشاهده صفحه سوالات متداول</a>
                </div>

                <p style="font-size: 13px; line-height: 1.7; color: #64748b; margin: 0;">
                    این پیام به صورت خودکار ارسال شده است. لطفاً به آن پاسخ ندهید. در صورت نیاز می‌توانید از طریق <a href="{{ url('/pages/contact') }}" style="color: #0ea5e9; text-decoration: none;">فرم تماس با ما</a> ارتباط برقرار کنید.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px 32px; background: #0f172a; color: #94a3b8; text-align: center; font-size: 12px;">
                &copy; {{ now()->year }} EarthCoop — تمامی حقوق محفوظ است.
            </td>
        </tr>
    </table>
</div>



