@extends('layouts.admin')

@section('title', 'جزئیات گزارش چت خصوصی')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-shield-alt ml-2"></i>
                جزئیات گزارش چت خصوصی
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">بررسی پیام، زمینه گفتگو و ثبت تصمیم نهایی</p>
        </div>
        <a href="{{ route('admin.private-chat-reports') }}" class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
            <i class="fas fa-arrow-right ml-2"></i>
            بازگشت به لیست
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">اطلاعات گزارش</h2>
                    @php
                        $statusClasses = [
                            'pending' => 'bg-amber-100 text-amber-800',
                            'reviewed' => 'bg-blue-100 text-blue-800',
                            'resolved' => 'bg-emerald-100 text-emerald-800',
                            'dismissed' => 'bg-rose-100 text-rose-800',
                        ];
                    @endphp
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $statusClasses[$report->status] ?? 'bg-slate-100 text-slate-700' }}">
                        {{ \App\Models\PrivateChatReport::STATUSES[$report->status] ?? $report->status }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="text-slate-500 dark:text-slate-400">دلیل</div>
                        <div class="font-semibold text-slate-900 dark:text-white">{{ \App\Models\PrivateChatReport::REASONS[$report->reason] ?? $report->reason }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500 dark:text-slate-400">زمان ثبت</div>
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $report->created_at?->format('Y/m/d H:i') }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500 dark:text-slate-400">گزارش‌دهنده</div>
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $report->reporter?->fullName() ?? $report->reporter?->email ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500 dark:text-slate-400">کاربر گزارش‌شده</div>
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $report->reportedUser?->fullName() ?? $report->reportedUser?->email ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500 dark:text-slate-400">بررسی‌کننده</div>
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $report->reviewer?->fullName() ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500 dark:text-slate-400">زمان بررسی</div>
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $report->reviewed_at?->format('Y/m/d H:i') ?? '-' }}</div>
                    </div>
                </div>

                @if($report->description)
                    <div class="mt-5 p-4 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
                        <div class="text-sm text-slate-500 dark:text-slate-400 mb-2">توضیحات گزارش‌دهنده</div>
                        <div class="text-slate-800 dark:text-slate-200 leading-7">{{ $report->description }}</div>
                    </div>
                @endif
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">زمینه گفتگو</h2>
                <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
                    @foreach($report->conversation?->messages ?? [] as $message)
                        <div class="p-4 rounded-xl border {{ $message->id === $report->reported_message_id ? 'border-rose-300 bg-rose-50 dark:bg-rose-900/10' : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30' }}">
                            <div class="flex items-center justify-between gap-3 mb-2 text-sm">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $message->sender?->fullName() ?? $message->sender?->email ?? 'کاربر' }}</div>
                                <div class="text-slate-500 dark:text-slate-400">{{ $message->created_at?->format('Y/m/d H:i') }}</div>
                            </div>
                            <div class="text-slate-700 dark:text-slate-200 leading-7">{{ $message->message }}</div>
                            @if($message->id === $report->reported_message_id)
                                <div class="mt-2 text-xs font-semibold text-rose-600 dark:text-rose-400">این پیام گزارش شده است</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">رسیدگی ادمین</h2>
                <form method="POST" action="{{ route('admin.private-chat-reports.review', $report->id) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">وضعیت نهایی</label>
                        <select name="status" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                            @foreach(\App\Models\PrivateChatReport::STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected($report->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="text-sm text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">یادداشت ادمین</label>
                        <textarea name="admin_notes" rows="6" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" placeholder="نتیجه بررسی، اقدامات انجام شده یا توضیح تکمیلی...">{{ old('admin_notes', $report->admin_notes) }}</textarea>
                        @error('admin_notes')<div class="text-sm text-rose-600 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-semibold">
                        <i class="fas fa-check ml-2"></i>
                        ذخیره نتیجه بررسی
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.private-chat-reports.destroy', $report->id) }}" class="mt-3" onsubmit="return confirm('آیا از حذف این گزارش مطمئن هستید؟');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-rose-600 text-white rounded-lg hover:bg-rose-700 transition-colors font-semibold">
                        <i class="fas fa-trash ml-2"></i>
                        حذف گزارش
                    </button>
                </form>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">اعضای گفتگو</h2>
                <div class="space-y-3">
                    @foreach($report->conversation?->users ?? [] as $user)
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
                            <div class="h-10 w-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                                {{ mb_substr($user->fullName() ?: $user->email, 0, 1) }}
                            </div>
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $user->fullName() ?: $user->email }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection