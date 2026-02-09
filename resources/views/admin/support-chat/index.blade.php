@extends('layouts.admin')

@section('title', 'مدیریت چت‌های پشتیبانی')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-comments ml-2"></i>
                مدیریت چت‌های پشتیبانی
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                مدیریت و پاسخگویی به چت‌های زنده کاربران
            </p>
        </div>
        <div class="flex items-center gap-3">
            <form action="{{ route('admin.support-chat.auto-assign') }}" method="POST" class="inline">
                @csrf
                <button type="submit" 
                        class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-sync-alt ml-2"></i>
                    توزیع خودکار
                </button>
            </form>
            <a href="{{ route('admin.tickets.index') }}" 
               class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                <i class="fas fa-ticket-alt ml-2"></i>
                تیکت‌ها
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6 flex items-center gap-3">
            <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
            <span class="text-green-800 dark:text-green-200">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6 flex items-center gap-3">
            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400"></i>
            <span class="text-red-800 dark:text-red-200">{{ session('error') }}</span>
        </div>
    @endif

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">در انتظار</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $stats['waiting'] }}</p>
                </div>
                <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                    <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">فعال</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $stats['active'] }}</p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <i class="fas fa-comments text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">چت‌های من</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $stats['my_active'] }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <i class="fas fa-user-tie text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">حل شده</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $stats['resolved'] }}</p>
                </div>
                <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <i class="fas fa-check-circle text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">بسته شده</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $stats['closed'] }}</p>
                </div>
                <div class="p-3 bg-gray-100 dark:bg-gray-900/30 rounded-lg">
                    <i class="fas fa-times-circle text-gray-600 dark:text-gray-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- فیلترها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <form method="GET" action="{{ route('admin.support-chat.index') }}" class="flex items-end gap-4 flex-wrap">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">جستجو</label>
                <input type="text" 
                       name="q" 
                       value="{{ request('q') }}"
                       placeholder="جستجو در موضوع، کاربر..."
                       class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">وضعیت</label>
                <select name="status" class="px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    <option value="waiting" {{ request('status') === 'waiting' ? 'selected' : '' }}>در انتظار</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>فعال</option>
                    <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>حل شده</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>بسته شده</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">پشتیبان</label>
                <select name="agent_id" class="px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>
                            {{ $agent->fullName() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">اولویت</label>
                <select name="priority" class="px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>پایین</option>
                    <option value="normal" {{ request('priority') === 'normal' ? 'selected' : '' }}>عادی</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>بالا</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" 
                        class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search ml-2"></i>
                    جستجو
                </button>
                <a href="{{ route('admin.support-chat.index') }}" 
                   class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    <i class="fas fa-times ml-2"></i>
                    پاک کردن
                </a>
            </div>
        </form>
    </div>

    <!-- جدول چت‌ها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">کاربر</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">موضوع</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">پشتیبان</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">اولویت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">آخرین فعالیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($chats as $chat)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-900 dark:text-white">
                                    {{ $chat->user->fullName() }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $chat->user->email }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $chat->subject ?? 'بدون موضوع' }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    {{ $chat->messages->count() }} پیام
                                    @if($chat->unreadMessagesCount() > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200 mr-1">
                                            {{ $chat->unreadMessagesCount() }} خوانده نشده
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($chat->agent)
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $chat->agent->fullName() }}
                                    </div>
                                @else
                                    <span class="text-xs text-yellow-600 dark:text-yellow-400">در انتظار</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($chat->status === 'waiting')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200">
                                        در انتظار
                                    </span>
                                @elseif($chat->status === 'active')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                        فعال
                                    </span>
                                @elseif($chat->status === 'resolved')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                        حل شده
                                    </span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/40 dark:text-gray-200">
                                        بسته شده
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($chat->priority === 'high')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">بالا</span>
                                @elseif($chat->priority === 'normal')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">عادی</span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/40 dark:text-gray-200">پایین</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                @if($chat->last_activity_at)
                                    {{ \Morilog\Jalali\Jalalian::fromCarbon($chat->last_activity_at)->format('Y/m/d H:i') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.support-chat.show', $chat->id) }}" 
                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 ml-3">
                                    <i class="fas fa-eye ml-1"></i>
                                    مشاهده
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                                <i class="fas fa-comments text-4xl mb-2"></i>
                                <p>هیچ چتی یافت نشد</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($chats->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $chats->links() }}
            </div>
        @endif
    </div>
</div>
@endsection




