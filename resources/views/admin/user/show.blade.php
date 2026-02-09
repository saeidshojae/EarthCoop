@extends('layouts.admin')

@section('title', 'جزئیات کاربر - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'جزئیات کاربر')
@section('page-description', 'مشاهده اطلاعات کامل کاربر: ' . $user->first_name . ' ' . $user->last_name)

@push('styles')
<style>
    .user-detail-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .user-detail-header {
        display: flex;
        align-items: center;
        gap: 2rem;
        padding-bottom: 2rem;
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .user-detail-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #667eea;
    }
    
    .user-detail-avatar-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 2.5rem;
        border: 4px solid #667eea;
    }
    
    .user-detail-info {
        flex: 1;
        min-width: 200px;
    }
    
    .user-detail-name {
        font-size: 2rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .user-detail-email {
        font-size: 1.125rem;
        color: #64748b;
        margin-bottom: 1rem;
    }
    
    .user-detail-badges {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .user-detail-badge {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .user-detail-badge.status.active {
        background: #d1fae5;
        color: #065f46;
    }
    
    .user-detail-badge.status.inactive {
        background: #e5e7eb;
        color: #374151;
    }
    
    .user-detail-badge.status.suspended {
        background: #fef3c7;
        color: #92400e;
    }
    
    .user-detail-badge.verified {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .user-detail-badge.online {
        background: #d1fae5;
        color: #065f46;
    }
    
    .user-detail-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .user-detail-btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .user-detail-btn.edit {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .user-detail-btn.edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }
    
    .user-detail-btn.profile {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
    }
    
    .user-detail-btn.profile:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        color: white;
    }
    
    .user-detail-btn.reset-password {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
    }
    
    .user-detail-btn.reset-password:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        color: white;
    }
    
    .user-detail-section {
        margin-bottom: 2rem;
    }
    
    .user-detail-section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .user-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }
    
    .user-detail-item {
        display: flex;
        flex-direction: column;
    }
    
    .user-detail-item-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 0.5rem;
    }
    
    .user-detail-item-value {
        font-size: 1rem;
        color: #1e293b;
        font-weight: 500;
    }
    
    .user-detail-item-value.empty {
        color: #9ca3af;
        font-style: italic;
    }
    
    .user-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .user-stat-item {
        background: #f9fafb;
        padding: 1rem;
        border-radius: 0.75rem;
        text-align: center;
    }
    
    .user-stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #667eea;
        margin-bottom: 0.25rem;
    }
    
    .user-stat-label {
        font-size: 0.875rem;
        color: #64748b;
    }
    
    .user-groups-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .user-group-item {
        background: #f9fafb;
        padding: 1rem;
        border-radius: 0.75rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .user-group-name {
        font-weight: 600;
        color: #1e293b;
    }
    
    .user-group-link {
        padding: 0.5rem 1rem;
        background: #667eea;
        color: white;
        border-radius: 0.5rem;
        text-decoration: none;
        font-size: 0.875rem;
        transition: all 0.2s;
    }
    
    .user-group-link:hover {
        background: #5568d3;
        color: white;
    }
    
    @media (prefers-color-scheme: dark) {
        .user-detail-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .user-detail-header {
            border-bottom-color: #475569 !important;
        }
        
        .user-detail-name {
            color: #f1f5f9 !important;
        }
        
        .user-detail-email {
            color: #94a3b8 !important;
        }
        
        .user-detail-section-title {
            color: #f1f5f9 !important;
            border-bottom-color: #475569 !important;
        }
        
        .user-detail-item-label {
            color: #94a3b8 !important;
        }
        
        .user-detail-item-value {
            color: #f1f5f9 !important;
        }
        
        .user-detail-item-value.empty {
            color: #64748b !important;
        }
        
        .user-stat-item {
            background: #334155 !important;
        }
        
        .user-stat-label {
            color: #94a3b8 !important;
        }
        
        .user-group-item {
            background: #334155 !important;
        }
        
        .user-group-name {
            color: #f1f5f9 !important;
        }
        
        .user-detail-badge.status.active {
            background: #064e3b !important;
            color: #a7f3d0 !important;
        }
        
        .user-detail-badge.status.inactive {
            background: #334155 !important;
            color: #cbd5e1 !important;
        }
        
        .user-detail-badge.status.suspended {
            background: #451a03 !important;
            color: #fed7aa !important;
        }
    }
    
    @media (max-width: 768px) {
        .user-detail-card {
            padding: 1rem;
        }
        
        .user-detail-header {
            flex-direction: column;
            text-align: center;
        }
        
        .user-detail-actions {
            width: 100%;
            flex-direction: column;
        }
        
        .user-detail-btn {
            width: 100%;
            justify-content: center;
        }
        
        .user-detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <!-- Header -->
    <div class="user-detail-card">
        <div class="user-detail-header">
            <div>
                @if($user->avatar)
                <img src="{{ asset('images/users/avatars/' . $user->avatar) }}" alt="Avatar" class="user-detail-avatar">
                @else
                <div class="user-detail-avatar-placeholder">
                    {{ mb_substr($user->first_name, 0, 1) }}{{ mb_substr($user->last_name, 0, 1) }}
                </div>
                @endif
            </div>
            <div class="user-detail-info">
                <div class="user-detail-name">
                    {{ $user->first_name . ' ' . $user->last_name }}
                </div>
                <div class="user-detail-email">
                    <i class="fas fa-envelope ml-2"></i>
                    {{ $user->email }}
                </div>
                <div class="user-detail-badges">
                    <span class="user-detail-badge status {{ $user->status ?? 'active' }}">
                        @if(($user->status ?? 'active') == 'active')
                            فعال
                        @elseif(($user->status ?? 'active') == 'inactive')
                            غیرفعال
                        @else
                            تعلیق شده
                        @endif
                    </span>
                    @if($user->email_verified_at)
                    <span class="user-detail-badge verified">
                        <i class="fas fa-check-circle ml-2"></i>
                        ایمیل تایید شده
                    </span>
                    @endif
                    @if($user->isOnline())
                    <span class="user-detail-badge online">
                        <i class="fas fa-circle ml-2"></i>
                        آنلاین
                    </span>
                    @endif
                </div>
            </div>
            <div class="user-detail-actions">
                <a href="{{ route('admin.users.sendMessage', $user->id) }}" class="user-detail-btn" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <i class="fas fa-paper-plane"></i>
                    ارسال پیام
                </a>
                <a href="{{ route('admin.users.edit', $user->id) }}" class="user-detail-btn edit">
                    <i class="fas fa-edit"></i>
                    ویرایش
                </a>
                <a href="{{ route('profile.member.show', $user->id) }}" target="_blank" class="user-detail-btn profile">
                    <i class="fas fa-user"></i>
                    مشاهده پروفایل
                </a>
                <form action="{{ route('admin.users.resetPassword', $user->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید رمز عبور این کاربر را بازنشانی کنید؟')">
                    @csrf
                    <button type="submit" class="user-detail-btn reset-password">
                        <i class="fas fa-key"></i>
                        بازنشانی رمز عبور
                    </button>
                </form>
                <form action="{{ route('admin.users.forceLogout', $user->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این کاربر را از همه دستگاه‌ها خارج کنید؟')">
                    @csrf
                    <button type="submit" class="user-detail-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <i class="fas fa-sign-out-alt"></i>
                        خروج اجباری
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- اطلاعات شخصی -->
    <div class="user-detail-card">
        <div class="user-detail-section">
            <h3 class="user-detail-section-title">
                <i class="fas fa-user ml-2"></i>
                اطلاعات شخصی
            </h3>
            <div class="user-detail-grid">
                <div class="user-detail-item">
                    <span class="user-detail-item-label">نام</span>
                    <span class="user-detail-item-value">{{ $user->first_name }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">نام خانوادگی</span>
                    <span class="user-detail-item-value">{{ $user->last_name }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">ایمیل</span>
                    <span class="user-detail-item-value">{{ $user->email }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">شماره تماس</span>
                    <span class="user-detail-item-value">{{ $user->phone }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">کد ملی</span>
                    <span class="user-detail-item-value">{{ $user->national_id }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">جنسیت</span>
                    <span class="user-detail-item-value">{{ $user->gender == 'male' ? 'مرد' : 'زن' }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">تاریخ تولد</span>
                    <span class="user-detail-item-value">{{ $user->birth_date }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">وضعیت</span>
                    <span class="user-detail-item-value">
                        <span class="user-detail-badge status {{ $user->status ?? 'active' }}">
                            @if(($user->status ?? 'active') == 'active')
                                فعال
                            @elseif(($user->status ?? 'active') == 'inactive')
                                غیرفعال
                            @else
                                تعلیق شده
                            @endif
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- آدرس -->
    @if($user->address)
    <div class="user-detail-card">
        <div class="user-detail-section">
            <h3 class="user-detail-section-title">
                <i class="fas fa-map-marker-alt ml-2"></i>
                اطلاعات آدرس
            </h3>
            <div class="user-detail-grid">
                <div class="user-detail-item">
                    <span class="user-detail-item-label">کشور</span>
                    <span class="user-detail-item-value">{{ $user->address->country->name ?? '-' }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">استان</span>
                    <span class="user-detail-item-value">{{ $user->address->province->name ?? '-' }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">شهرستان</span>
                    <span class="user-detail-item-value">{{ $user->address->county->name ?? '-' }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">بخش</span>
                    <span class="user-detail-item-value">{{ $user->address->section->name ?? '-' }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">شهر/روستا</span>
                    <span class="user-detail-item-value">{{ $user->address->city?->name ?? $user->address->rural?->name ?? '-' }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">منطقه/دهستان</span>
                    <span class="user-detail-item-value">{{ $user->address->region?->name ?? $user->address->village?->name ?? '-' }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">محله</span>
                    <span class="user-detail-item-value">{{ $user->address->neighborhood->name ?? '-' }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">خیابان</span>
                    <span class="user-detail-item-value">{{ $user->address->street->name ?? '-' }}</span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">کوچه</span>
                    <span class="user-detail-item-value">{{ $user->address->alley->name ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    <!-- صنف و تخصص -->
    <div class="user-detail-card">
        <div class="user-detail-section">
            <h3 class="user-detail-section-title">
                <i class="fas fa-briefcase ml-2"></i>
                صنف و تخصص
            </h3>
            <div class="user-detail-grid">
                <div class="user-detail-item">
                    <span class="user-detail-item-label">صنف</span>
                    <span class="user-detail-item-value">
                        @if($user->occupationalFields->count() > 0)
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            @foreach($user->occupationalFields as $field)
                            <li style="padding: 0.25rem 0;">• {{ $field->name }}</li>
                            @endforeach
                        </ul>
                        @else
                        <span class="user-detail-item-value empty">تعریف نشده</span>
                        @endif
                    </span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-item-label">تخصص</span>
                    <span class="user-detail-item-value">
                        @if($user->experienceFields->count() > 0)
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            @foreach($user->experienceFields as $field)
                            <li style="padding: 0.25rem 0;">• {{ $field->name }}</li>
                            @endforeach
                        </ul>
                        @else
                        <span class="user-detail-item-value empty">تعریف نشده</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- آمار و فعالیت -->
    <div class="user-detail-card">
        <div class="user-detail-section">
            <h3 class="user-detail-section-title">
                <i class="fas fa-chart-bar ml-2"></i>
                آمار و فعالیت
            </h3>
            <div class="user-stats-grid">
                <div class="user-stat-item">
                    <div class="user-stat-value">{{ $userStats['groups_count'] }}</div>
                    <div class="user-stat-label">گروه‌ها</div>
                </div>
                <div class="user-stat-item">
                    <div class="user-stat-value">{{ $userStats['total_posts_count'] }}</div>
                    <div class="user-stat-label">پست‌ها</div>
                    <div style="font-size: 0.7rem; color: #9ca3af; margin-top: 0.25rem;">
                        گروهی: {{ $userStats['blog_posts_count'] }} | وبلاگ: {{ $userStats['website_posts_count'] }}
                    </div>
                </div>
                <div class="user-stat-item">
                    <div class="user-stat-value">{{ $userStats['total_comments_count'] }}</div>
                    <div class="user-stat-label">نظرات</div>
                    <div style="font-size: 0.7rem; color: #9ca3af; margin-top: 0.25rem;">
                        گروهی: {{ $userStats['blog_comments_count'] }} | وبلاگ: {{ $userStats['website_comments_count'] }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- گروه‌ها -->
    @if($user->groups->count() > 0)
    <div class="user-detail-card">
        <div class="user-detail-section">
            <h3 class="user-detail-section-title">
                <i class="fas fa-users ml-2"></i>
                گروه‌های عضو
            </h3>
            <div class="user-groups-list">
                @foreach($user->groups as $group)
                <div class="user-group-item">
                    <span class="user-group-name">{{ $group->name }}</span>
                    <a href="{{ route('admin.groups.manage', $group) }}" class="user-group-link">
                        <i class="fas fa-eye ml-2"></i>
                        مشاهده
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
    
    <!-- نقش‌ها و دسترسی‌ها -->
    <div class="user-detail-card">
        <div class="user-detail-section">
            <h3 class="user-detail-section-title">
                <i class="fas fa-user-shield ml-2"></i>
                نقش‌ها و دسترسی‌ها
            </h3>
            
            <!-- نمایش نقش‌های فعلی -->
            <div style="margin-bottom: 1.5rem;">
                <div style="font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.75rem;">
                    نقش‌های فعلی ({{ $user->roles->count() }})
                </div>
                @if($user->roles->count() > 0)
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    @foreach($user->roles as $role)
                    <span style="padding: 0.5rem 1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600;">
                        {{ $role->name }}
                        @if($role->is_system)
                        <span style="font-size: 0.75rem; opacity: 0.9;">(سیستمی)</span>
                        @endif
                    </span>
                    @endforeach
                </div>
                @else
                <div style="color: #9ca3af; font-size: 0.875rem;">هیچ نقشی اختصاص داده نشده است</div>
                @endif
            </div>
            
            <!-- نمایش دسترسی‌ها -->
            @php
                $userPermissions = $user->getAllPermissions();
            @endphp
            <div style="margin-bottom: 1.5rem;">
                <div style="font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.75rem;">
                    دسترسی‌های کاربر ({{ $userPermissions->count() }})
                </div>
                @if($userPermissions->count() > 0)
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; max-height: 200px; overflow-y: auto;">
                    @foreach($userPermissions as $permission)
                    <span style="padding: 0.375rem 0.75rem; background: #e0e7ff; color: #4338ca; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500;">
                        {{ $permission->name }}
                    </span>
                    @endforeach
                </div>
                @else
                <div style="color: #9ca3af; font-size: 0.875rem;">هیچ دسترسی‌ای وجود ندارد</div>
                @endif
            </div>
            
            <!-- فرم اختصاص نقش -->
            <form action="{{ route('admin.users.assignRole', $user->id) }}" method="POST">
                @csrf
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.75rem;">
                        اختصاص نقش
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem;">
                        @foreach($allRoles as $role)
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" 
                                   id="role_{{ $role->id }}" 
                                   name="role_ids[]" 
                                   value="{{ $role->id }}"
                                   {{ $user->hasRole($role) ? 'checked' : '' }}
                                   style="width: 18px; height: 18px; cursor: pointer;">
                            <label for="role_{{ $role->id }}" style="font-size: 0.875rem; color: #4b5563; cursor: pointer; user-select: none;">
                                {{ $role->name }}
                                @if($role->is_system)
                                <span style="color: #9ca3af; font-size: 0.75rem;">(سیستمی)</span>
                                @endif
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                <button type="submit" style="padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; border: none; border-radius: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-save"></i>
                    ذخیره نقش‌ها
                </button>
            </form>
        </div>
    </div>
    
    <!-- اطلاعات سیستم -->
    <div class="user-detail-card">
        <div class="user-detail-section">
            <h3 class="user-detail-section-title">
                <i class="fas fa-info-circle ml-2"></i>
                اطلاعات سیستم
            </h3>
            <div class="user-detail-grid">
                @if($user->created_at)
                <div class="user-detail-item">
                    <span class="user-detail-item-label">تاریخ ثبت‌نام</span>
                    <span class="user-detail-item-value">
                        @php
                            $createdAt = null;
                            if ($user->created_at) {
                                if ($user->created_at instanceof \Carbon\Carbon) {
                                    $createdAt = $user->created_at;
                                } elseif (is_string($user->created_at) && !empty(trim($user->created_at))) {
                                    try {
                                        $createdAt = \Carbon\Carbon::parse($user->created_at);
                                    } catch (\Exception $e) {
                                        $createdAt = null;
                                    }
                                }
                            }
                        @endphp
                        @if($createdAt)
                            {{ \Morilog\Jalali\Jalalian::fromCarbon($createdAt)->format('Y/m/d H:i') }}
                        @else
                            {{ $user->created_at ?? '—' }}
                        @endif
                    </span>
                </div>
                @else
                <div class="user-detail-item">
                    <span class="user-detail-item-label">تاریخ ثبت‌نام</span>
                    <span class="user-detail-item-value empty">تعریف نشده</span>
                </div>
                @endif
                @if($user->updated_at)
                <div class="user-detail-item">
                    <span class="user-detail-item-label">آخرین بروزرسانی</span>
                    <span class="user-detail-item-value">
                    @php
                        $updatedAt = null;
                        if ($user->updated_at) {
                            if ($user->updated_at instanceof \Carbon\Carbon) {
                                $updatedAt = $user->updated_at;
                            } elseif (is_string($user->updated_at) && !empty(trim($user->updated_at))) {
                                try {
                                    $updatedAt = \Carbon\Carbon::parse($user->updated_at);
                                } catch (\Exception $e) {
                                    $updatedAt = null;
                                }
                            }
                        }
                    @endphp
                    @if($updatedAt)
                        {{ \Morilog\Jalali\Jalalian::fromCarbon($updatedAt)->format('Y/m/d H:i') }}
                    @else
                        {{ $user->updated_at ?? '—' }}
                    @endif
                    </span>
                </div>
                @else
                <div class="user-detail-item">
                    <span class="user-detail-item-label">آخرین بروزرسانی</span>
                    <span class="user-detail-item-value empty">تعریف نشده</span>
                </div>
                @endif
                @if($user->last_seen)
                <div class="user-detail-item">
                    <span class="user-detail-item-label">آخرین فعالیت</span>
                    <span class="user-detail-item-value">
                    @php
                        $lastSeen = null;
                        if ($user->last_seen) {
                            if ($user->last_seen instanceof \Carbon\Carbon) {
                                $lastSeen = $user->last_seen;
                            } elseif (is_string($user->last_seen) && !empty(trim($user->last_seen))) {
                                try {
                                    $lastSeen = \Carbon\Carbon::parse($user->last_seen);
                                } catch (\Exception $e) {
                                    $lastSeen = null;
                                }
                            }
                        }
                    @endphp
                    @if($lastSeen)
                        {{ \Morilog\Jalali\Jalalian::fromCarbon($lastSeen)->format('Y/m/d H:i') }}
                    @else
                        {{ $user->last_seen ?? '—' }}
                    @endif
                    </span>
                </div>
                @endif
                @if($user->email_verified_at)
                <div class="user-detail-item">
                    <span class="user-detail-item-label">تاریخ تایید ایمیل</span>
                    <span class="user-detail-item-value">
                        @php
                            $emailVerifiedAt = null;
                            if ($user->email_verified_at) {
                                if ($user->email_verified_at instanceof \Carbon\Carbon) {
                                    $emailVerifiedAt = $user->email_verified_at;
                                } elseif (is_string($user->email_verified_at) && !empty(trim($user->email_verified_at))) {
                                    try {
                                        $emailVerifiedAt = \Carbon\Carbon::parse($user->email_verified_at);
                                    } catch (\Exception $e) {
                                        $emailVerifiedAt = null;
                                    }
                                }
                            }
                        @endphp
                        @if($emailVerifiedAt)
                            {{ \Morilog\Jalali\Jalalian::fromCarbon($emailVerifiedAt)->format('Y/m/d H:i') }}
                        @else
                            {{ $user->email_verified_at ?? '—' }}
                        @endif
                    </span>
                </div>
                @endif
                @if($user->last_login_ip)
                <div class="user-detail-item">
                    <span class="user-detail-item-label">IP آخرین ورود</span>
                    <span class="user-detail-item-value">
                        {{ $user->last_login_ip }}
                        @if($user->last_login_at)
                        <span style="font-size: 0.75rem; color: #9ca3af; display: block; margin-top: 0.25rem;">
                            @php
                                $lastLoginAt = null;
                                if ($user->last_login_at) {
                                    if ($user->last_login_at instanceof \Carbon\Carbon) {
                                        $lastLoginAt = $user->last_login_at;
                                    } elseif (is_string($user->last_login_at) && !empty(trim($user->last_login_at))) {
                                        try {
                                            $lastLoginAt = \Carbon\Carbon::parse($user->last_login_at);
                                        } catch (\Exception $e) {
                                            $lastLoginAt = null;
                                        }
                                    }
                                }
                            @endphp
                            @if($lastLoginAt)
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($lastLoginAt)->format('Y/m/d H:i') }}
                            @else
                                {{ $user->last_login_at ?? '—' }}
                            @endif
                        </span>
                        @endif
                    </span>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Session های فعال -->
    @if(isset($activeSessions) && count($activeSessions) > 0)
    <div class="user-detail-card">
        <div class="user-detail-section">
            <h3 class="user-detail-section-title">
                <i class="fas fa-desktop ml-2"></i>
                Session های فعال ({{ count($activeSessions) }})
            </h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 0.75rem; text-align: right; font-weight: 600; color: #1e293b; font-size: 0.875rem;">IP</th>
                            <th style="padding: 0.75rem; text-align: right; font-weight: 600; color: #1e293b; font-size: 0.875rem;">مرورگر</th>
                            <th style="padding: 0.75rem; text-align: right; font-weight: 600; color: #1e293b; font-size: 0.875rem;">آخرین فعالیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activeSessions as $session)
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 0.75rem; color: #4b5563; font-size: 0.875rem;">{{ $session['ip_address'] }}</td>
                            <td style="padding: 0.75rem; color: #4b5563; font-size: 0.875rem;">{{ Str::limit($session['user_agent'], 50) }}</td>
                            <td style="padding: 0.75rem; color: #4b5563; font-size: 0.875rem;">
                                @php
                                    $lastActivity = null;
                                    if (isset($session['last_activity']) && $session['last_activity']) {
                                        if ($session['last_activity'] instanceof \Carbon\Carbon) {
                                            $lastActivity = $session['last_activity'];
                                        } elseif (is_string($session['last_activity']) && !empty(trim($session['last_activity']))) {
                                            try {
                                                $lastActivity = \Carbon\Carbon::parse($session['last_activity']);
                                            } catch (\Exception $e) {
                                                $lastActivity = null;
                                            }
                                        } elseif (is_numeric($session['last_activity'])) {
                                            try {
                                                $lastActivity = \Carbon\Carbon::createFromTimestamp($session['last_activity']);
                                            } catch (\Exception $e) {
                                                $lastActivity = null;
                                            }
                                        }
                                    }
                                @endphp
                                @if($lastActivity)
                                    {{ \Morilog\Jalali\Jalalian::fromCarbon($lastActivity)->format('Y/m/d H:i') }}
                                @else
                                    {{ $session['last_activity'] ?? '—' }}
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
    
    <!-- دکمه بازگشت -->
    <div style="text-align: left; margin-top: 2rem;">
        <a href="{{ route('admin.users.index') }}" style="padding: 0.75rem 1.5rem; background: #6b7280; color: white; border-radius: 0.75rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-arrow-right"></i>
            بازگشت به لیست کاربران
        </a>
    </div>
</div>
@endsection

