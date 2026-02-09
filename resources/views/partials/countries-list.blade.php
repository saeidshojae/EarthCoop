<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@php
            $countryCodes = [
                ['name' => 'ایران', 'code' => '+98', 'example' => '9123456789', 'flag' => '🇮🇷'],
                ['name' => 'آمریکا', 'code' => '+1', 'example' => '4151234567', 'flag' => '🇺🇸'],
                ['name' => 'انگلستان', 'code' => '+44', 'example' => '7123456789', 'flag' => '🇬🇧'],
                ['name' => 'آلمان', 'code' => '+49', 'example' => '1512345678', 'flag' => '🇩🇪'],
                ['name' => 'فرانسه', 'code' => '+33', 'example' => '612345678', 'flag' => '🇫🇷'],
                ['name' => 'ژاپن', 'code' => '+81', 'example' => '901234567', 'flag' => '🇯🇵'],
                ['name' => 'هند', 'code' => '+91', 'example' => '9123456789', 'flag' => '🇮🇳'],
                ['name' => 'ترکیه', 'code' => '+90', 'example' => '5012345678', 'flag' => '🇹🇷'],
                ['name' => 'مصر', 'code' => '+20', 'example' => '1012345678', 'flag' => '🇪🇬'],
                ['name' => 'عربستان', 'code' => '+966', 'example' => '501234567', 'flag' => '🇸🇦'],
                ['name' => 'امارات', 'code' => '+971', 'example' => '501234567', 'flag' => '🇦🇪'],
                ['name' => 'افغانستان', 'code' => '+93', 'example' => '701234567', 'flag' => '🇦🇫'],
                ['name' => 'آلبانی', 'code' => '+355', 'example' => '672345678', 'flag' => '🇦🇱'],
                ['name' => 'الجزایر', 'code' => '+213', 'example' => '551234567', 'flag' => '🇩🇿'],
                ['name' => 'آندورا', 'code' => '+376', 'example' => '312345', 'flag' => '🇦🇩'],
                ['name' => 'آنگولا', 'code' => '+244', 'example' => '923456789', 'flag' => '🇦🇴'],
                ['name' => 'آرژانتین', 'code' => '+54', 'example' => '91123456789', 'flag' => '🇦🇷'],
                ['name' => 'ارمنستان', 'code' => '+374', 'example' => '91234567', 'flag' => '🇦🇲'],
                ['name' => 'استرالیا', 'code' => '+61', 'example' => '412345678', 'flag' => '🇦🇺'],
                ['name' => 'اتریش', 'code' => '+43', 'example' => '6641234567', 'flag' => '🇦🇹'],
                ['name' => 'آذربایجان', 'code' => '+994', 'example' => '512345678', 'flag' => '🇦��'],
                ['name' => 'باهاما', 'code' => '+1-242', 'example' => '3591234', 'flag' => '🇧🇸'],
                ['name' => 'بحرین', 'code' => '+973', 'example' => '36001234', 'flag' => '🇧🇭'],
                ['name' => 'بنگلادش', 'code' => '+880', 'example' => '1712345678', 'flag' => '🇧🇩'],
                ['name' => 'باربادوس', 'code' => '+1-246', 'example' => '2501234', 'flag' => '🇧🇧'],
                ['name' => 'بلاروس', 'code' => '+375', 'example' => '291234567', 'flag' => '🇧🇾'],
                ['name' => 'بلژیک', 'code' => '+32', 'example' => '471234567', 'flag' => '🇧🇪'],
                ['name' => 'بلیز', 'code' => '+501', 'example' => '6221234', 'flag' => '🇧🇿'],
                ['name' => 'بنین', 'code' => '+229', 'example' => '90011234', 'flag' => '🇧🇯'],
                ['name' => 'بوتان', 'code' => '+975', 'example' => '17123456', 'flag' => '🇧🇹'],
                ['name' => 'بولیوی', 'code' => '+591', 'example' => '71234567', 'flag' => '🇧🇴'],
                ['name' => 'بوسنی و هرزگوین', 'code' => '+387', 'example' => '61123456', 'flag' => '🇧🇦'],
                ['name' => 'بوتسوانا', 'code' => '+267', 'example' => '71234567', 'flag' => '🇧🇼'],
                ['name' => 'برزیل', 'code' => '+55', 'example' => '11912345678', 'flag' => '🇧🇷'],
                ['name' => 'برونئی', 'code' => '+673', 'example' => '7123456', 'flag' => '🇧🇳'],
                ['name' => 'بلغارستان', 'code' => '+359', 'example' => '878123456', 'flag' => '🇧🇬'],
                ['name' => 'بورکینافاسو', 'code' => '+226', 'example' => '70123456', 'flag' => '🇧🇫'],
                ['name' => 'بوروندی', 'code' => '+257', 'example' => '79123456', 'flag' => '🇧🇮'],
                ['name' => 'کاپ‌ورد', 'code' => '+238', 'example' => '9911234', 'flag' => '🇨🇻'],
                ['name' => 'کامبوج', 'code' => '+855', 'example' => '91234567', 'flag' => '🇰🇭'],
                ['name' => 'کامرون', 'code' => '+237', 'example' => '671234567', 'flag' => '🇨🇲'],
                ['name' => 'کانادا', 'code' => '+1', 'example' => '4161234567', 'flag' => '🇨🇦'],
                ['name' => 'جمهوری آفریقای مرکزی', 'code' => '+236', 'example' => '70012345', 'flag' => '🇨🇫'],
                ['name' => 'چاد', 'code' => '+235', 'example' => '63012345', 'flag' => '🇹🇩'],
                ['name' => 'شیلی', 'code' => '+56', 'example' => '912345678', 'flag' => '🇨🇱'],
                ['name' => 'چین', 'code' => '+86', 'example' => '13123456789', 'flag' => '🇨🇳'],
                ['name' => 'کلمبیا', 'code' => '+57', 'example' => '3211234567', 'flag' => '🇨🇴'],
                ['name' => 'کومور', 'code' => '+269', 'example' => '3212345', 'flag' => '🇰🇲'],
                ['name' => 'کنگو (جمهوری دموکراتیک)', 'code' => '+243', 'example' => '991234567', 'flag' => '🇨🇩'],
                ['name' => 'کنگو (جمهوری)', 'code' => '+242', 'example' => '061234567', 'flag' => '🇨🇬'],
                ['name' => 'کاستاریکا', 'code' => '+506', 'example' => '83123456', 'flag' => '🇨🇷'],
                ['name' => 'کرواسی', 'code' => '+385', 'example' => '912345678', 'flag' => '🇭🇷'],
                ['name' => 'کوبا', 'code' => '+53', 'example' => '51234567', 'flag' => '🇨🇺'],
                ['name' => 'قبرس', 'code' => '+357', 'example' => '96123456', 'flag' => '🇨🇾'],
                ['name' => 'جمهوری چک', 'code' => '+420', 'example' => '601123456', 'flag' => '🇨🇿'],
                ['name' => 'دانمارک', 'code' => '+45', 'example' => '20123456', 'flag' => '🇩🇰'],
                ['name' => 'جیبوتی', 'code' => '+253', 'example' => '77831001', 'flag' => '🇩🇯'],
                ['name' => 'دومینیکا', 'code' => '+1-767', 'example' => '2251234', 'flag' => '🇩🇲'],
                ['name' => 'جمهوری دومینیکن', 'code' => '+1-809', 'example' => '2345678', 'flag' => '🇩🇴'],
                ['name' => 'اکوادور', 'code' => '+593', 'example' => '991234567', 'flag' => '🇪🇨'],
                ['name' => 'مصر', 'code' => '+20', 'example' => '1001234567', 'flag' => '🇪🇬'],
                ['name' => 'السالوادور', 'code' => '+503', 'example' => '70123456', 'flag' => '🇸🇻'],
                ['name' => 'گینه استوایی', 'code' => '+240', 'example' => '222123456', 'flag' => '🇬🇶'],
                ['name' => 'اریتره', 'code' => '+291', 'example' => '7123456', 'flag' => '🇪🇷'],
                ['name' => 'استونی', 'code' => '+372', 'example' => '51234567', 'flag' => '🇪🇪'],
                ['name' => 'اسواتینی', 'code' => '+268', 'example' => '76123456', 'flag' => '🇸🇿'],
            ];
            @endphp
            

            <style>
                .phone-input-wrapper {
                    position: relative;
                    display: flex;
                    align-items: stretch;
                    width: 100%;
                    gap: 0;
                }
                
                .country-code-select-wrapper {
                    position: relative;
                    flex-shrink: 0;
                    width: 160px;
                }
                
                .country-code-select {
                    appearance: none;
                    -webkit-appearance: none;
                    -moz-appearance: none;
                    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23334155' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
                    background-repeat: no-repeat;
                    background-position: left 14px center;
                    background-size: 12px;
                    padding-left: 50px;
                    padding-right: 14px;
                    cursor: pointer;
                    font-size: 14px;
                    height: 100%;
                    min-height: 48px;
                    border: 2px solid #e2e8f0;
                    border-left: 2px solid #e2e8f0;
                    border-right: none;
                    border-radius: 12px 0 0 12px;
                    background-color: #f8fafc;
                    transition: all 0.3s ease;
                    direction: ltr;
                    text-align: left;
                    width: 100%;
                    color: #1e293b;
                    font-weight: 500;
                }
                
                .country-code-select:hover {
                    background-color: #f1f5f9;
                    border-color: #cbd5e1;
                }
                
                .country-code-select:focus {
                    outline: none;
                    border-color: #3b82f6;
                    background-color: #ffffff;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }
                
                .phone-number-input {
                    flex: 1;
                    border: 2px solid #e2e8f0;
                    border-left: none;
                    border-right: 2px solid #e2e8f0;
                    border-radius: 0 12px 12px 0;
                    padding-right: 16px;
                    padding-left: 16px;
                    transition: all 0.3s ease;
                    min-height: 48px;
                }
                
                .phone-number-input:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }
                
                .phone-number-input.error {
                    border-color: #ef4444;
                    background-color: #fef2f2;
                }
                
                .country-flag-display {
                    position: absolute;
                    left: 14px;
                    top: 50%;
                    transform: translateY(-50%);
                    font-size: 24px;
                    line-height: 1;
                    pointer-events: none;
                    z-index: 10;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 32px;
                    height: 32px;
                }
                
                .country-code-text {
                    display: inline-block;
                    margin-right: 8px;
                }
                
                /* برای نمایش پرچم‌ها در dropdown */
                .country-code-select option {
                    direction: ltr;
                    text-align: left;
                    padding: 8px;
                    font-size: 14px;
                }
                
                @media (max-width: 768px) {
                    .country-code-select-wrapper {
                        width: 130px;
                    }
                    
                    .country-code-select {
                        font-size: 13px;
                        padding-right: 40px;
                        padding-left: 12px;
                    }
                    
                    .country-flag-display {
                        font-size: 20px;
                        left: 12px;
                    }
                }
            </style>
            
            <div class="form-group mt-3">
                <label for="phone" class="block text-lg font-bold text-gray-800 mb-3">
                    شماره تلفن: <span class="text-red-500">*</span>
                </label>
                <div class="phone-input-wrapper">
                    <input type="text" 
                           name="phone" 
                           id="phone" 
                           required
                           class="phone-number-input w-full px-4 py-3 text-right @error('phone') error @else border-gray-300 @enderror"
                           placeholder="برای مثال: 9123456789"
                           value="{{ old('phone') }}"
                           style="font-size: 16px;">
                    
                    <div class="country-code-select-wrapper">
                        <span class="country-flag-display" id="selected-flag">🇮🇷</span>
                        <select name="country_code" 
                                class="country-code-select" 
                                id="country_code" 
                                onchange="updatePlaceholder()">
                            @foreach ($countryCodes as $country)
                                <option value="{{ $country['code'] }}"
                                    data-flag="{{ $country['flag'] }}"
                                    data-placeholder="{{ $country['example'] }}"
                                    data-name="{{ $country['name'] }}"
                                    {{ old('country_code', '+98') == $country['code'] ? 'selected' : '' }}>
                                    {{ $country['flag'] }} {{ $country['name'] }} ({{ $country['code'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @error('phone')
                    <div class="mt-2 flex items-center text-red-600 text-sm">
                        <i class="fas fa-exclamation-triangle ml-2"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
                <p class="mt-2 text-sm text-gray-500">
                    <i class="fas fa-info-circle ml-1"></i>
                    شماره تلفن باید ۱۰ رقم باشد و با ۹ شروع شود (بدون صفر ابتدایی)
                </p>
            </div>
            
            <script>
                function updatePlaceholder() {
                    const select = document.getElementById('country_code');
                    const phoneInput = document.getElementById('phone');
                    const selectedOption = select.options[select.selectedIndex];
                    const placeholder = selectedOption.getAttribute('data-placeholder');
                    const flag = selectedOption.getAttribute('data-flag');
                    const name = selectedOption.getAttribute('data-name');
                    
                    if (placeholder) {
                        phoneInput.placeholder = 'برای مثال: ' + placeholder;
                    }
                    
                    // به‌روزرسانی پرچم نمایش داده شده
                    const flagDisplay = document.getElementById('selected-flag');
                    if (flagDisplay && flag) {
                        flagDisplay.textContent = flag;
                    }
                    
                    // به‌روزرسانی متن select برای نمایش بهتر
                    // در برخی مرورگرها emoji در option نمایش داده نمی‌شود
                    // پس فقط نام و کد را نمایش می‌دهیم
                }
                
                // به‌روزرسانی پرچم هنگام لود صفحه
                document.addEventListener('DOMContentLoaded', function() {
                    updatePlaceholder();
                    
                    // اطمینان از نمایش پرچم اولیه
                    const select = document.getElementById('country_code');
                    if (select) {
                        const selectedOption = select.options[select.selectedIndex];
                        const flag = selectedOption ? selectedOption.getAttribute('data-flag') : '🇮🇷';
                        const flagDisplay = document.getElementById('selected-flag');
                        if (flagDisplay) {
                            flagDisplay.textContent = flag || '🇮🇷';
                        }
                    }
                });
            </script>