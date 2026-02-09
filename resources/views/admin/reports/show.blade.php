@extends('layouts.admin')

@section('title', 'جزئیات گزارش')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-flag ml-2"></i>
                جزئیات گزارش
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">مشاهده و مدیریت گزارش</p>
        </div>
        <a href="{{ route('admin.reports.index') }}" 
           class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
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
        <!-- اطلاعات اصلی گزارش -->
        <div class="lg:col-span-2 space-y-6">
            <!-- اطلاعات گزارش -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-info-circle ml-2"></i>
                    اطلاعات گزارش
                </h3>
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">نوع گزارش</label>
                            @php
                                $typeLabels = [
                                    'message' => 'پیام',
                                    'post' => 'پست',
                                    'poll' => 'نظرسنجی',
                                    'user' => 'کاربر'
                                ];
                                $typeColors = [
                                    'message' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'post' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    'poll' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                    'user' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                ];
                                $type = $report->type ?? 'message';
                            @endphp
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium {{ $typeColors[$type] ?? 'bg-gray-100 text-gray-800' }}">
                                <i class="fas {{ $type == 'message' ? 'fa-comment' : ($type == 'post' ? 'fa-file-alt' : ($type == 'poll' ? 'fa-poll' : 'fa-user')) }} ml-1"></i>
                                {{ $typeLabels[$type] ?? $type }}
                            </span>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">وضعیت</label>
                            @php
                                $statusLabels = [
                                    'pending' => 'در انتظار بررسی',
                                    'reviewed' => 'بررسی شده',
                                    'resolved' => 'حل شده',
                                    'rejected' => 'رد شده',
                                    'archived' => 'بایگانی شده'
                                ];
                                $statusColors = [
                                    'pending' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                    'reviewed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'resolved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'archived' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                ];
                                $status = $report->status ?? 'pending';
                            @endphp
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $statusLabels[$status] ?? $status }}
                            </span>
                        </div>
                        
                        @if(isset($report->priority))
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">اولویت</label>
                            @php
                                $priorityLabels = [
                                    'low' => 'پایین',
                                    'medium' => 'متوسط',
                                    'high' => 'بالا',
                                    'critical' => 'بحرانی'
                                ];
                                $priorityColors = [
                                    'low' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                    'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                    'critical' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                ];
                                $priority = $report->priority ?? 'medium';
                            @endphp
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium {{ $priorityColors[$priority] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $priorityLabels[$priority] ?? $priority }}
                            </span>
                        </div>
                        @endif
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">تاریخ گزارش</label>
                            <div class="text-sm text-slate-600 dark:text-slate-400">
                                @php
                                    try {
                                        $date = isset($report->created_at) ? \Carbon\Carbon::parse($report->created_at) : null;
                                        echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d H:i') : '-';
                                    } catch (\Exception $e) {
                                        echo '-';
                                    }
                                @endphp
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">دلیل گزارش</label>
                        <div class="text-sm text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                            {{ $report->reason ?? '-' }}
                        </div>
                    </div>
                    
                    @if($report->description)
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">توضیحات تکمیلی</label>
                        <div class="text-sm text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-900 rounded-lg p-3 whitespace-pre-wrap">
                            {{ $report->description }}
                        </div>
                    </div>
                    @endif
                    
                    @if($report->admin_note)
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">یادداشت ادمین</label>
                        <div class="text-sm text-slate-600 dark:text-slate-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 whitespace-pre-wrap">
                            {{ $report->admin_note }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- محتوای مورد گزارش -->
            @if($reportedItem)
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-file-alt ml-2"></i>
                    محتوای مورد گزارش
                </h3>
                
                @if($report->type === 'message' || (isset($report->source) && $report->source === 'old'))
                    <!-- نمایش پیام -->
                    @php
                        $message = $reportedItem;
                    @endphp
                    <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold ml-3">
                                    {{ mb_substr($message->user->first_name ?? 'U', 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        {{ $message->user->fullName() ?? 'کاربر حذف شده' }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        @php
                                            try {
                                                $date = isset($message->created_at) ? \Carbon\Carbon::parse($message->created_at) : null;
                                                echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d H:i') : '-';
                                            } catch (\Exception $e) {
                                                echo '-';
                                            }
                                        @endphp
                                    </div>
                                </div>
                            </div>
                            @if($message->group)
                            <div class="text-sm text-slate-600 dark:text-slate-400">
                                گروه: <a href="{{ route('admin.groups.manage', $message->group) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $message->group->name }}</a>
                            </div>
                            @endif
                        </div>
                        
                        @if($message->message)
                        <div class="text-sm text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 rounded-lg p-3 whitespace-pre-wrap">
                            {{ $message->message }}
                        </div>
                        @endif
                        
                        @if($message->file_path)
                        <div>
                            @if(str_starts_with($message->file_type ?? '', 'image/'))
                                <img src="{{ asset($message->file_path) }}" alt="تصویر پیام" class="max-w-full h-auto rounded-lg">
                            @else
                                <a href="{{ asset($message->file_path) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-file ml-2"></i>
                                    دانلود فایل: {{ $message->file_name ?? 'فایل' }}
                                </a>
                            @endif
                        </div>
                        @endif
                        
                        @if($message->voice_message)
                        <div>
                            <audio controls class="w-full">
                                <source src="{{ $message->voice_message }}" type="audio/wav">
                            </audio>
                        </div>
                        @endif
                    </div>
                @elseif($report->type === 'post')
                    <!-- نمایش پست -->
                    @php
                        $post = $reportedItem;
                    @endphp
                    <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white font-bold ml-3">
                                    {{ mb_substr($post->user->first_name ?? 'U', 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        {{ $post->user->fullName() ?? 'کاربر حذف شده' }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        @php
                                            try {
                                                $date = isset($post->created_at) ? \Carbon\Carbon::parse($post->created_at) : null;
                                                echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d H:i') : '-';
                                            } catch (\Exception $e) {
                                                echo '-';
                                            }
                                        @endphp
                                    </div>
                                </div>
                            </div>
                            @if($post->group)
                            <div class="text-sm text-slate-600 dark:text-slate-400">
                                گروه: <a href="{{ route('admin.groups.manage', $post->group) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $post->group->name }}</a>
                            </div>
                            @endif
                        </div>
                        
                        <div class="font-semibold text-lg text-slate-900 dark:text-white">
                            {{ $post->title }}
                        </div>
                        
                        <div class="text-sm text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 rounded-lg p-3 whitespace-pre-wrap">
                            {{ $post->content }}
                        </div>
                        
                        @if($post->img)
                        <div>
                            <img src="{{ asset('images/blogs/' . $post->img) }}" alt="{{ $post->title }}" class="max-w-full h-auto rounded-lg">
                        </div>
                        @endif
                    </div>
                @elseif($report->type === 'poll')
                    <!-- نمایش نظرسنجی -->
                    @php
                        $poll = $reportedItem;
                    @endphp
                    <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-bold ml-3">
                                    {{ mb_substr($poll->creator->first_name ?? 'U', 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        {{ $poll->creator->fullName() ?? 'کاربر حذف شده' }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        @php
                                            try {
                                                $date = isset($poll->created_at) ? \Carbon\Carbon::parse($poll->created_at) : null;
                                                echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d H:i') : '-';
                                            } catch (\Exception $e) {
                                                echo '-';
                                            }
                                        @endphp
                                    </div>
                                </div>
                            </div>
                            @if($poll->group)
                            <div class="text-sm text-slate-600 dark:text-slate-400">
                                گروه: <a href="{{ route('admin.groups.manage', $poll->group) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $poll->group->name }}</a>
                            </div>
                            @endif
                        </div>
                        
                        <div class="font-semibold text-lg text-slate-900 dark:text-white">
                            {{ $poll->question }}
                        </div>
                        
                        @if($poll->options && $poll->options->count() > 0)
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">گزینه‌ها:</label>
                            @foreach($poll->options as $option)
                            <div class="flex items-center justify-between bg-white dark:bg-slate-800 rounded-lg p-3">
                                <span class="text-sm text-slate-700 dark:text-slate-300">{{ $option->option_text }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $option->votes_count ?? 0 }} رأی</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                @elseif($report->type === 'user')
                    <!-- نمایش کاربر -->
                    @php
                        $user = $reportedItem;
                    @endphp
                    <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 space-y-3">
                        <div class="flex items-center">
                            <div class="h-16 w-16 rounded-full bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center text-white font-bold text-xl ml-4">
                                {{ mb_substr($user->first_name ?? 'U', 0, 1) }}
                            </div>
                            <div>
                                <div class="font-semibold text-lg text-slate-900 dark:text-white">
                                    {{ $user->fullName() }}
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ $user->email }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    شناسه: #{{ $user->id }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                            <a href="{{ route('admin.users.show', $user) }}" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-eye ml-2"></i>
                                مشاهده پروفایل
                            </a>
                        </div>
                    </div>
                @endif
            </div>
            @endif
        </div>

        <!-- سایدبار -->
        <div class="space-y-6">
            <!-- اطلاعات گزارش‌دهنده -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-user ml-2"></i>
                    گزارش‌دهنده
                </h3>
                
                @php
                    $reporter = $report->reporter ?? null;
                @endphp
                @if($reporter)
                <div class="space-y-3">
                    <div class="flex items-center">
                        <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-lg ml-3">
                            {{ mb_substr($reporter->first_name ?? 'U', 0, 1) }}
                        </div>
                        <div>
                            <div class="font-medium text-slate-900 dark:text-white">
                                {{ $reporter->fullName() }}
                            </div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">
                                {{ $reporter->email }}
                            </div>
                        </div>
                    </div>
                    <div class="pt-3 border-t border-slate-200 dark:border-slate-700">
                        <a href="{{ route('admin.users.show', $reporter) }}" 
                           class="inline-flex items-center w-full justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-eye ml-2"></i>
                            مشاهده پروفایل
                        </a>
                    </div>
                </div>
                @else
                <p class="text-sm text-slate-400">کاربر حذف شده</p>
                @endif
            </div>

            <!-- مدیریت گزارش -->
            @hasPermission('reports.manage')
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-cog ml-2"></i>
                    مدیریت گزارش
                </h3>
                
                <form id="updateReportForm" class="space-y-4">
                    @csrf
                    @method('PUT')
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">وضعیت</label>
                        @php
                            $currentStatus = $report->status ?? 'pending';
                        @endphp
                        <select name="status" id="reportStatus" 
                                class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                            <option value="pending" {{ $currentStatus == 'pending' ? 'selected' : '' }}>در انتظار بررسی</option>
                            <option value="reviewed" {{ $currentStatus == 'reviewed' ? 'selected' : '' }}>بررسی شده</option>
                            <option value="resolved" {{ $currentStatus == 'resolved' ? 'selected' : '' }}>حل شده</option>
                            <option value="rejected" {{ $currentStatus == 'rejected' ? 'selected' : '' }}>رد شده</option>
                            <option value="archived" {{ $currentStatus == 'archived' ? 'selected' : '' }}>بایگانی شده</option>
                        </select>
                    </div>
                    
                    @if(isset($report->priority))
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">اولویت</label>
                        @php
                            $currentPriority = $report->priority ?? 'medium';
                        @endphp
                        <select name="priority" id="reportPriority" 
                                class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                            <option value="low" {{ $currentPriority == 'low' ? 'selected' : '' }}>پایین</option>
                            <option value="medium" {{ $currentPriority == 'medium' ? 'selected' : '' }}>متوسط</option>
                            <option value="high" {{ $currentPriority == 'high' ? 'selected' : '' }}>بالا</option>
                            <option value="critical" {{ $currentPriority == 'critical' ? 'selected' : '' }}>بحرانی</option>
                        </select>
                    </div>
                    @endif
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">یادداشت ادمین</label>
                        <textarea name="admin_note" 
                                  id="adminNote"
                                  rows="4"
                                  class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                                  placeholder="یادداشت‌های خود را اینجا وارد کنید...">{{ $report->admin_note ?? '' }}</textarea>
                    </div>
                    
                    <div class="flex flex-col gap-2">
                        <button type="submit" 
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save ml-2"></i>
                            ذخیره تغییرات
                        </button>
                        <button type="button" 
                                onclick="deleteReport({{ $report->id }})"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-trash ml-2"></i>
                            حذف گزارش
                        </button>
                    </div>
                </form>
            </div>
            @endhasPermission

            <!-- اطلاعات بررسی -->
            @if(isset($report->reviewed_by) && $report->reviewed_by)
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-user-check ml-2"></i>
                    اطلاعات بررسی
                </h3>
                
                <div class="space-y-2">
                    @php
                        $reviewer = $report->reviewer ?? null;
                    @endphp
                    @if($reviewer)
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">بررسی‌کننده</label>
                        <div class="text-sm text-slate-900 dark:text-white">
                            {{ $reviewer->fullName() }}
                        </div>
                    </div>
                    @endif
                    
                    @if($report->reviewed_at)
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">تاریخ بررسی</label>
                        <div class="text-sm text-slate-900 dark:text-white">
                            @php
                                try {
                                    $date = \Carbon\Carbon::parse($report->reviewed_at);
                                    echo \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d H:i');
                                } catch (\Exception $e) {
                                    echo '-';
                                }
                            @endphp
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- اطلاعات گروه -->
            @if($report->group_id)
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-users ml-2"></i>
                    گروه
                </h3>
                
                @php
                    $group = $report->group ?? null;
                @endphp
                @if($group)
                <div class="space-y-3">
                    <div class="font-medium text-slate-900 dark:text-white">
                        {{ $group->name }}
                    </div>
                    <a href="{{ route('admin.groups.manage', $group) }}" 
                       class="inline-flex items-center w-full justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-cog ml-2"></i>
                        مدیریت گروه
                    </a>
                </div>
                @else
                <p class="text-sm text-slate-400">گروه حذف شده</p>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // به‌روزرسانی گزارش
        $('#updateReportForm').on('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('آیا مطمئن هستید که می‌خواهید تغییرات را ذخیره کنید؟')) {
                return;
            }
            
            $.ajax({
                url: '{{ route('admin.reports.update', $report->id) }}',
                method: 'PUT',
                data: {
                    _token: '{{ csrf_token() }}',
                    status: $('#reportStatus').val(),
                    @if(isset($report->priority))
                    priority: $('#reportPriority').val(),
                    @endif
                    admin_note: $('#adminNote').val()
                },
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('خطا در به‌روزرسانی گزارش');
                    }
                },
                error: function() {
                    alert('خطا در به‌روزرسانی گزارش');
                }
            });
        });
    });
    
    // حذف گزارش
    function deleteReport(reportId) {
        if (!confirm('آیا مطمئن هستید که می‌خواهید این گزارش را حذف کنید؟')) {
            return;
        }
        
        $.ajax({
            url: `/admin/reports/${reportId}`,
            method: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.href = '{{ route('admin.reports.index') }}';
                } else {
                    alert('خطا در حذف گزارش');
                }
            },
            error: function() {
                alert('خطا در حذف گزارش');
            }
        });
    }
</script>
@endpush
@endsection

