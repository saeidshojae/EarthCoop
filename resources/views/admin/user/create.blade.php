@extends('layouts.admin')

@section('title', 'ایجاد کاربر جدید - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'ایجاد کاربر جدید')
@section('page-description', 'ایجاد کاربر جدید در سیستم')

@push('styles')
<style>
    .user-form-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .user-form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .user-form-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .user-form-group {
        margin-bottom: 1.5rem;
    }
    
    .user-form-label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .user-form-label .required {
        color: #ef4444;
    }
    
    .user-form-input, .user-form-select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
    }
    
    .user-form-input:focus, .user-form-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .user-form-input.error, .user-form-select.error {
        border-color: #ef4444;
    }
    
    .user-form-error {
        font-size: 0.75rem;
        color: #ef4444;
        margin-top: 0.25rem;
    }
    
    .user-form-help {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
    
    .user-birth-date-wrapper {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }
    
    .user-form-submit {
        padding: 0.75rem 2rem;
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        justify-content: center;
    }
    
    .user-form-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .user-form-back {
        padding: 0.75rem 1.5rem;
        background: #f3f4f6;
        color: #374151;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        width: 100%;
        justify-content: center;
        margin-top: 0.5rem;
    }
    
    .user-form-back:hover {
        background: #e5e7eb;
        color: #1f2937;
    }
    
    .user-error-alert {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 1rem;
        border-radius: 0.75rem;
        margin-bottom: 2rem;
    }
    
    .user-error-alert ul {
        margin: 0;
        padding-right: 1.5rem;
    }
    
    .user-error-alert li {
        margin-bottom: 0.5rem;
    }
    
    @media (prefers-color-scheme: dark) {
        .user-form-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .user-form-header h3 {
            color: #f1f5f9 !important;
        }
        
        .user-form-label {
            color: #f1f5f9 !important;
        }
        
        .user-form-input, .user-form-select {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .user-form-input:focus, .user-form-select:focus {
            border-color: #3b82f6 !important;
        }
        
        .user-form-input.error, .user-form-select.error {
            border-color: #ef4444 !important;
        }
        
        .user-form-help {
            color: #94a3b8 !important;
        }
        
        .user-form-back {
            background: #334155 !important;
            color: #f1f5f9 !important;
        }
        
        .user-form-back:hover {
            background: #475569 !important;
        }
        
        .user-error-alert {
            background: #450a0a !important;
            border-color: #b91c1c !important;
            color: #fecaca !important;
        }
    }
    
    @media (max-width: 768px) {
        .user-form-card {
            padding: 1rem;
        }
        
        .user-form-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .user-birth-date-wrapper {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <!-- Header -->
    <div class="user-form-card">
        <div class="user-form-header">
            <h3>
                <i class="fas fa-user-plus ml-2"></i>
                ایجاد کاربر جدید
            </h3>
            <a href="{{ route('admin.users.index') }}" class="user-form-back">
                <i class="fas fa-arrow-right"></i>
                بازگشت
            </a>
        </div>
    </div>
    
    @if($errors->any())
    <div class="user-form-card">
        <div class="user-error-alert">
            <strong>خطاهای زیر رخ داد:</strong>
            <ul>
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif
    
    <form action="{{ route('admin.users.store') }}" method="POST">
        @csrf
        
        <div class="user-form-card">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Name -->
                <div class="user-form-group">
                    <label for="first_name" class="user-form-label">
                        نام <span class="required">*</span>
                    </label>
                    <input type="text" 
                           class="user-form-input @error('first_name') error @enderror" 
                           id="first_name" 
                           name="first_name" 
                           value="{{ old('first_name') }}" 
                           required
                           placeholder="نام را وارد کنید">
                    @error('first_name')
                    <div class="user-form-error">{{ $message }}</div>
                    @enderror
                </div>
                
                <!-- Last Name -->
                <div class="user-form-group">
                    <label for="last_name" class="user-form-label">
                        نام خانوادگی <span class="required">*</span>
                    </label>
                    <input type="text" 
                           class="user-form-input @error('last_name') error @enderror" 
                           id="last_name" 
                           name="last_name" 
                           value="{{ old('last_name') }}" 
                           required
                           placeholder="نام خانوادگی را وارد کنید">
                    @error('last_name')
                    <div class="user-form-error">{{ $message }}</div>
                    @enderror
                </div>
                
                <!-- Email -->
                <div class="user-form-group">
                    <label for="email" class="user-form-label">
                        ایمیل <span class="required">*</span>
                    </label>
                    <input type="email" 
                           class="user-form-input @error('email') error @enderror" 
                           id="email" 
                           name="email" 
                           value="{{ old('email') }}" 
                           required
                           placeholder="example@email.com">
                    @error('email')
                    <div class="user-form-error">{{ $message }}</div>
                    @enderror
                </div>
                
                <!-- Phone -->
                <div class="user-form-group">
                    <label for="phone" class="user-form-label">
                        شماره تماس <span class="required">*</span>
                    </label>
                    <input type="text" 
                           class="user-form-input @error('phone') error @enderror" 
                           id="phone" 
                           name="phone" 
                           value="{{ old('phone') }}" 
                           required
                           placeholder="09123456789">
                    <div class="user-form-help">فرمت: 09123456789</div>
                    @error('phone')
                    <div class="user-form-error">{{ $message }}</div>
                    @enderror
                </div>
                
                <!-- National ID -->
                <div class="user-form-group">
                    <label for="national_id" class="user-form-label">
                        کد ملی <span class="required">*</span>
                    </label>
                    <input type="text" 
                           class="user-form-input @error('national_id') error @enderror" 
                           id="national_id" 
                           name="national_id" 
                           value="{{ old('national_id') }}" 
                           required
                           placeholder="1234567890"
                           maxlength="10">
                    <div class="user-form-help">10 رقم</div>
                    @error('national_id')
                    <div class="user-form-error">{{ $message }}</div>
                    @enderror
                </div>
                
                <!-- Gender -->
                <div class="user-form-group">
                    <label for="gender" class="user-form-label">
                        جنسیت <span class="required">*</span>
                    </label>
                    <select class="user-form-select @error('gender') error @enderror" 
                            id="gender" 
                            name="gender" 
                            required>
                        <option value="">انتخاب کنید...</option>
                        <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>مرد</option>
                        <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>زن</option>
                    </select>
                    @error('gender')
                    <div class="user-form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <!-- Birth Date -->
            <div class="user-form-group">
                <label for="birth_date" class="user-form-label">
                    تاریخ تولد <span class="required">*</span>
                </label>
                <div class="user-birth-date-wrapper">
                    <div>
                        <select name="birth_date[]" 
                                class="user-form-select @error('birth_date') error @enderror" 
                                required>
                            <option value="">روز</option>
                            @for ($i = 1; $i <= 31; $i++)
                            <option value="{{ $i }}" {{ old('birth_date.0') == $i ? 'selected' : '' }}>
                                {{ $i }}
                            </option>
                            @endfor
                        </select>
                    </div>
                    
                    <div>
                        <select name="birth_date[]" 
                                class="user-form-select @error('birth_date') error @enderror" 
                                required>
                            <option value="">ماه</option>
                            <option value="1" {{ old('birth_date.1') == 1 ? 'selected' : '' }}>فروردین</option>
                            <option value="2" {{ old('birth_date.1') == 2 ? 'selected' : '' }}>اردیبهشت</option>
                            <option value="3" {{ old('birth_date.1') == 3 ? 'selected' : '' }}>خرداد</option>
                            <option value="4" {{ old('birth_date.1') == 4 ? 'selected' : '' }}>تیر</option>
                            <option value="5" {{ old('birth_date.1') == 5 ? 'selected' : '' }}>مرداد</option>
                            <option value="6" {{ old('birth_date.1') == 6 ? 'selected' : '' }}>شهریور</option>
                            <option value="7" {{ old('birth_date.1') == 7 ? 'selected' : '' }}>مهر</option>
                            <option value="8" {{ old('birth_date.1') == 8 ? 'selected' : '' }}>آبان</option>
                            <option value="9" {{ old('birth_date.1') == 9 ? 'selected' : '' }}>آذر</option>
                            <option value="10" {{ old('birth_date.1') == 10 ? 'selected' : '' }}>دی</option>
                            <option value="11" {{ old('birth_date.1') == 11 ? 'selected' : '' }}>بهمن</option>
                            <option value="12" {{ old('birth_date.1') == 12 ? 'selected' : '' }}>اسفند</option>
                        </select>
                    </div>
                    
                    <div>
                        @php
                        use Morilog\Jalali\Jalalian;
                        $currentYear = Jalalian::now()->getYear() - 15;
                        $startYear = $currentYear - 135;
                        @endphp
                        <select name="birth_date[]" 
                                class="user-form-select @error('birth_date') error @enderror" 
                                required>
                            <option value="">سال</option>
                            @for ($i = $currentYear; $i >= $startYear; $i--)
                            <option value="{{ $i }}" {{ old('birth_date.2') == $i ? 'selected' : '' }}>
                                {{ $i }}
                            </option>
                            @endfor
                        </select>
                    </div>
                </div>
                @error('birth_date')
                <div class="user-form-error">{{ $message }}</div>
                @enderror
            </div>
            
            <!-- Password -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="user-form-group">
                    <label for="password" class="user-form-label">
                        رمز عبور <span class="required">*</span>
                    </label>
                    <input type="password" 
                           class="user-form-input @error('password') error @enderror" 
                           id="password" 
                           name="password" 
                           required
                           placeholder="حداقل 6 کاراکتر">
                    @error('password')
                    <div class="user-form-error">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="user-form-group">
                    <label for="password_confirmation" class="user-form-label">
                        تایید رمز عبور <span class="required">*</span>
                    </label>
                    <input type="password" 
                           class="user-form-input" 
                           id="password_confirmation" 
                           name="password_confirmation" 
                           required
                           placeholder="تکرار رمز عبور">
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="user-form-group">
                <button type="submit" class="user-form-submit">
                    <i class="fas fa-save"></i>
                    ایجاد کاربر
                </button>
                <a href="{{ route('admin.users.index') }}" class="user-form-back">
                    <i class="fas fa-times"></i>
                    انصراف
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
