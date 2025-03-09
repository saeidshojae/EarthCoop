<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title>خوش آمدید</title>
  <link href="{{ asset('css/app.css') }}" rel="stylesheet" />
  <script>
    async function captureFingerprint() {
      try {
        const publicKey = {
          challenge: new Uint8Array(32), // یک چالش تصادفی
          rp: {
            name: "نام ارائه‌دهنده"
          },
          user: {
            id: new Uint8Array(16), // شناسه کاربر
            name: "نام کاربر",
            displayName: "نمایش نام کاربر"
          },
          pubKeyCredParams: [
            { type: "public-key", alg: -7 } // ES256
          ],
          timeout: 60000, // زمان تایم اوت
          attestation: "direct"
        };

        const credential = await navigator.credentials.create({ publicKey });
        const fingerprint = credential.response.rawId; // اثر انگشت کاربر

        // ذخیره اثر انگشت در فیلد
        document.getElementById('fingerprint_id').value = Array.from(new Uint8Array(fingerprint)).map(b => String.fromCharCode(b)).join('');
      } catch (error) {
        console.error("خطا در جمع‌آوری اثر انگشت:", error);
      }
    }
  </script>
</head>
<body>
  <div class="container mt-5">
    <h1 class="mb-4">به برنامه ثبت‌نام چندمرحله‌ای خوش آمدید</h1>
    <form action="{{ route('register.accept') }}" method="POST">
      @csrf
      <div class="form-group">
        <label for="fingerprint_id">اثر انگشت (شناسه):</label>
        <input type="text" name="fingerprint_id" id="fingerprint_id" class="form-control">
        <button type="button" onclick="captureFingerprint()" class="btn btn-secondary mt-2">جمع‌آوری اثر انگشت</button>
      </div>
      <div class="form-check mt-3">
        <input type="checkbox" name="agreement" id="agreement" class="form-check-input" required>
        <label for="agreement" class="form-check-label">موافقت با قوانین و مقررات</label>
      </div>
      <button type="submit" class="btn btn-primary mt-3">تایید و ادامه</button>
    </form>
  </div>
</body>
</html>
