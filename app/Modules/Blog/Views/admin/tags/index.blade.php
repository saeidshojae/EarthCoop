@extends('layouts.admin')

@section('title', 'مدیریت برچسب‌ها - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت برچسب‌ها')
@section('page-description', 'ایجاد، ویرایش و مدیریت برچسب‌های وبلاگ')

@push('styles')
<style>
    .blog-tags-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .blog-tags-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .blog-tags-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .blog-tags-form {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .blog-tags-input {
        flex: 1;
        min-width: 200px;
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
    }
    
    .blog-tags-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .blog-tags-btn {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
    }
    
    .blog-tags-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .blog-tags-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .blog-tag-card {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1rem;
        transition: all 0.2s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }
    
    .blog-tag-card:hover {
        background: #f3f4f6;
        border-color: #d1d5db;
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .blog-tag-info {
        flex: 1;
    }
    
    .blog-tag-name {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .blog-tag-meta {
        font-size: 0.75rem;
        color: #64748b;
    }
    
    .blog-tag-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .blog-tag-action-btn {
        padding: 0.5rem;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
    }
    
    .blog-tag-action-btn.edit {
        background: #fef3c7;
        color: #92400e;
    }
    
    .blog-tag-action-btn.edit:hover {
        background: #fde68a;
    }
    
    .blog-tag-action-btn.delete {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .blog-tag-action-btn.delete:hover {
        background: #fecaca;
    }
    
    .blog-tag-edit-form {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex: 1;
    }
    
    .blog-tag-edit-input {
        flex: 1;
        padding: 0.5rem 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
    }
    
    .blog-tag-edit-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .blog-tag-edit-btn {
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        border: none;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.75rem;
    }
    
    .blog-tag-edit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .blog-tag-cancel-btn {
        padding: 0.5rem 1rem;
        background: #f3f4f6;
        color: #374151;
        border: none;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.75rem;
    }
    
    .blog-tag-cancel-btn:hover {
        background: #e5e7eb;
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 0;
        color: #64748b;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .empty-state-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .empty-state-text {
        font-size: 1rem;
        color: #64748b;
    }
    
    @media (prefers-color-scheme: dark) {
        .blog-tags-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .blog-tags-header h3 {
            color: #f1f5f9 !important;
        }
        
        .blog-tags-input {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .blog-tags-input:focus {
            border-color: #3b82f6 !important;
        }
        
        .blog-tag-card {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        
        .blog-tag-card:hover {
            background: #475569 !important;
            border-color: #64748b !important;
        }
        
        .blog-tag-name {
            color: #f1f5f9 !important;
        }
        
        .blog-tag-meta {
            color: #94a3b8 !important;
        }
        
        .blog-tag-edit-input {
            background: #475569 !important;
            border-color: #64748b !important;
            color: #f1f5f9 !important;
        }
        
        .blog-tag-edit-input:focus {
            border-color: #3b82f6 !important;
        }
        
        .blog-tag-cancel-btn {
            background: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .blog-tag-cancel-btn:hover {
            background: #64748b !important;
        }
        
        .empty-state-title {
            color: #f1f5f9 !important;
        }
        
        .empty-state-text {
            color: #94a3b8 !important;
        }
        
        .blog-error-alert {
            background: #450a0a !important;
            border-color: #b91c1c !important;
            color: #fecaca !important;
        }
    }
    
    @media (max-width: 768px) {
        .blog-tags-card {
            padding: 1rem;
        }
        
        .blog-tags-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .blog-tags-form {
            flex-direction: column;
        }
        
        .blog-tags-input {
            width: 100%;
        }
        
        .blog-tags-card {
            padding: 1rem;
        }
        
        .blog-tag-card {
            flex-direction: column;
            align-items: stretch;
        }
        
        .blog-tag-actions {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <!-- Header -->
    <div class="blog-tags-card">
        <div class="blog-tags-header">
            <h3>
                <i class="fas fa-tags ml-2"></i>
                مدیریت برچسب‌ها
            </h3>
        </div>
        
        <!-- Create Form -->
        <form action="{{ route('admin.blog.tags.store') }}" method="POST" class="blog-tags-form">
            @csrf
            <input type="text" 
                   class="blog-tags-input" 
                   name="name" 
                   placeholder="نام برچسب را وارد کنید" 
                   required
                   value="{{ old('name') }}">
            <button type="submit" class="blog-tags-btn">
                <i class="fas fa-plus"></i>
                افزودن برچسب
            </button>
        </form>
        
        @if($errors->any())
        <div class="blog-error-alert" style="margin-bottom: 1rem;">
            <strong>خطاهای زیر رخ داد:</strong>
            <ul>
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
    
    <!-- Tags Grid -->
    <div class="blog-tags-card">
        @forelse($tags as $tag)
        <div class="blog-tag-card" id="tag-{{ $tag->id }}">
            <div class="blog-tag-info">
                <div class="blog-tag-name">{{ $tag->name }}</div>
                <div class="blog-tag-meta">{{ $tag->posts_count }} مقاله</div>
            </div>
            <div class="blog-tag-actions">
                <button type="button" 
                        class="blog-tag-action-btn edit"
                        onclick="editTag({{ $tag->id }}, '{{ $tag->name }}')"
                        title="ویرایش">
                    <i class="fas fa-edit"></i>
                </button>
                <form action="{{ route('admin.blog.tags.delete', $tag) }}" 
                      method="POST" 
                      class="d-inline"
                      onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این برچسب را حذف کنید؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="blog-tag-action-btn delete" title="حذف">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Edit Form (Hidden by default) -->
        <div class="blog-tag-card" id="edit-tag-{{ $tag->id }}" style="display: none;">
            <form action="{{ route('admin.blog.tags.update', $tag) }}" method="POST" class="blog-tag-edit-form">
                @csrf
                @method('PUT')
                <input type="text" 
                       class="blog-tag-edit-input" 
                       name="name" 
                       value="{{ $tag->name }}"
                       required
                       id="edit-name-{{ $tag->id }}">
                <button type="submit" class="blog-tag-edit-btn">
                    <i class="fas fa-save"></i>
                    ذخیره
                </button>
                <button type="button" 
                        class="blog-tag-cancel-btn"
                        onclick="cancelEditTag({{ $tag->id }}, '{{ $tag->name }}')">
                    <i class="fas fa-times"></i>
                    لغو
                </button>
            </form>
        </div>
        @empty
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-tags"></i>
            </div>
            <div class="empty-state-title">برچسبی یافت نشد</div>
            <div class="empty-state-text">هنوز هیچ برچسبی ایجاد نشده است.</div>
        </div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
    function editTag(id, name) {
        document.getElementById('tag-' + id).style.display = 'none';
        document.getElementById('edit-tag-' + id).style.display = 'flex';
        document.getElementById('edit-name-' + id).focus();
    }
    
    function cancelEditTag(id, name) {
        document.getElementById('tag-' + id).style.display = 'flex';
        document.getElementById('edit-tag-' + id).style.display = 'none';
        document.getElementById('edit-name-' + id).value = name;
    }
</script>
@endpush

