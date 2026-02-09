<form action="{{ route('profile.update.password') }}" method="POST">
    @csrf
    @method('PUT')
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Current Password -->
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-lock"></i>
                رمز فعلی
            </label>
            <input type="password" 
                   name="current_password" 
                   placeholder="رمز عبور فعلی خود را وارد کنید" 
                   class="form-input-enhanced" 
                   @error('current_password') style="border-color: #ef4444;" @enderror
                   required>
            @error('current_password')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
        </div>

        <!-- New Password -->
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-key"></i>
                رمز جدید
            </label>
            <input type="password" 
                   name="password" 
                   placeholder="رمز عبور جدید خود را وارد کنید" 
                   class="form-input-enhanced" 
                   @error('password') style="border-color: #ef4444;" @enderror
                   required>
            @error('password')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="form-group-enhanced md:col-span-2">
            <label class="form-label-enhanced">
                <i class="fas fa-lock"></i>
                تکرار رمز عبور جدید
            </label>
            <input type="password" 
                   name="password_confirmation" 
                   placeholder="تکرار رمز عبور جدید را وارد کنید" 
                   class="form-input-enhanced" 
                   @error('password_confirmation') style="border-color: #ef4444;" @enderror
                   required>
            @error('password_confirmation')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="flex justify-center mt-8">
        <button type="submit" class="submit-btn-enhanced">
            <i class="fas fa-save ml-2"></i>
            ذخیره تغییرات رمز عبور
        </button>
    </div>
</form>
