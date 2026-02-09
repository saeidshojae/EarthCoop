@extends('layouts.admin')

@section('title', 'جزئیات تیکت')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('admin.tickets.index') }}" 
               class="inline-flex items-center text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white mb-2">
                <i class="fas fa-arrow-right ml-2"></i>
                بازگشت به لیست تیکت‌ها
            </a>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-ticket-alt ml-2"></i>
                جزئیات تیکت
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                کد پیگیری: <span class="font-mono font-semibold">{{ $ticket->tracking_code }}</span>
            </p>
        </div>
        <div class="flex items-center gap-3">
            @if($ticket->status != 'closed')
                <form action="{{ route('admin.tickets.close', $ticket->id) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" 
                            onclick="return confirm('آیا مطمئن هستید که می‌خواهید این تیکت را ببندید؟')"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                        <i class="fas fa-times-circle ml-2"></i>
                        بستن تیکت
                    </button>
                </form>
            @endif
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- ستون اصلی -->
        <div class="lg:col-span-2 space-y-6">
            <!-- اطلاعات تیکت -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-2">
                            {{ $ticket->subject }}
                        </h2>
                        <div class="flex items-center gap-3 flex-wrap">
                            @if($ticket->status == 'open')
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200">
                                    <i class="fas fa-clock ml-1"></i>
                                    باز
                                </span>
                            @elseif($ticket->status == 'in-progress')
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                    <i class="fas fa-spinner ml-1"></i>
                                    در حال بررسی
                                </span>
                            @else
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                    <i class="fas fa-check-circle ml-1"></i>
                                    بسته شده
                                </span>
                            @endif

                            @if($ticket->priority == 'high')
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">
                                    <i class="fas fa-exclamation-triangle ml-1"></i>
                                    اولویت بالا
                                </span>
                            @elseif($ticket->priority == 'low')
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/40 dark:text-gray-200">
                                    <i class="fas fa-arrow-down ml-1"></i>
                                    اولویت پایین
                                </span>
                            @else
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                    اولویت عادی
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="prose dark:prose-invert max-w-none">
                    <div class="text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                        {{ $ticket->message }}
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-slate-500 dark:text-slate-400">تاریخ ایجاد:</span>
                            <span class="text-slate-900 dark:text-white font-medium mr-2">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($ticket->created_at)->format('Y/m/d H:i') }}
                            </span>
                        </div>
                        @if($ticket->updated_at != $ticket->created_at)
                            <div>
                                <span class="text-slate-500 dark:text-slate-400">آخرین بروزرسانی:</span>
                                <span class="text-slate-900 dark:text-white font-medium mr-2">
                                    {{ \Morilog\Jalali\Jalalian::fromCarbon($ticket->updated_at)->format('Y/m/d H:i') }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- پاسخ‌ها -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-comments ml-2"></i>
                    پاسخ‌ها ({{ $ticket->comments->count() }})
                </h3>

                <div class="space-y-4">
                    @forelse($ticket->comments as $comment)
                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 {{ $comment->user_id == auth()->id() ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-semibold">
                                        {{ $comment->user ? substr($comment->user->fullName(), 0, 1) : '?' }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-white">
                                            {{ $comment->user ? $comment->user->fullName() : 'کاربر' }}
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ \Morilog\Jalali\Jalalian::fromCarbon($comment->created_at)->format('Y/m/d H:i') }}
                                        </div>
                                    </div>
                                </div>
                                @if($comment->user_id == auth()->id())
                                    <span class="inline-flex px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                        شما
                                    </span>
                                @endif
                            </div>
                            <div class="text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                                {{ $comment->message }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-slate-500 dark:text-slate-400">
                            <i class="fas fa-comment-slash text-4xl mb-3"></i>
                            <p>هنوز پاسخی ثبت نشده است</p>
                        </div>
                    @endforelse
                </div>

                <!-- فرم پاسخ -->
                @if($ticket->status != 'closed')
                    <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                        <h4 class="text-md font-semibold text-slate-900 dark:text-white mb-4">
                            <i class="fas fa-reply ml-2"></i>
                            ارسال پاسخ
                        </h4>
                        <form action="{{ route('admin.tickets.reply', $ticket->id) }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <textarea 
                                    name="message" 
                                    rows="5"
                                    required
                                    placeholder="پاسخ خود را بنویسید..."
                                    class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white resize-none"></textarea>
                                @error('message')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex items-center justify-end gap-3">
                                <button type="submit" 
                                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                    <i class="fas fa-paper-plane ml-2"></i>
                                    ارسال پاسخ
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                        <div class="bg-slate-100 dark:bg-slate-700 rounded-lg p-4 text-center text-slate-600 dark:text-slate-400">
                            <i class="fas fa-lock ml-2"></i>
                            این تیکت بسته شده است و امکان ارسال پاسخ وجود ندارد
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- ستون کناری -->
        <div class="space-y-6">
            <!-- اطلاعات کاربر -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-user ml-2"></i>
                    اطلاعات کاربر
                </h3>
                <div class="space-y-3">
                    <div>
                        <span class="text-sm text-slate-500 dark:text-slate-400">نام:</span>
                        <div class="text-slate-900 dark:text-white font-medium mt-1">
                            {{ $ticket->user ? $ticket->user->fullName() : ($ticket->name ?? '-') }}
                        </div>
                    </div>
                    @if($ticket->email)
                        <div>
                            <span class="text-sm text-slate-500 dark:text-slate-400">ایمیل:</span>
                            <div class="text-slate-900 dark:text-white font-medium mt-1">
                                <a href="mailto:{{ $ticket->email }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ $ticket->email }}
                                </a>
                            </div>
                        </div>
                    @endif
                    @if($ticket->phone)
                        <div>
                            <span class="text-sm text-slate-500 dark:text-slate-400">تلفن:</span>
                            <div class="text-slate-900 dark:text-white font-medium mt-1">
                                <a href="tel:{{ $ticket->phone }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ $ticket->phone }}
                                </a>
                            </div>
                        </div>
                    @endif
                    @if($ticket->user)
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700">
                            <a href="{{ route('admin.users.show', $ticket->user->id) }}" 
                               class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-user-circle ml-2"></i>
                                مشاهده پروفایل کاربر
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- اختصاص مسئول -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-user-tie ml-2"></i>
                    مسئول تیکت
                </h3>
                <form action="{{ route('admin.tickets.assign', $ticket->id) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <select name="assignee_id" 
                                class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                            <option value="">بدون مسئول</option>
                            @foreach($operators as $op)
                                <option value="{{ $op->id }}" {{ $ticket->assignee_id == $op->id ? 'selected' : '' }}>
                                    {{ $op->fullName() }} ({{ $op->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" 
                            class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <i class="fas fa-save ml-2"></i>
                        ذخیره
                    </button>
                </form>
                @if($ticket->assignee)
                    <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-green-600 text-white flex items-center justify-center font-semibold">
                                {{ substr($ticket->assignee->fullName(), 0, 1) }}
                            </div>
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white">
                                    {{ $ticket->assignee->fullName() }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $ticket->assignee->email }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- اطلاعات تیکت -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-info-circle ml-2"></i>
                    اطلاعات تیکت
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">کد پیگیری:</span>
                        <span class="text-slate-900 dark:text-white font-mono font-semibold">{{ $ticket->tracking_code }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">وضعیت:</span>
                        <span class="text-slate-900 dark:text-white font-medium">
                            @if($ticket->status == 'open')
                                باز
                            @elseif($ticket->status == 'in-progress')
                                در حال بررسی
                            @else
                                بسته شده
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">اولویت:</span>
                        <span class="text-slate-900 dark:text-white font-medium">
                            {{ $ticket->priority == 'high' ? 'بالا' : ($ticket->priority == 'low' ? 'پایین' : 'عادی') }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">تعداد پاسخ‌ها:</span>
                        <span class="text-slate-900 dark:text-white font-medium">{{ $ticket->comments->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">تاریخ ایجاد:</span>
                        <span class="text-slate-900 dark:text-white font-medium">
                            {{ \Morilog\Jalali\Jalalian::fromCarbon($ticket->created_at)->format('Y/m/d') }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">ساعت ایجاد:</span>
                        <span class="text-slate-900 dark:text-white font-medium">
                            {{ \Morilog\Jalali\Jalalian::fromCarbon($ticket->created_at)->format('H:i') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
