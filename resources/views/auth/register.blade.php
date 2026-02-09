@extends('layouts.unified')

@section('title', 'ثبت‌نام - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .register-page {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .register-background {
        position: relative;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem 1rem;
    }

    .register-background::before,
    .register-background::after {
        content: '';
        position: absolute;
        border-radius: 9999px;
        filter: blur(60px);
        opacity: 0.12;
        pointer-events: none;
    }

    .register-background::before {
        top: 4rem;
        left: 5rem;
        width: 16rem;
        height: 16rem;
        background: #ffffff;
    }

    .register-background::after {
        bottom: 5rem;
        right: 5rem;
        width: 22rem;
        height: 22rem;
        background: #facc15;
    }

    .register-glow {
        position: absolute;
        top: 50%;
        right: 4rem;
        width: 18rem;
        height: 18rem;
        background: #fb923c;
        opacity: 0.12;
        border-radius: 9999px;
        filter: blur(70px);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .glass-effect {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .input-focus:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .slide-in {
        animation: slideIn 0.6s ease-out;
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .float-animation {
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
    }

    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-15px); }
    }
</style>
@endpush

@section('content')
<div class="register-page">
    <div class="register-background">
        <span class="register-glow"></span>
        <div class="w-full max-w-lg relative slide-in">
            <div class="text-center mb-8">
                <a href="{{ route('welcome') }}" class="inline-flex items-center justify-center gap-4 mb-4 text-white">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="animate-bounce" style="animation: bounce 3s infinite ease-in-out;">
                        <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/>
                        <path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/>
                    </svg>
                    <span class="text-3xl font-extrabold tracking-wide drop-shadow-lg">EarthCoop</span>
                </a>
                <h1 class="text-4xl font-bold text-white mb-2">عضویت در زمین نو</h1>
                <p class="text-purple-100 text-lg">مشخصات خود را وارد کنید</p>
            </div>

            <div class="glass-effect rounded-3xl shadow-2xl p-8">
                <form action="{{ route('register.process') }}" method="POST" class="space-y-6">
                    @csrf

                    @if(isset($_GET['code']))
                        <input type="hidden" name="invation_code" value="{{ $_GET['code'] }}">
                        <div class="bg-green-50 border-r-4 border-green-500 p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 text-xl ml-3"></i>
                                <div>
                                    <p class="text-green-700 font-bold">کد دعوت تایید شد</p>
                                    <p class="text-green-600 text-sm mt-1">کد: <span class="font-mono font-bold">{{ $_GET['code'] }}</span></p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label for="email" class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-envelope text-blue-500 ml-2"></i>
                            آدرس ایمیل
                        </label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="w-full px-4 py-3 border-2 @error('email') border-red-500 @else border-gray-300 @enderror rounded-xl input-focus transition outline-none"
                            placeholder="example@email.com"
                            required
                            autofocus
                        >
                        @error('email')
                            <p class="text-red-500 text-sm mt-2 bg-red-50 p-2 rounded-lg">
                                <i class="fas fa-exclamation-circle ml-1"></i>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-lock text-blue-500 ml-2"></i>
                            رمز عبور
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="w-full px-4 py-3 border-2 @error('password') border-red-500 @else border-gray-300 @enderror rounded-xl input-focus transition outline-none pl-12"
                                placeholder="حداقل 6 کاراکتر"
                                required
                            >
                            <button
                                type="button"
                                onclick="togglePasswordVisibility('password', 'toggleIcon1')"
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 transition"
                            >
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="text-red-500 text-sm mt-2 bg-red-50 p-2 rounded-lg">
                                <i class="fas fa-exclamation-circle ml-1"></i>
                                {{ $message }}
                            </p>
                        @enderror
                        <p class="text-gray-500 text-xs mt-2">
                            <i class="fas fa-info-circle ml-1"></i>
                            رمز عبور باید حداقل 6 کاراکتر باشد
                        </p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-lock-open text-blue-500 ml-2"></i>
                            تکرار رمز عبور
                        </label>
                        <div class="relative">
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl input-focus transition outline-none pl-12"
                                placeholder="رمز عبور را دوباره وارد کنید"
                                required
                            >
                            <button
                                type="button"
                                onclick="togglePasswordVisibility('password_confirmation', 'toggleIcon2')"
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 transition"
                            >
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full py-4 rounded-xl font-bold text-lg text-white shadow-lg hover:shadow-xl transform hover:scale-105 transition duration-300"
                        style="background: linear-gradient(to right, #10b981, #059669);"
                    >
                        <i class="fas fa-user-plus ml-2"></i>
                        ادامه ثبت‌نام
                    </button>

                    <a href="{{ route('google.login') }}"
                       class="block w-full py-3 bg-gray-900 hover:bg-gray-800 text-white rounded-xl font-medium text-center transition duration-300 shadow-lg">
                        <div class="flex items-center justify-center">
                            <img src="https://cdn1.iconfinder.com/data/icons/google-s-logo/150/Google_Icons-09-512.png"
                                 alt="Google"
                                 class="w-6 h-6 ml-3 bg-white rounded p-1">
                            <span>عضویت با حساب گوگل</span>
                        </div>
                    </a>

                    <div class="relative my-6">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-4 bg-white text-gray-500">یا</span>
                        </div>
                    </div>

                    <div class="text-center">
                        <p class="text-gray-600">
                            قبلاً ثبت‌نام کرده‌اید؟
                            <a href="{{ route('login') }}" class="text-blue-500 hover:text-blue-700 font-bold transition">
                                ورود به سامانه
                                <i class="fas fa-sign-in-alt mr-1"></i>
                            </a>
                        </p>
                    </div>
                </form>
            </div>

            <div class="text-center mt-8">
                <p class="text-purple-100 text-sm">
                    © {{ now()->year }} EarthCoop - تمامی حقوق محفوظ است
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function togglePasswordVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>
@endpush
