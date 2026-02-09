@extends('layouts.admin')

@php
use Illuminate\Support\Facades\Storage;
@endphp

@section('title', 'جزئیات چت پشتیبانی')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('admin.support-chat.index') }}" 
               class="inline-flex items-center text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white mb-2">
                <i class="fas fa-arrow-right ml-2"></i>
                بازگشت به لیست چت‌ها
            </a>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-comments ml-2"></i>
                جزئیات چت پشتیبانی
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                چت با: <span class="font-semibold">{{ $chat->user->fullName() }}</span>
            </p>
        </div>
        <div class="flex items-center gap-3">
            @if($chat->status !== 'closed' && $chat->status !== 'resolved')
                <form action="{{ route('admin.support-chat.close', $chat->id) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" 
                            onclick="return confirm('آیا مطمئن هستید که می‌خواهید این چت را ببندید؟')"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-times-circle ml-2"></i>
                        بستن چت
                    </button>
                </form>
            @endif
            @if($chat->ticket_id)
                <a href="{{ route('admin.tickets.show', $chat->ticket_id) }}" 
                   class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-ticket-alt ml-2"></i>
                    مشاهده تیکت
                </a>
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
            <!-- اطلاعات چت -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-2">
                            {{ $chat->subject ?? 'چت پشتیبانی' }}
                        </h2>
                        <div class="flex items-center gap-3 flex-wrap">
                            @if($chat->status === 'waiting')
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200">
                                    <i class="fas fa-clock ml-1"></i>
                                    در انتظار
                                </span>
                            @elseif($chat->status === 'active')
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                    <i class="fas fa-comments ml-1"></i>
                                    فعال
                                </span>
                            @elseif($chat->status === 'resolved')
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                    <i class="fas fa-check-circle ml-1"></i>
                                    حل شده
                                </span>
                            @else
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/40 dark:text-gray-200">
                                    <i class="fas fa-times-circle ml-1"></i>
                                    بسته شده
                                </span>
                            @endif

                            @if($chat->priority === 'high')
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">
                                    <i class="fas fa-exclamation-triangle ml-1"></i>
                                    اولویت بالا
                                </span>
                            @elseif($chat->priority === 'low')
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
            </div>

            <!-- پیام‌ها -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6" style="height: 500px; display: flex; flex-direction: column;">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-comments ml-2"></i>
                    پیام‌ها ({{ $chat->messages->count() }})
                </h3>

                <div id="chatMessages" class="flex-1 overflow-y-auto space-y-4 mb-4">
                    @forelse($chat->messages as $message)
                        <div class="flex {{ $message->type === 'user' ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-md rounded-lg p-3 {{ $message->type === 'user' ? 'bg-blue-600 text-white' : ($message->type === 'system' ? 'bg-gray-200 text-gray-700' : 'bg-green-100 text-green-900 border border-green-300') }}">
                                <div class="text-xs mb-1 opacity-75">
                                    {{ $message->user->fullName() }}
                                    @if($message->type === 'agent')
                                        <i class="fas fa-user-tie mr-1"></i>
                                    @endif
                                </div>
                                <div class="text-sm whitespace-pre-wrap">{{ $message->message }}</div>
                                @if($message->attachments && count($message->attachments) > 0)
                                    <div class="mt-2 space-y-1">
                                        @foreach($message->attachments as $attachment)
                                            <a href="{{ Storage::disk('public')->url($attachment['file_path']) }}" 
                                               target="_blank"
                                               class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-black bg-opacity-10 hover:bg-opacity-20 transition-colors">
                                                <i class="fas fa-paperclip"></i>
                                                {{ $attachment['file_name'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="text-xs mt-1 opacity-75">
                                    {{ \Morilog\Jalali\Jalalian::fromCarbon($message->created_at)->format('Y/m/d H:i') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-slate-500 dark:text-slate-400 py-8">
                            <i class="fas fa-comments text-4xl mb-2"></i>
                            <p>هنوز پیامی ارسال نشده است</p>
                        </div>
                    @endforelse
                </div>

                <!-- فرم ارسال پیام -->
                @if($chat->status !== 'closed' && $chat->status !== 'resolved')
                    <form id="chatForm" onsubmit="sendMessage(event)" class="border-t border-slate-200 dark:border-slate-700 pt-4">
                        @csrf
                        <div class="mb-3">
                            <textarea id="messageInput" 
                                      rows="3"
                                      placeholder="پاسخ خود را بنویسید..."
                                      required
                                      class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white resize-none"></textarea>
                        </div>
                        <div class="mb-3">
                            <input type="file" 
                                   id="attachmentsInput" 
                                   multiple 
                                   accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar"
                                   class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:text-white">
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <button type="submit" 
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-paper-plane ml-2"></i>
                                ارسال پیام
                            </button>
                        </div>
                    </form>
                @else
                    <div class="border-t border-slate-200 dark:border-slate-700 pt-4 text-center text-slate-500 dark:text-slate-400">
                        <p>این چت بسته شده است</p>
                        @if($chat->ticket_id)
                            <a href="{{ route('admin.tickets.show', $chat->ticket_id) }}" 
                               class="text-blue-600 dark:text-blue-400 hover:underline mt-2 inline-block">
                                مشاهده تیکت مرتبط
                            </a>
                        @endif
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
                            {{ $chat->user->fullName() }}
                        </div>
                    </div>
                    <div>
                        <span class="text-sm text-slate-500 dark:text-slate-400">ایمیل:</span>
                        <div class="text-slate-900 dark:text-white font-medium mt-1">
                            <a href="mailto:{{ $chat->user->email }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                {{ $chat->user->email }}
                            </a>
                        </div>
                    </div>
                    @if($chat->user->phone)
                        <div>
                            <span class="text-sm text-slate-500 dark:text-slate-400">تلفن:</span>
                            <div class="text-slate-900 dark:text-white font-medium mt-1">
                                <a href="tel:{{ $chat->user->phone }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ $chat->user->phone }}
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- عملیات -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-cog ml-2"></i>
                    عملیات
                </h3>
                <div class="space-y-3">
                    @if($chat->status !== 'closed' && $chat->status !== 'resolved')
                        @if(!$chat->ticket_id)
                            <button onclick="convertToTicket()" 
                                    class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition-colors">
                                <i class="fas fa-ticket-alt ml-2"></i>
                                تبدیل به تیکت
                            </button>
                        @endif

                        @if(!$chat->agent_id || $chat->agent_id !== auth()->id())
                            <form action="{{ route('admin.support-chat.assign', $chat->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="agent_id" value="{{ auth()->id() }}">
                                <button type="submit" 
                                        class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-user-plus ml-2"></i>
                                    اختصاص به خودم
                                </button>
                            </form>
                        @endif

                        @if($chat->agent_id && $chat->agent_id !== auth()->id())
                            <form action="{{ route('admin.support-chat.assign', $chat->id) }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">اختصاص به:</label>
                                    <select name="agent_id" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:text-white">
                                        @foreach($agents as $agent)
                                            <option value="{{ $agent->id }}" {{ $chat->agent_id == $agent->id ? 'selected' : '' }}>
                                                {{ $agent->fullName() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" 
                                        class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                                    <i class="fas fa-exchange-alt ml-2"></i>
                                    تغییر پشتیبان
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

            <!-- اطلاعات چت -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-info-circle ml-2"></i>
                    اطلاعات چت
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">تاریخ ایجاد:</span>
                        <span class="text-slate-900 dark:text-white font-medium">
                            {{ \Morilog\Jalali\Jalalian::fromCarbon($chat->created_at)->format('Y/m/d') }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">ساعت ایجاد:</span>
                        <span class="text-slate-900 dark:text-white font-medium">
                            {{ \Morilog\Jalali\Jalalian::fromCarbon($chat->created_at)->format('H:i') }}
                        </span>
                    </div>
                    @if($chat->last_activity_at)
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">آخرین فعالیت:</span>
                            <span class="text-slate-900 dark:text-white font-medium">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($chat->last_activity_at)->format('Y/m/d H:i') }}
                            </span>
                        </div>
                    @endif
                    @if($chat->resolved_at)
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">تاریخ حل:</span>
                            <span class="text-slate-900 dark:text-white font-medium">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($chat->resolved_at)->format('Y/m/d H:i') }}
                            </span>
                        </div>
                    @endif
                    @if($chat->agent)
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">پشتیبان:</span>
                            <span class="text-slate-900 dark:text-white font-medium">
                                {{ $chat->agent->fullName() }}
                            </span>
                        </div>
                    @endif
                    @if($chat->ticket_id)
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">تیکت:</span>
                            <a href="{{ route('admin.tickets.show', $chat->ticket_id) }}" 
                               class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                مشاهده تیکت
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal تبدیل به تیکت -->
<div id="convertTicketModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-bold mb-4 text-slate-900 dark:text-white">تبدیل چت به تیکت</h3>
        <form id="convertTicketForm" action="{{ route('admin.support-chat.convert-to-ticket', $chat->id) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">موضوع تیکت</label>
                <input type="text" 
                       name="subject" 
                       value="{{ $chat->subject ?? '' }}"
                       required
                       class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-slate-700 dark:text-white">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">دسته</label>
                <select name="category" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-slate-700 dark:text-white">
                    <option value="general">عمومی</option>
                    <option value="technical">مشکل فنی</option>
                    <option value="account">حساب کاربری</option>
                    <option value="payment">پرداخت</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">اولویت</label>
                <select name="priority" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-slate-700 dark:text-white">
                    <option value="normal" {{ $chat->priority === 'normal' ? 'selected' : '' }}>عادی</option>
                    <option value="low" {{ $chat->priority === 'low' ? 'selected' : '' }}>پایین</option>
                    <option value="high" {{ $chat->priority === 'high' ? 'selected' : '' }}>بالا</option>
                </select>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" 
                        onclick="closeConvertTicketModal()"
                        class="px-4 py-2 text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    انصراف
                </button>
                <button type="submit" 
                        class="px-4 py-2 text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition-colors font-semibold">
                    تبدیل
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const chatId = {{ $chat->id }};
    
    // اسکرول به پایین
    function scrollToBottom() {
        const messagesDiv = document.getElementById('chatMessages');
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
    
    scrollToBottom();

    // ارسال پیام
    async function sendMessage(e) {
        e.preventDefault();
        
        const messageInput = document.getElementById('messageInput');
        const attachmentsInput = document.getElementById('attachmentsInput');
        const formData = new FormData();
        
        formData.append('message', messageInput.value);
        formData.append('_token', document.querySelector('input[name="_token"]').value);
        
        if (attachmentsInput.files.length > 0) {
            for (let i = 0; i < attachmentsInput.files.length; i++) {
                formData.append('attachments[]', attachmentsInput.files[i]);
            }
        }
        
        if (!messageInput.value.trim() && attachmentsInput.files.length === 0) {
            return;
        }
        
        try {
            const response = await fetch(`/admin/support-chat/${chatId}/message`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                messageInput.value = '';
                attachmentsInput.value = '';
                location.reload();
            } else {
                alert('خطا در ارسال پیام: ' + (data.error || 'خطای نامشخص'));
            }
        } catch (error) {
            alert('خطا در ارسال پیام');
            console.error(error);
        }
    }

    // تبدیل به تیکت
    function convertToTicket() {
        document.getElementById('convertTicketModal').classList.remove('hidden');
    }

    function closeConvertTicketModal() {
        document.getElementById('convertTicketModal').classList.add('hidden');
    }

    // بارگذاری خودکار پیام‌های جدید هر 5 ثانیه
    setInterval(function() {
        scrollToBottom();
    }, 5000);
</script>
@endpush
@endsection




