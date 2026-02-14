@extends('layouts.admin')

@section('title', 'تنظیمات نجم بهار')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">تنظیمات نجم بهار</h1>
            <p class="text-slate-600 mt-2">تنظیمات اولیه سیستم نجم بهار</p>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                {{ session('success') }}
            </div>
        @endif

        <!-- Settings Form -->
        <div class="bg-white rounded-lg shadow-md p-8">
            <form action="{{ route('admin.najm-bahar.settings.update') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Initial Amount -->
                <div class="space-y-2">
                    <label for="najm_bahar_initial_amount" class="block text-sm font-semibold text-slate-700">
                        مقدار اولیه واریز (بهار)
                    </label>
                    <div class="relative">
                        <input
                            type="number"
                            step="0.01"
                            id="najm_bahar_initial_amount"
                            name="najm_bahar_initial_amount"
                            value="{{ $settings->najm_bahar_initial_amount ? \App\Helpers\BaharMoney::formatDecimalValue($settings->najm_bahar_initial_amount) : '' }}"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                            placeholder="مثال: 10000.00"
                        />
                        <span class="absolute left-4 top-2.5 text-slate-500 text-sm">بهار</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        این مقدار برای تمام کاربران جدید هنگام ثبت‌نام واریز می‌شود.
                    </p>
                    @error('najm_bahar_initial_amount')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Active Type Selection -->
                <div class="space-y-4 border-t pt-6">
                    <h3 class="text-lg font-semibold text-slate-800">نحوه تعیین موجودی فعال در واریز اولیه</h3>
                    
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                name="najm_bahar_initial_active_type"
                                value="percentage"
                                {{ ($settings->najm_bahar_initial_active_type ?? 'percentage') === 'percentage' ? 'checked' : '' }}
                                onchange="toggleActiveType()"
                                class="w-4 h-4 text-blue-600"
                            />
                            <span class="text-slate-700">درصدی</span>
                        </label>
                        
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                name="najm_bahar_initial_active_type"
                                value="fixed_amount"
                                {{ ($settings->najm_bahar_initial_active_type ?? 'percentage') === 'fixed_amount' ? 'checked' : '' }}
                                onchange="toggleActiveType()"
                                class="w-4 h-4 text-blue-600"
                            />
                            <span class="text-slate-700">مبلغ ثابت</span>
                        </label>
                    </div>

                    <!-- Percentage Mode -->
                    <div id="percentage-mode" class="space-y-2">
                        <label for="najm_bahar_initial_active_percentage" class="block text-sm font-semibold text-slate-700">
                            درصد موجودی فعال (اکتیو)
                        </label>
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <input
                                    type="range"
                                    id="najm_bahar_initial_active_percentage"
                                    name="najm_bahar_initial_active_percentage"
                                    min="0"
                                    max="100"
                                    value="{{ $settings->najm_bahar_initial_active_percentage ?? 30 }}"
                                    class="w-full h-2 bg-slate-300 rounded-lg appearance-none cursor-pointer"
                                    oninput="updatePreview()"
                                />
                            </div>
                            <div class="w-16 text-center">
                                <span id="percentage-display" class="text-2xl font-bold text-blue-600">
                                    {{ $settings->najm_bahar_initial_active_percentage ?? 30 }}
                                </span>
                                <span class="text-sm text-slate-600">%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Fixed Amount Mode -->
                    <div id="fixed-amount-mode" class="space-y-2" style="display: none;">
                        <label for="najm_bahar_initial_active_fixed_amount" class="block text-sm font-semibold text-slate-700">
                            مبلغ ثابت موجودی فعال (بهار)
                        </label>
                        <div class="relative">
                            <input
                                type="number"
                                step="0.01"
                                id="najm_bahar_initial_active_fixed_amount"
                                name="najm_bahar_initial_active_fixed_amount"
                                value="{{ $settings->najm_bahar_initial_active_fixed_amount ? \App\Helpers\BaharMoney::formatDecimalValue($settings->najm_bahar_initial_active_fixed_amount) : '0' }}"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="مثال: 50.00"
                                oninput="updatePreview()"
                            />
                            <span class="absolute left-4 top-2.5 text-slate-500 text-sm">بهار</span>
                        </div>
                        <p class="text-xs text-slate-500">در این حالت، همیشه این مقدار به عنوان موجودی فعال در نظر گرفته می‌شود</p>
                    </div>

                    <!-- Preview -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-2">
                        <p class="text-xs text-slate-600 mb-2">پیش‌نمایش برای مبلغ واریز اولیه:</p>
                        <p class="text-sm text-blue-900"><strong>موجودی فعال:</strong> <span id="active-preview">0.00</span> بهار</p>
                        <p class="text-sm text-blue-900"><strong>موجودی کمرنگ:</strong> <span id="faded-preview">0.00</span> بهار</p>
                    </div>
                    
                    @error('najm_bahar_initial_active_percentage')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                    @error('najm_bahar_initial_active_fixed_amount')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Auto Activation Settings -->
                <div class="space-y-4 border-t pt-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">فعال‌سازی خودکار دوره‌ای</h3>
                            <p class="text-sm text-slate-600 mt-1">تبدیل خودکار موجودی کمرنگ به فعال در دوره‌های زمانی مشخص</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                name="najm_bahar_auto_activation_enabled"
                                value="1"
                                {{ ($settings->najm_bahar_auto_activation_enabled ?? false) ? 'checked' : '' }}
                                onchange="toggleAutoActivation()"
                                class="sr-only peer"
                            />
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div id="auto-activation-settings" style="display: {{ ($settings->najm_bahar_auto_activation_enabled ?? false) ? 'block' : 'none' }};" class="space-y-4 bg-slate-50 p-4 rounded-lg">
                        <!-- Period Selection -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-slate-700">دوره فعال‌سازی</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="najm_bahar_auto_activation_period"
                                        value="monthly"
                                        {{ ($settings->najm_bahar_auto_activation_period ?? 'monthly') === 'monthly' ? 'checked' : '' }}
                                        class="w-4 h-4 text-blue-600"
                                    />
                                    <span class="text-slate-700">ماهانه</span>
                                </label>
                                
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="najm_bahar_auto_activation_period"
                                        value="yearly"
                                        {{ ($settings->najm_bahar_auto_activation_period ?? 'monthly') === 'yearly' ? 'checked' : '' }}
                                        class="w-4 h-4 text-blue-600"
                                    />
                                    <span class="text-slate-700">سالانه</span>
                                </label>
                            </div>
                        </div>

                        <!-- Activation Amount -->
                        <div class="space-y-2">
                            <label for="najm_bahar_auto_activation_amount" class="block text-sm font-semibold text-slate-700">
                                مقدار فعال‌سازی در هر دوره (بهار)
                            </label>
                            <div class="relative">
                                <input
                                    type="number"
                                    step="0.01"
                                    id="najm_bahar_auto_activation_amount"
                                    name="najm_bahar_auto_activation_amount"
                                    value="{{ $settings->najm_bahar_auto_activation_amount ? \App\Helpers\BaharMoney::formatDecimalValue($settings->najm_bahar_auto_activation_amount) : '0' }}"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="مثال: 100.00"
                                />
                                <span class="absolute left-4 top-2.5 text-slate-500 text-sm">بهار</span>
                            </div>
                            <p class="text-xs text-slate-500">
                                این مقدار در هر دوره از موجودی کمرنگ کاربران کسر شده و به موجودی فعال اضافه می‌شود
                            </p>
                        </div>

                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <p class="text-sm text-amber-900">
                                <strong>⚠️ توجه:</strong> این عملیات روی تمام کاربران اعمال می‌شود. اگر موجودی کمرنگ کاربر کافی نباشد، تمام موجودی کمرنگ او فعال می‌شود.
                            </p>
                        </div>
                    </div>

                    @error('najm_bahar_auto_activation_amount')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Reputation Conversion Settings -->
                <div class="space-y-4 border-t pt-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">تبدیل امتیازات به پول اکتیو</h3>
                            <p class="text-sm text-slate-600 mt-1">کاربران می‌توانند امتیازات خود را به پول فعال تبدیل کنند</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                name="reputation_conversion_enabled"
                                value="1"
                                {{ ($settings->reputation_conversion_enabled ?? true) ? 'checked' : '' }}
                                onchange="toggleReputationConversion()"
                                class="sr-only peer"
                            />
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>

                    <div id="reputation-conversion-settings" style="display: {{ ($settings->reputation_conversion_enabled ?? true) ? 'block' : 'none' }};" class="space-y-4 bg-slate-50 p-4 rounded-lg">
                        <!-- Conversion Ratio -->
                        <div class="space-y-2">
                            <label for="reputation_to_gol_ratio" class="block text-sm font-semibold text-slate-700">
                                نسبت تبدیل امتیاز به گل
                            </label>
                            <div class="relative">
                                <input
                                    type="number"
                                    id="reputation_to_gol_ratio"
                                    name="reputation_to_gol_ratio"
                                    min="1"
                                    value="{{ $settings->reputation_to_gol_ratio ?? 100 }}"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                    placeholder="100"
                                    oninput="updateReputationPreview()"
                                />
                            </div>
                            <p class="text-xs text-slate-500">
                                <strong>مثال:</strong> اگر ۱۰۰ وارد کنید، یعنی هر ۱۰۰ امتیاز = ۱ گل (۱۰۰ بهار)
                            </p>
                        </div>

                        <!-- Preview -->
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                            <p class="text-sm text-purple-900">
                                <strong>نسبت تبدیل:</strong> هر <span id="ratio-display">{{ $settings->reputation_to_gol_ratio ?? 100 }}</span> امتیاز = ۱ گل (۱۰۰ بهار)
                            </p>
                            <p class="text-xs text-purple-700 mt-2">
                                مثال: کاربر با <strong>۵۰۰ امتیاز</strong> می‌تواند تا <strong id="example-conversion">۵</strong> گل (<strong id="example-bahar">۵۰۰</strong> بهار) دریافت کند
                            </p>
                        </div>

                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <p class="text-sm text-amber-900">
                                <strong>⚠️ توجه:</strong> امتیازات نقد شده از کاربر حذف نمی‌شوند، فقط "کمرنگ" می‌شوند و دیگر قابل نقد مجدد نیستند. معادل ارزش امتیازات از موجودی کمرنگ کسر و به موجودی فعال اضافه می‌شود.
                            </p>
                        </div>
                    </div>

                    @error('reputation_to_gol_ratio')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Info Box -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 space-y-2">
                    <h3 class="font-semibold text-yellow-900">توضیحات</h3>
                    <ul class="text-sm text-yellow-800 space-y-1">
                        <li>• <strong>موجودی فعال (اکتیو):</strong> کاربر می‌تواند بلافاصله استفاده کند</li>
                        <li>• <strong>موجودی کمرنگ (فیدد):</strong> پس از گذشت مدت زمان تعیین‌شده، فعال می‌شود</li>
                        <li>• <strong>درصدی:</strong> موجودی فعال بر اساس درصد از مبلغ واریز اولیه محاسبه می‌شود</li>
                        <li>• <strong>مبلغ ثابت:</strong> همیشه مقدار مشخصی به عنوان موجودی فعال در نظر گرفته می‌شود</li>
                        <li>• <strong>فعال‌سازی دوره‌ای:</strong> به صورت خودکار در دوره‌های زمانی مشخص، از موجودی کمرنگ به فعال منتقل می‌شود</li>
                        <li>• <strong>تبدیل امتیازات:</strong> کاربران می‌توانند امتیازات خود را به پول فعال تبدیل کنند (امتیازات کمرنگ می‌شوند، معادل ارزش از موجودی کمرنگ به فعال منتقل می‌شود)</li>
                    </ul>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-4 pt-4">
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition"
                    >
                        لغو
                    </a>
                    <button
                        type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold"
                    >
                        ذخیره تنظیمات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleActiveType() {
        const type = document.querySelector('input[name="najm_bahar_initial_active_type"]:checked').value;
        const percentageMode = document.getElementById('percentage-mode');
        const fixedAmountMode = document.getElementById('fixed-amount-mode');
        
        if (type === 'percentage') {
            percentageMode.style.display = 'block';
            fixedAmountMode.style.display = 'none';
        } else {
            percentageMode.style.display = 'none';
            fixedAmountMode.style.display = 'block';
        }
        
        updatePreview();
    }

    function toggleAutoActivation() {
        const enabled = document.querySelector('input[name="najm_bahar_auto_activation_enabled"]').checked;
        const settings = document.getElementById('auto-activation-settings');
        settings.style.display = enabled ? 'block' : 'none';
    }
toggleReputationConversion() {
        const enabled = document.querySelector('input[name="reputation_conversion_enabled"]').checked;
        const settings = document.getElementById('reputation-conversion-settings');
        settings.style.display = enabled ? 'block' : 'none';
    }

    function updateReputationPreview() {
        const ratio = parseInt(document.getElementById('reputation_to_gol_ratio').value) || 100;
        document.getElementById('ratio-display').textContent = ratio;
        
        const examplePoints = 500;
        const gols = Math.floor(examplePoints / ratio);
        const bahars = gols * 100;
        
        document.getElementById('example-conversion').textContent = gols;
        document.getElementById('example-bahar').textContent = bahars;
    }

    function 
    function updatePreview() {
        const initialAmount = parseFloat(
            document.getElementById('najm_bahar_initial_amount').value || '0'
        );
        
        if (initialAmount <= 0) {
            document.getElementById('active-preview').textContent = '0.00';
            document.getElementById('faded-preview').textContent = '0.00';
            return;
        }

        const type = document.querySelector('input[name="najm_bahar_initial_active_type"]:checked').value;
        let activeAmount = 0;

        if (type === 'percentage') {
            const percentage = document.getElementById('najm_bahar_initial_active_percentage').value;
            document.getElementById('percentage-display').textContent = percentage;
            activeAmount = (initialAmount * percentage) / 100;
        } else {
            activeAmount = parseFloat(
                document.getElementById('najm_bahar_initial_active_fixed_amount').value || '0'
            );
            // Limit active amount to initial amount
        toggleReputationConversion();
        updatePreview();
        updateReputation (activeAmount > initialAmount) {
                activeAmount = initialAmount;
            }
        }

        const fadedAmount = Math.max(0, initialAmount - activeAmount);

        document.getElementById('active-preview').textContent = activeAmount.toFixed(2);
        document.getElementById('faded-preview').textContent = fadedAmount.toFixed(2);
    }

    // Event listeners
    document.getElementById('najm_bahar_initial_amount').addEventListener('input', updatePreview);

    // Initial setup
    window.addEventListener('load', function() {
        toggleActiveType();
        toggleAutoActivation();
        updatePreview();
    });
</script>
@endsection

