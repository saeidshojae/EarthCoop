@extends('layouts.admin')

@section('title', 'داشبورد مهماندار - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'داشبورد مهماندار')
@section('page-description', 'یکپارچه‌سازی منابع محتوایی')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-12">
            <div class="flex items-center justify-center gap-3 mb-4">
                <span class="text-4xl">👨‍✈️</span>
                <h1 class="text-4xl font-bold text-gray-900">مهماندار نجم‌هدا</h1>
            </div>
            <p class="text-lg text-gray-600">یکپارچه‌سازی منابع محتوایی</p>
        </div>

        <!-- Main Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Knowledge Base</p>
                        <p class="text-3xl font-bold text-blue-600">20</p>
                        <p class="text-xs text-gray-500 mt-1">مقاله</p>
                    </div>
                    <span class="text-4xl">📚</span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Blog Posts</p>
                        <p class="text-3xl font-bold text-emerald-600">14</p>
                        <p class="text-xs text-gray-500 mt-1">پست</p>
                    </div>
                    <span class="text-4xl">📝</span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">FAQ Questions</p>
                        <p class="text-3xl font-bold text-purple-600">0</p>
                        <p class="text-xs text-gray-500 mt-1">سوال</p>
                    </div>
                    <span class="text-4xl">❓</span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">کل منابع</p>
                        <p class="text-3xl font-bold text-indigo-600">34</p>
                        <p class="text-xs text-gray-500 mt-1">محتوا</p>
                    </div>
                    <span class="text-4xl">✨</span>
                </div>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Knowledge Base -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-3xl">📚</span>
                    <h2 class="text-xl font-bold text-gray-900">پایگاه دانش</h2>
                </div>
                <div class="space-y-3">
                    <p class="text-gray-600 text-sm">20 مقاله تفصیلی در 18 دسته</p>
                    <ul class="text-sm text-gray-700 space-y-2">
                        <li>✓ راهنماهای مرحله‌ای</li>
                        <li>✓ معرفی ویژگی‌ها</li>
                        <li>✓ حل مشکلات متداول</li>
                        <li>✓ نکات امنیتی</li>
                    </ul>
                    <a href="/support/knowledge-base" class="inline-block mt-3 text-blue-600 font-semibold text-sm hover:underline">
                        مشاهده → 
                    </a>
                </div>
            </div>

            <!-- Blog -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-3xl">📝</span>
                    <h2 class="text-xl font-bold text-gray-900">وبلاگ</h2>
                </div>
                <div class="space-y-3">
                    <p class="text-gray-600 text-sm">14 پست در گروه‌های مختلف</p>
                    <ul class="text-sm text-gray-700 space-y-2">
                        <li>✓ اخبار و اطلاعیه‌ها</li>
                        <li>✓ نکات و ترفندها</li>
                        <li>✓ تجربیات کاربران</li>
                        <li>✓ داستان‌های موفقیت</li>
                    </ul>
                    <p class="text-xs text-gray-500 mt-3">
                        از گروه‌های مختلف منتشر می‌شود
                    </p>
                </div>
            </div>

            <!-- FAQ -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-3xl">❓</span>
                    <h2 class="text-xl font-bold text-gray-900">سوالات متداول</h2>
                </div>
                <div class="space-y-3">
                    <p class="text-gray-600 text-sm">سوالات رایج و پاسخ‌های مختصر</p>
                    <ul class="text-sm text-gray-700 space-y-2">
                        <li>✓ سوالات متداول</li>
                        <li>✓ پاسخ‌های سریع</li>
                        <li>✓ دسته‌بندی شده</li>
                        <li>✓ قابل جستجو</li>
                    </ul>
                    <p class="text-xs text-gray-500 mt-3">
                        هنوز سوالی اضافه نشده
                    </p>
                </div>
            </div>
        </div>

        <!-- Integration Architecture -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">🔄 جریان یکپارچه‌سازی</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Step 1 -->
                <div class="flex flex-col items-center text-center">
                    <div class="w-12 h-12 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-lg mb-3">
                        1
                    </div>
                    <p class="font-semibold text-gray-800 mb-2">محتوا تغییر</p>
                    <p class="text-sm text-gray-600">KB / Blog / FAQ</p>
                </div>

                <!-- Arrow -->
                <div class="flex items-center justify-center text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>

                <!-- Step 2 -->
                <div class="flex flex-col items-center text-center">
                    <div class="w-12 h-12 rounded-full bg-green-500 text-white flex items-center justify-center font-bold text-lg mb-3">
                        2
                    </div>
                    <p class="font-semibold text-gray-800 mb-2">Observer شنید</p>
                    <p class="text-sm text-gray-600">Event رخ می‌دهد</p>
                </div>

                <!-- Arrow -->
                <div class="flex items-center justify-center text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>

                <!-- Step 3 -->
                <div class="flex flex-col items-center text-center">
                    <div class="w-12 h-12 rounded-full bg-purple-500 text-white flex items-center justify-center font-bold text-lg mb-3">
                        3
                    </div>
                    <p class="font-semibold text-gray-800 mb-2">Cache پاک</p>
                    <p class="text-sm text-gray-600">فوری</p>
                </div>

                <!-- Arrow -->
                <div class="flex items-center justify-center text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>

                <!-- Step 4 -->
                <div class="flex flex-col items-center text-center">
                    <div class="w-12 h-12 rounded-full bg-indigo-500 text-white flex items-center justify-center font-bold text-lg mb-3">
                        4
                    </div>
                    <p class="font-semibold text-gray-800 mb-2">Steward آپدیت</p>
                    <p class="text-sm text-gray-600">محتوای جدید</p>
                </div>
            </div>
        </div>

        <!-- Capabilities -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <!-- Search Capabilities -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">🔍 قابلیت جستجو</h3>
                
                <div class="space-y-4">
                    <div class="flex gap-4">
                        <span class="text-2xl">📚</span>
                        <div>
                            <p class="font-semibold text-gray-800">Knowledge Base</p>
                            <p class="text-sm text-gray-600">جستجو در عنوان و خلاصه</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <span class="text-2xl">📝</span>
                        <div>
                            <p class="font-semibold text-gray-800">Blog Posts</p>
                            <p class="text-sm text-gray-600">جستجو در عنوان و محتوا</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <span class="text-2xl">❓</span>
                        <div>
                            <p class="font-semibold text-gray-800">FAQ</p>
                            <p class="text-sm text-gray-600">جستجو در سوال و پاسخ</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Observer System -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">🛡️ سیستم Observers</h3>
                
                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <span class="text-lg mt-1">✅</span>
                        <div>
                            <p class="font-semibold text-gray-800">KbArticleObserver</p>
                            <p class="text-sm text-gray-600">مراقبت‌گر پایگاه دانش</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-3">
                        <span class="text-lg mt-1">✅</span>
                        <div>
                            <p class="font-semibold text-gray-800">BlogObserver</p>
                            <p class="text-sm text-gray-600">مراقبت‌گر پست‌های وبلاگ</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-3">
                        <span class="text-lg mt-1">✅</span>
                        <div>
                            <p class="font-semibold text-gray-800">FaqQuestionObserver</p>
                            <p class="text-sm text-gray-600">مراقبت‌گر سوالات متداول</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Stats -->
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg shadow-lg p-8 text-white mb-8">
            <h2 class="text-2xl font-bold mb-6">⚡ عملکرد</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <p class="text-3xl font-bold">0-5ms</p>
                    <p class="text-sm mt-2 opacity-90">زمان جواب (از Cache)</p>
                </div>
                
                <div class="text-center">
                    <p class="text-3xl font-bold">3600s</p>
                    <p class="text-sm mt-2 opacity-90">Cache TTL (1 ساعت)</p>
                </div>
                
                <div class="text-center">
                    <p class="text-3xl font-bold">3</p>
                    <p class="text-sm mt-2 opacity-90">منبع جستجو</p>
                </div>
                
                <div class="text-center">
                    <p class="text-3xl font-bold">9</p>
                    <p class="text-sm mt-2 opacity-90">حداکثر نتایج</p>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="text-center">
            <a href="{{ route('support.kb.index') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition mr-4 mb-4">
                📚 پایگاه دانش →
            </a>
            <a href="/admin/kb/steward-dashboard" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg transition">
                ⚙️ تنظیمات Admin →
            </a>
        </div>
    </div>
</div>
@endsection

