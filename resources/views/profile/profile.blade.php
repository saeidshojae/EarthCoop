@extends('layouts.unified')

@section('title', 'حساب کاربری شما - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    /* Profile Section Styles from profile.html */
    .user-profile-section {
        display: flex;
        flex-direction: column;
        gap: 20px;
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
    }

    .profile-header {
        text-align: center;
        margin-bottom: 30px;
        position: relative;
    }

    .profile-header h1 {
        font-size: 2.2rem;
        color: var(--color-gentle-black);
        margin-bottom: 10px;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .profile-picture-container {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: white;
        margin: 20px auto 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08), 0 2px 4px rgba(0, 0, 0, 0.12);
        border: 4px solid var(--color-earth-green);
        overflow: hidden;
        position: relative;
    }

    .profile-picture-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        padding: 20px;
        background-color: var(--color-light-gray);
        border-radius: 1rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
        border: 1px solid #e2e8f0;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        background-color: var(--color-pure-white);
        padding: 18px 20px;
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        border: 1px solid #f1f5f9;
        position: relative;
    }

    .info-label {
        font-weight: 600;
        color: var(--color-earth-green);
        font-size: 0.9rem;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
    }

    .info-label i {
        margin-left: 8px;
        color: var(--color-dark-green);
        font-size: 1.1em;
    }

    .info-value {
        color: var(--color-gentle-black);
        font-size: 1rem;
        flex-grow: 1;
    }

    .info-actions {
        display: flex;
        gap: 8px;
        position: absolute;
        top: 15px;
        left: 15px;
    }

    .action-button {
        background-color: var(--color-light-gray);
        border: none;
        border-radius: 9999px;
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease-out;
        color: var(--color-earth-green);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        text-decoration: none;
    }

    .action-button:hover {
        background-color: #e2e8f0;
        color: var(--color-dark-green);
        transform: scale(1.1);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .social-media-list,
    .documents-list {
        display: flex;
        flex-wrap: wrap; 
        gap: 10px;
        margin-top: 10px;
        padding: 0; 
        border-radius: 0.375rem;
    }

    .social-media-item {
        background-color: #e0f2fe;
        color: var(--color-ocean-blue);
        padding: 8px 12px;
        border-radius: 9999px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 5px;
        border: 1px solid #bfdbfe;
        transition: all 0.15s ease-out;
    }

    .social-media-item:hover {
        background-color: #d0effd;
        transform: translateY(-2px);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .documents-list {
        flex-direction: column; 
    }

    .document-item {
        display: flex;
        align-items: center;
        gap: 8px;
        background-color: #f1f5f9;
        padding: 8px 12px;
        border-radius: 0.375rem;
        font-size: 0.9rem;
        color: var(--color-gentle-black);
        width: fit-content; 
    }

    .document-item i {
        color: #64748b;
    }

    /* Groups Section Styles - حفظ منطق فعلی */
    .groups-section {
        width: 100%;
        max-width: 800px;
        margin: 40px auto 0; 
        background-color: var(--color-pure-white);
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid #e2e8f0;
        padding: 25px;
    }

    .groups-section h2 {
        font-size: 1.8rem;
        color: var(--color-gentle-black);
        margin-bottom: 20px;
        text-align: center;
        position: relative;
        padding-bottom: 10px;
    }

    .groups-section h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 3px;
        background: linear-gradient(90deg, var(--color-earth-green), var(--color-dark-green));
        border-radius: 9999px;
    }

    /* Tab Buttons - حفظ منطق فعلی */
    .tabs {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 25px;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 10px;
    }

    .tab {
        background-color: #f1f5f9;
        color: var(--color-gentle-black);
        padding: 12px 20px;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 600; 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        flex-grow: 1; 
        min-width: 100px; 
        text-align: center;
    }

    .tab:hover {
        background-color: #e2e8f0;
        color: var(--color-gentle-black);
        transform: translateY(-2px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .tab.active {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08), 0 2px 4px rgba(0, 0, 0, 0.12);
        transform: translateY(-3px);
    }

    .tab-content {
        display: none;
        padding: 15px 0;
        animation: fadeIn 0.5s ease-out; 
    }

    .tab-content.active {
        display: block;
    }

    /* Sub-tabs - حفظ منطق فعلی */
    .sub-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
        direction: rtl;
    }

    .sub-tab {
        padding: 0.5rem 1rem;
        background-color: #eee;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: 0.3s;
        font-size: 0.85rem;
        white-space: nowrap;
        border: 1px solid #ddd;
    }

    .sub-tab:hover {
        background-color: #e7f0ff;
    }

    .sub-tab.active,
    .sub-tab.btn-primary {
        background-color: var(--color-earth-green) !important;
        color: #fff !important;
        border-color: var(--color-earth-green) !important;
    }

    .sub-tab-content {
        display: none;
    }

    .sub-tab-content:not(.d-none) {
        display: block;
    }

    /* Group List Styles - حفظ منطق فعلی */
    .tab-content-list {
        list-style: none;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 15px;
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 10px;
    }

    .tab-content-item {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1rem;
        color: var(--color-gentle-black);
        transition: all 0.15s ease-out;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        cursor: pointer;
    }

    .tab-content-item:hover {
        background-color: #f1f5f9;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
        border-color: var(--color-earth-green);
    }

    .tab-content-item i {
        color: var(--color-earth-green);
        font-size: 1.2em;
        flex-shrink: 0;
    }

    .group-avatar {
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        background-color: #ecf5ff;
        color: rgb(61, 131, 175);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 18px;
        flex-shrink: 0;
    }

    .group-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    /* Request Cards */
    .request-card {
        background-color: var(--color-pure-white);
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    .request-card-header {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--color-gentle-black);
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid var(--color-earth-green);
    }

    /* Dark Mode Support */
    body.dark-mode .user-profile-section,
    body.dark-mode .groups-section,
    body.dark-mode .info-item,
    body.dark-mode .request-card {
        background-color: #2d2d2d;
        border-color: #404040;
    }

    body.dark-mode .profile-info-grid {
        background-color: #3a3a3a;
    }

    body.dark-mode .tab {
        background-color: #3a3a3a;
        color: #e0e0e0;
    }

    body.dark-mode .sub-tab {
        background-color: #3a3a3a;
        color: #e0e0e0;
        border-color: #555;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .profile-info-grid {
            grid-template-columns: 1fr;
        }
        .tabs {
            flex-direction: column;
        }
        .tab {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- User Profile Section -->
    <div class="user-profile-section">
        <div class="profile-header">
            <h1>حساب کاربری شما</h1>
            <div class="profile-picture-container">
                {!! $user->profile() !!}
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                <a href="{{ route('profile.edit') }}" 
                   class="bg-earth-green text-pure-white px-6 py-2 rounded-full shadow-md hover:bg-dark-green transition duration-300 font-medium transform hover:scale-105">
                    <i class="fas fa-edit ml-2"></i>ویرایش اطلاعات
                </a>
                <a href="{{ url('/profile-member/' . auth()->user()->id) }}" 
                   class="bg-ocean-blue text-pure-white px-6 py-2 rounded-full shadow-md hover:bg-dark-blue transition duration-300 font-medium transform hover:scale-105">
                    <i class="fas fa-eye ml-2"></i>پیش نمایش پروفایل من
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        <!-- Chat Requests -->
        @if($chatRequests->isNotEmpty())
            <div class="request-card">
                <div class="request-card-header">
                    <i class="fas fa-comments ml-2" style="color: var(--color-earth-green);"></i>
                    درخواست‌های چت
                </div>
                <div class="space-y-3">
                    @foreach($chatRequests as $request)
                        <div class="flex justify-between items-center p-3 bg-light-gray rounded-lg">
                            <div>
                                <h6 class="font-semibold text-gentle-black">{{ $request->sender->fullName() }}</h6>
                                <small class="text-gray-500">{{ verta($request->created_at)->format('Y-m-d H:i') }}</small>
                            </div>
                            <div class="flex gap-2">
                                <form action="{{ route('chat-requests.accept', $request->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                                        <i class="fas fa-check ml-2"></i>پذیرفتن
                                    </button>
                                </form>
                                <form action="{{ route('chat-requests.reject', $request->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">
                                        <i class="fas fa-times ml-2"></i>رد کردن
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Candidates -->
        @foreach ($candidates as $candidate)
            @php
                $role = \App\Models\Vote::where('candidate_id', $candidate->user_id)->where('election_id', $candidate->election_id)->first();
            @endphp
            <div class="request-card">
                <h5 class="text-center mb-4 font-semibold">
                    شما به عنوان {{ $role->position == 0 ? 'بازرس' : 'مدیر' }} در گروه {{ $candidate->election->group->name }} پذیرفته شدید
                </h5>
                <div class="flex gap-3">
                    <a href="{{ route('profile.accept.candidate', ['accept', 'id' => $candidate->id]) }}" 
                       class="flex-1 bg-green-500 text-white px-4 py-2 rounded-lg text-center hover:bg-green-600 transition">
                        می‌پذیرم
                    </a>
                    <a href="{{ route('profile.accept.candidate', ['reject', 'id' => $candidate->id]) }}" 
                       class="flex-1 bg-red-500 text-white px-4 py-2 rounded-lg text-center hover:bg-red-600 transition">
                        نمی‌پذیرم
                    </a>
                </div>
            </div>
        @endforeach

        <!-- Join Group Requests -->
        @if($joinGroupRequests->isNotEmpty())
            <div class="request-card">
                <div class="request-card-header">
                    <i class="fas fa-user-plus ml-2" style="color: var(--color-earth-green);"></i>
                    درخواست‌های افزودن به گروه
                </div>
                <div class="space-y-3">
                    @foreach ($joinGroupRequests as $request)
                        <div class="p-4 bg-light-gray rounded-lg">
                            <h5 class="text-center mb-4 font-semibold">
                                شما درخواست پیوستن به گروه {{ $request->group->name }} دریافت کرده‌اید.
                            </h5>
                            <div class="flex gap-3">
                                <a href="{{ route('profile.join.group', ['1', 'id' => $request->id]) }}" 
                                   class="flex-1 bg-green-500 text-white px-4 py-2 rounded-lg text-center hover:bg-green-600 transition">
                                    می‌پذیرم
                                </a>
                                <a href="{{ route('profile.join.group', ['0', 'id' => $request->id]) }}" 
                                   class="flex-1 bg-red-500 text-white px-4 py-2 rounded-lg text-center hover:bg-red-600 transition">
                                    نمی‌پذیرم
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Profile Info Grid -->
        <div class="profile-info-grid">
            <!-- Name -->
            <div class="info-item">
                <div class="info-actions">
                    <a href="{{ route('profile.show.info', ['field' => 'name']) }}" class="action-button js-visibility-toggle" data-field="name" title="مخفی/نمایش عمومی">
                        <i class="{{ Auth::user()->show_name == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                    </a>
                </div>
                <span class="info-label"><i class="fas fa-signature"></i>نام:</span>
                <span class="info-value">{{ Auth::user()->fullName() }}</span>
            </div>

            <!-- Email -->
            <div class="info-item">
                <div class="info-actions">
                    <a href="{{ route('profile.show.info', ['field' => 'email']) }}" class="action-button js-visibility-toggle" data-field="email" title="مخفی/نمایش عمومی">
                        <i class="{{ Auth::user()->show_email == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                    </a>
                </div>
                <span class="info-label"><i class="fas fa-at"></i>ایمیل:</span>
                <span class="info-value">{{ Auth::user()->email }}</span>
            </div>

            <!-- Phone -->
            <div class="info-item">
                <div class="info-actions">
                    <a href="{{ route('profile.show.info', ['field' => 'phone']) }}" class="action-button js-visibility-toggle" data-field="phone" title="مخفی/نمایش عمومی">
                        <i class="{{ Auth::user()->show_phone == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                    </a>
                </div>
                <span class="info-label"><i class="fas fa-phone"></i>شماره تماس:</span>
                <span class="info-value">{{ Auth::user()->phone }}</span>
            </div>

            <!-- Birthdate -->
            <div class="info-item">
                <div class="info-actions">
                    <a href="{{ route('profile.show.info', ['field' => 'birthdate']) }}" class="action-button js-visibility-toggle" data-field="birthdate" title="مخفی/نمایش عمومی">
                        <i class="{{ Auth::user()->show_birthdate == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                    </a>
                </div>
                <span class="info-label"><i class="fas fa-calendar-alt"></i>تاریخ تولد:</span>
                <span class="info-value">{{ verta(Auth::user()->birth_date)->format('Y-m-d') }}</span>
            </div>

            <!-- Gender -->
            <div class="info-item">
                <div class="info-actions">
                    <a href="{{ route('profile.show.info', ['field' => 'gender']) }}" class="action-button js-visibility-toggle" data-field="gender" title="مخفی/نمایش عمومی">
                        <i class="{{ Auth::user()->show_gender == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                    </a>
                </div>
                <span class="info-label"><i class="fas fa-venus-mars"></i>جنسیت:</span>
                <span class="info-value">{{ Auth::user()->gender == 'male' ? 'مرد' : 'زن' }}</span>
            </div>

            <!-- National ID -->
            <div class="info-item">
                <div class="info-actions">
                    <a href="{{ route('profile.show.info', ['field' => 'national_id']) }}" class="action-button js-visibility-toggle" data-field="national_id" title="مخفی/نمایش عمومی">
                        <i class="{{ Auth::user()->show_national_id == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                    </a>
                </div>
                <span class="info-label"><i class="fas fa-id-card"></i>کد ملی:</span>
                <span class="info-value">{{ Auth::user()->national_id }}</span>
            </div>

            <!-- Biography -->
            <div class="info-item" style="grid-column: 1 / -1;">
                <div class="info-actions">
                    @if (Auth::user()->biografie != null)
                        <a href="{{ route('profile.show.info', ['field' => 'biografie']) }}" class="action-button js-visibility-toggle" data-field="biografie" title="مخفی/نمایش عمومی">
                            <i class="{{ Auth::user()->show_biografie == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                        </a>
                    @endif
                </div>
                <span class="info-label"><i class="fas fa-book"></i>بیوگرافی:</span>
                <span class="info-value">{{ Auth::user()->biografie == null ? '-' : Auth::user()->biografie }}</span>
            </div>

            <!-- Documents -->
            <div class="info-item" style="grid-column: 1 / -1;">
                <div class="info-actions">
                    @if (Auth::user()->documents != null)
                        <a href="{{ route('profile.show.info', ['field' => 'documents']) }}" class="action-button js-visibility-toggle" data-field="documents" title="مخفی/نمایش عمومی">
                            <i class="{{ Auth::user()->show_documents == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                        </a>
                    @endif
                </div>
                <span class="info-label"><i class="fas fa-file-invoice"></i>مدارک:</span>
                <div class="documents-list info-value">
                    @if(Auth::user()->documents == null)
                        <span>-</span>
                    @else
                        @php
                            $documentsData = auth()->user()->documents;
                            $decoded = json_decode($documentsData, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $documents = $decoded;
                            } else {
                                $files = explode(',', $documentsData);
                                $documents = [];
                                foreach ($files as $file) {
                                    $file = trim($file);
                                    if (!empty($file)) {
                                        $extension = pathinfo($file, PATHINFO_EXTENSION);
                                        $documents[] = [
                                            'filename' => $file,
                                            'name' => 'مدرک',
                                            'type' => strtolower($extension)
                                        ];
                                    }
                                }
                            }
                        @endphp
                        @foreach($documents as $doc)
                            @php
                                $filename = is_array($doc) ? $doc['filename'] : $doc;
                                $docName = is_array($doc) ? ($doc['name'] ?? $filename) : $filename;
                                $extension = is_array($doc) ? ($doc['type'] ?? pathinfo($filename, PATHINFO_EXTENSION)) : pathinfo($filename, PATHINFO_EXTENSION);
                                $isImage = in_array(strtolower($extension), ['png', 'jpg', 'jpeg']);
                            @endphp
                            <div class="document-item">
                                @if($isImage)
                                    <i class="fas fa-file-image"></i>
                                    <img src="{{ asset('images/users/documents/' . $filename) }}" width="50" class="rounded">
                                @else
                                    <i class="fas fa-file-pdf"></i>
                                @endif
                                <span>{{ $docName }}</span>
                                <a href="{{ asset('images/users/documents/' . $filename) }}" download class="text-earth-green hover:text-dark-green">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- Created At -->
            <div class="info-item">
                <div class="info-actions">
                    <a href="{{ route('profile.show.info', ['field' => 'created_at']) }}" class="action-button js-visibility-toggle" data-field="created_at" title="مخفی/نمایش عمومی">
                        <i class="{{ Auth::user()->show_created_at == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                    </a>
                </div>
                <span class="info-label"><i class="fas fa-calendar-check"></i>تاریخ ثبت نام:</span>
                <span class="info-value">{{ verta(Auth::user()->created_at)->format('Y-m-d') }}</span>
            </div>

            <!-- Groups Count -->
            <div class="info-item">
                <div class="info-actions">
                    <a href="{{ route('profile.show.info', ['field' => 'groups']) }}" class="action-button js-visibility-toggle" data-field="groups" title="مخفی/نمایش عمومی">
                        <i class="{{ Auth::user()->show_groups == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                    </a>
                </div>
                <span class="info-label"><i class="fas fa-users-cog"></i>گروه‌ها:</span>
                <span class="info-value">{{ Auth::user()->groups->count() }} گروه</span>
            </div>

            <!-- Social Networks -->
            <div class="info-item" style="grid-column: 1 / -1;">
                <div class="info-actions">
                    <a href="{{ route('profile.show.info', ['field' => 'social']) }}" class="action-button js-visibility-toggle" data-field="social" title="مخفی/نمایش عمومی">
                        <i class="{{ Auth::user()->show_social_networks == 0 ? 'fas fa-eye' : 'fas fa-eye-slash' }}"></i>
                    </a>
                </div>
                <span class="info-label"><i class="fas fa-share-alt"></i>شبکه‌های اجتماعی:</span>
                <div class="social-media-list info-value">
                    @php
                        $storedLinks = $user->social_networks ?? [];
                        if (is_string($storedLinks)) {
                            $storedLinks = json_decode($storedLinks, true);
                        }
                        $socialLinks = old('options', $storedLinks);
                    @endphp
                    @forelse($socialLinks as $index => $link)
                        <span class="social-media-item">
                            <i class="fas fa-link"></i>
                            <a href="{{ $link }}" target="_blank" style="text-decoration: none; color: inherit;">{{ $link }}</a>
                        </span>
                    @empty
                        <span class="text-gray-500">هیچ لینک اجتماعی ثبت نشده است.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Groups Section - حفظ منطق فعلی -->
    <div class="groups-section">
        <h2>گروه‌های همکاری</h2>
        
        @php
            // برچسب‌های زیرتب آدرس - حفظ منطق فعلی
            $levelTabs = [
                'global'       => 'جهانی',
                'continent'    => 'قاره‌ای',
                'country'      => 'کشوری',
                'province'     => 'استانی',
                'county'       => 'شهرستانی',
                'section'      => 'بخشی',
                'city'         => 'شهری/ دهستانی',
                'region'       => 'منطقه‌ای/ روستایی',
                'neighborhood' => 'محله‌ای',
            ];
        @endphp

        <!-- Tab Buttons - حفظ منطق فعلی -->
        <div class="tabs">
            <div class="tab active" data-tab="generalGroups">گروه‌های مجمع عمومی</div>
            <div class="tab" data-tab="specialityGroups">گروه‌های شغلی و صنفی</div>
            <div class="tab" data-tab="experienceGroups">گروه‌های علمی و تجربی</div>
            <div class="tab" data-tab="ageGroups">گروه‌های سنی</div>
            <div class="tab" data-tab="genderGroups">گروه‌های جنسیتی</div>
        </div>

        <!-- Tab Contents - حفظ منطق فعلی -->
        {{-- گروه‌های مجمع عمومی --}}
        <div class="tab-content active" id="generalGroups">
            @include('partials.group_list', ['groups' => $generalGroups, 'user' => $user])
        </div>

        {{-- گروه‌های شغلی و صنفی --}}
        <div class="tab-content" id="specialityGroups">
            @if($specialityGroups->isNotEmpty())
                @php
                    $groupsByLevel = $specialityGroups->groupBy('location_level');
                    $firstSpecActive = null;
                @endphp

                <div class="sub-tabs d-flex flex-wrap gap-2 mb-3" dir="rtl">
                    @foreach($levelTabs as $lvl => $label)
                        @php
                            $hasAny = isset($groupsByLevel[$lvl]) && $groupsByLevel[$lvl]->isNotEmpty();
                            if ($hasAny && is_null($firstSpecActive)) $firstSpecActive = "spec-lvl-$lvl";
                        @endphp
                        @if($hasAny)
                            <button class="sub-tab btn btn-sm btn-outline-primary" data-subtab="spec-lvl-{{ $lvl }}">{{ $label }}</button>
                        @endif
                    @endforeach
                </div>

                @foreach($levelTabs as $lvl => $label)
                    @php $collection = $groupsByLevel[$lvl] ?? collect(); @endphp
                    @if($collection->isNotEmpty())
                        <div class="sub-tab-content {{ $firstSpecActive === "spec-lvl-$lvl" ? '' : 'd-none' }}" id="spec-lvl-{{ $lvl }}">
                            @include('partials.group_list', ['groups' => $collection, 'user' => $user])
                        </div>
                    @endif
                @endforeach
            @endif
        </div>

        {{-- گروه‌های علمی و تجربی --}}
        <div class="tab-content" id="experienceGroups">
            @if($experienceGroups->isNotEmpty())
                @php
                    $groupsByLevel = $experienceGroups->groupBy('location_level');
                    $firstExpActive = null;
                @endphp

                <div class="sub-tabs d-flex flex-wrap gap-2 mb-3" dir="rtl">
                    @foreach($levelTabs as $lvl => $label)
                        @php
                            $hasAny = isset($groupsByLevel[$lvl]) && $groupsByLevel[$lvl]->isNotEmpty();
                            if ($hasAny && is_null($firstExpActive)) $firstExpActive = "exp-lvl-$lvl";
                        @endphp
                        @if($hasAny)
                            <button class="sub-tab btn btn-sm btn-outline-primary" data-subtab="exp-lvl-{{ $lvl }}">{{ $label }}</button>
                        @endif
                    @endforeach
                </div>

                @foreach($levelTabs as $lvl => $label)
                    @php $collection = $groupsByLevel[$lvl] ?? collect(); @endphp
                    @if($collection->isNotEmpty())
                        <div class="sub-tab-content {{ $firstExpActive === "exp-lvl-$lvl" ? '' : 'd-none' }}" id="exp-lvl-{{ $lvl }}">
                            @include('partials.group_list', ['groups' => $collection, 'user' => $user])
                        </div>
                    @endif
                @endforeach
            @endif
        </div>

        {{-- گروه‌های سنی --}}
        <div class="tab-content" id="ageGroups">
            @include('partials.group_list', ['groups' => $ageGroups, 'user' => $user])
        </div>

        {{-- گروه‌های جنسیتی --}}
        <div class="tab-content" id="genderGroups">
            @include('partials.group_list', ['groups' => $genderGroups, 'user' => $user])
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // تب‌های اصلی - حفظ منطق فعلی
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(i => i.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById(tab.getAttribute('data-tab')).classList.add('active');
        });
    });

    // زیرتب‌ها - حفظ منطق فعلی
    document.querySelectorAll('.sub-tabs').forEach(subTabsWrap => {
        const subTabBtns = subTabsWrap.querySelectorAll('.sub-tab');
        const parentContent = subTabsWrap.closest('.tab-content');
        const contents = parentContent.querySelectorAll('.sub-tab-content');

        subTabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                subTabBtns.forEach(b => b.classList.remove('btn-primary', 'active'));
                contents.forEach(cnt => cnt.classList.add('d-none'));
                btn.classList.add('btn-primary', 'active');
                const target = parentContent.querySelector('#' + btn.getAttribute('data-subtab'));
                if (target) target.classList.remove('d-none');
            });
        });

        // فعال‌سازی پیش‌فرض اولین زیرتب
        if (subTabBtns.length) {
            const first = subTabBtns[0];
            first.classList.add('btn-primary', 'active');
            const firstContent = parentContent.querySelector('#' + first.getAttribute('data-subtab'));
            if (firstContent) firstContent.classList.remove('d-none');
        }
    });

    // Handler اضافی برای زیرتب‌ها - حفظ منطق فعلی
    document.querySelectorAll('.sub-tab').forEach(stab => {
        stab.addEventListener('click', () => {
            const parent = stab.closest('.tab-content');

            // حذف active از همه دکمه‌ها
            parent.querySelectorAll('.sub-tab').forEach(btn => btn.classList.remove('active', 'btn-primary'));

            // مخفی کردن همه محتواها
            parent.querySelectorAll('.sub-tab-content').forEach(cnt => cnt.classList.add('d-none'));

            // فعال کردن دکمه و نمایش محتوای مربوطه
            stab.classList.add('active', 'btn-primary');
            document.getElementById(stab.getAttribute('data-subtab')).classList.remove('d-none');
        });
    });

    // Toggle profile field visibility بدون رفرش صفحه
    const toggleUrl = @json(route('profile.show.info'));
    const csrfToken = @json(csrf_token());

    document.querySelectorAll('a.js-visibility-toggle[data-field]').forEach(link => {
        link.addEventListener('click', async (e) => {
            e.preventDefault();

            // جلوگیری از کلیک‌های پشت سرهم
            if (link.dataset.loading === '1') return;
            link.dataset.loading = '1';
            link.classList.add('opacity-50', 'pointer-events-none');

            try {
                const res = await fetch(toggleUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ field: link.dataset.field })
                });

                if (!res.ok) throw new Error('Toggle failed');
                const data = await res.json();

                if (!data || data.ok !== true) throw new Error('Invalid response');

                const icon = link.querySelector('i');
                if (icon) {
                    // UI convention in this page: icon shows the *action* (not current state)
                    // value=1 => show_* enabled => action is "hide" => eye-slash
                    icon.className = (parseInt(data.value, 10) === 1) ? 'fas fa-eye-slash' : 'fas fa-eye';
                }
            } catch (err) {
                // fallback به رفتار قبلی (با رفرش صفحه) در صورت خطا
                window.location.assign(link.href);
            } finally {
                link.dataset.loading = '0';
                link.classList.remove('opacity-50', 'pointer-events-none');
            }
        });
    });
});
</script>
@endpush
@endsection

