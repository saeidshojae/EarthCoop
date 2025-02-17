<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'لاراول') }}</title>

    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Sahel Font -->
    <link href="https://cdn.fontcdn.ir/Font/Persian/Sahel/Sahel.css" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand fw-bold" href="{{ url('/') }}" style="font-family: 'Sahel', sans-serif;">
                    {{ config('app.name', 'لاراول') }}
                </a>
            </div>
        </nav>

        <main class="py-4">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card shadow-lg rounded">
                            <div class="card-header bg-primary text-white text-center py-3">
                                <h4 class="mb-0"><i class="fas fa-hand-wave me-2"></i>خوش آمدید</h4>
                            </div>

                            <div class="card-body">
                                <div class="text-center mt-4">
                                    <h5 class="fw-bold text-dark mb-3">
                                        <i class="fas fa-envelope-open-text me-2"></i>
                                        لطفا ایمیل یا شماره موبایل خود را تایید کنید
                                    </h5>
                                    <p class="text-muted">
                                        برای ادامه، لطفا اطلاعات تماس خود را وارد کرده و قوانین را بپذیرید
                                    </p>
                                </div>

                                <form method="POST" action="{{ url('/verify') }}">
                                    @csrf
                                    <div class="row mb-3 align-items-center">
                                        <label for="email_or_phone" class="col-md-4 col-form-label text-md-start fw-medium">
                                            ایمیل یا موبایل
                                        </label>
                                        <div class="col-md-6">
                                            <input 
                                                id="email_or_phone" 
                                                type="text" 
                                                class="form-control text-end" 
                                                name="email_or_phone" 
                                                required 
                                                autofocus
                                                placeholder="0912xxx یا example@domain.com"
                                            >
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6 offset-md-4">
                                            <div class="form-check text-end">
                                                <input 
                                                    class="form-check-input" 
                                                    type="checkbox" 
                                                    name="terms" 
                                                    id="terms" 
                                                    required
                                                >
                                                <label class="form-check-label me-2" for="terms">
                                                    <i class="fas fa-file-signature me-2"></i>
                                                    شرایط و قوانین را می‌پذیرم
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-0 mt-4">
                                        <div class="col-md-6 offset-md-4">
                                            <button type="submit" class="btn btn-primary w-100 py-2">
                                                <i class="fas fa-check-circle me-2"></i>
                                                تایید و ادامه
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>