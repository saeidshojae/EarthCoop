@extends('layouts.admin')

@section('title', 'ارسال ایمیل - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'ارسال ایمیل')
@section('page-description', 'ارسال ایمیل با استفاده از قالب یا به صورت سفارشی')

@push('styles')
<style>
    .email-preview {
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 1rem;
        background: white;
        min-height: 200px;
    }
    @media (prefers-color-scheme: dark) {
        .email-preview {
            background: #1f2937;
            border-color: #374151;
        }
    }
</style>
@endpush

@section('content')
<div class="container mx-auto px-4 py-6 max-w-6xl">
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Send with Template -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-envelope-open-text text-blue-600"></i>
                ارسال با قالب
            </h3>

            <form action="{{ route('admin.emails.send-template') }}" method="POST" id="templateForm">
                @csrf

                <div class="space-y-4">
                    <!-- Template Selection -->
                    <div>
                        <label for="template_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            انتخاب قالب <span class="text-red-500">*</span>
                        </label>
                        <select id="template_id" name="template_id" required
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="">-- انتخاب قالب --</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}" data-subject="{{ $template->subject }}" data-body="{{ $template->body }}">
                                    {{ $template->name }} @if(!$template->is_active) (غیرفعال) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Recipients -->
                    <div>
                        <label for="recipients" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            گیرندگان <span class="text-red-500">*</span>
                        </label>
                        <textarea id="recipients" name="recipients[]" rows="4" required
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                            placeholder="یک ایمیل در هر خط یا با کاما جدا کنید&#10;مثال:&#10;user1@example.com&#10;user2@example.com"></textarea>
                        <p class="mt-1 text-xs text-gray-500">می‌توانید از لیست کاربران انتخاب کنید یا به صورت دستی وارد کنید</p>
                    </div>

                    <!-- Quick User Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            انتخاب سریع از کاربران
                        </label>
                        <select id="userSelector" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                            <option value="">-- انتخاب کاربر --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->email }}">{{ $user->fullName() }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                        <button type="button" onclick="addUserEmail()" class="mt-2 text-sm text-blue-600 hover:underline">
                            افزودن به لیست گیرندگان
                        </button>
                    </div>

                    <!-- Variables -->
                    <div id="variablesSection" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            متغیرها
                        </label>
                        <div id="variablesContainer" class="space-y-2"></div>
                    </div>

                    <!-- Preview -->
                    <div id="previewSection" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            پیش‌نمایش
                        </label>
                        <div class="email-preview">
                            <div class="font-bold mb-2" id="previewSubject"></div>
                            <div id="previewBody"></div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition">
                        <i class="fas fa-paper-plane"></i>
                        ارسال ایمیل
                    </button>
                </div>
            </form>
        </div>

        <!-- Send Custom Email -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-edit text-green-600"></i>
                ارسال سفارشی
            </h3>

            <form action="{{ route('admin.emails.send-custom') }}" method="POST">
                @csrf

                <div class="space-y-4">
                    <!-- Recipients -->
                    <div>
                        <label for="custom_recipients" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            گیرندگان <span class="text-red-500">*</span>
                        </label>
                        <textarea id="custom_recipients" name="recipients[]" rows="4" required
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                            placeholder="یک ایمیل در هر خط یا با کاما جدا کنید"></textarea>
                    </div>

                    <!-- Quick User Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            انتخاب سریع از کاربران
                        </label>
                        <select id="customUserSelector" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                            <option value="">-- انتخاب کاربر --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->email }}">{{ $user->fullName() }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                        <button type="button" onclick="addCustomUserEmail()" class="mt-2 text-sm text-green-600 hover:underline">
                            افزودن به لیست گیرندگان
                        </button>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label for="custom_subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            موضوع <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="custom_subject" name="subject" required
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Body -->
                    <div>
                        <label for="custom_body" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            محتوا (HTML) <span class="text-red-500">*</span>
                        </label>
                        <textarea id="custom_body" name="body" rows="10" required
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-white"></textarea>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition">
                        <i class="fas fa-paper-plane"></i>
                        ارسال ایمیل سفارشی
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addUserEmail() {
    const selector = document.getElementById('userSelector');
    const recipients = document.getElementById('recipients');
    const email = selector.value;
    
    if (email && !recipients.value.includes(email)) {
        recipients.value += (recipients.value ? '\n' : '') + email;
    }
}

function addCustomUserEmail() {
    const selector = document.getElementById('customUserSelector');
    const recipients = document.getElementById('custom_recipients');
    const email = selector.value;
    
    if (email && !recipients.value.includes(email)) {
        recipients.value += (recipients.value ? '\n' : '') + email;
    }
}

// Template selection handler
document.getElementById('template_id')?.addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const subject = option.dataset.subject || '';
    const body = option.dataset.body || '';
    
    if (subject || body) {
        updatePreview(subject, body);
        loadVariables(body);
    } else {
        document.getElementById('previewSection').style.display = 'none';
        document.getElementById('variablesSection').style.display = 'none';
    }
});

function updatePreview(subject, body) {
    document.getElementById('previewSubject').textContent = subject;
    document.getElementById('previewBody').innerHTML = body;
    document.getElementById('previewSection').style.display = 'block';
}

function loadVariables(body) {
    const regex = /\{\{(\w+)\}\}/g;
    const matches = [...new Set([...body.matchAll(regex)].map(m => m[1]))];
    
    const container = document.getElementById('variablesContainer');
    container.innerHTML = '';
    
    if (matches.length > 0) {
        matches.forEach(variable => {
            const div = document.createElement('div');
            div.className = 'flex gap-2 items-center';
            div.innerHTML = `
                <label class="text-sm text-gray-700 dark:text-gray-300 w-24">${variable}:</label>
                <input type="text" name="variables[${variable}]" 
                    class="flex-1 px-3 py-1 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-white"
                    oninput="updatePreviewFromVariables()">
            `;
            container.appendChild(div);
        });
        document.getElementById('variablesSection').style.display = 'block';
    } else {
        document.getElementById('variablesSection').style.display = 'none';
    }
}

function updatePreviewFromVariables() {
    const templateSelect = document.getElementById('template_id');
    const option = templateSelect.options[templateSelect.selectedIndex];
    let subject = option.dataset.subject || '';
    let body = option.dataset.body || '';
    
    const inputs = document.querySelectorAll('#variablesContainer input');
    inputs.forEach(input => {
        const varName = input.name.match(/\[(\w+)\]/)[1];
        const value = input.value || '{' + '{' + varName + '}' + '}';
        subject = subject.replace(new RegExp(`\\{\\{${varName}\\}\\}`, 'g'), value);
        body = body.replace(new RegExp(`\\{\\{${varName}\\}\\}`, 'g'), value);
    });
    
    updatePreview(subject, body);
}
</script>
@endsection

