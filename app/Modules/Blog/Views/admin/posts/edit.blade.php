@extends('layouts.admin')

@section('title', 'ویرایش مقاله - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'ویرایش مقاله')
@section('page-description', 'ویرایش مقاله: ' . Str::limit($post->title, 50))

@push('styles')
<link href="https://cdn.ckeditor.com/4.21.0/full/ckeditor.js" rel="stylesheet">
<style>
    .blog-form-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .blog-form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .blog-form-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .blog-form-group {
        margin-bottom: 1.5rem;
    }
    
    .blog-form-label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .blog-form-label .required {
        color: #ef4444;
    }
    
    .blog-form-input, .blog-form-select, .blog-form-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
    }
    
    .blog-form-input:focus, .blog-form-select:focus, .blog-form-textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .blog-form-textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .blog-form-help {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
    
    .blog-form-checkbox {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .blog-form-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .blog-form-checkbox label {
        font-size: 0.875rem;
        color: #1e293b;
        cursor: pointer;
    }
    
    .blog-tags-container {
        max-height: 200px;
        overflow-y: auto;
        padding: 1rem;
        background: #f9fafb;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
    }
    
    .blog-tags-checkbox {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    
    .blog-tags-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .blog-tags-checkbox label {
        font-size: 0.875rem;
        color: #1e293b;
        cursor: pointer;
    }
    
    .blog-form-submit {
        padding: 0.75rem 2rem;
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
        width: 100%;
        justify-content: center;
    }
    
    .blog-form-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .blog-form-back {
        padding: 0.75rem 1.5rem;
        background: #f3f4f6;
        color: #374151;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .blog-form-back:hover {
        background: #e5e7eb;
        color: #1f2937;
    }
    
    .blog-form-view {
        padding: 0.75rem 2rem;
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        justify-content: center;
        margin-top: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .blog-form-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }
    
    .blog-error-alert {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 1rem;
        border-radius: 0.75rem;
        margin-bottom: 2rem;
    }
    
    .blog-error-alert ul {
        margin: 0;
        padding-right: 1.5rem;
    }
    
    .blog-error-alert li {
        margin-bottom: 0.5rem;
    }
    
    .blog-current-image {
        margin-bottom: 1rem;
    }
    
    .blog-current-image img {
        max-width: 100%;
        height: auto;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
    }
    
    .blog-stats-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .blog-stats-list li {
        padding: 0.5rem 0;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #4b5563;
        font-size: 0.875rem;
    }
    
    .blog-stats-list li:last-child {
        border-bottom: none;
    }
    
    .blog-stats-list li i {
        color: #64748b;
        width: 20px;
    }
    
    @media (prefers-color-scheme: dark) {
        .blog-form-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .blog-form-header h3 {
            color: #f1f5f9 !important;
        }
        
        .blog-form-label {
            color: #f1f5f9 !important;
        }
        
        .blog-form-input, .blog-form-select, .blog-form-textarea {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .blog-form-input:focus, .blog-form-select:focus, .blog-form-textarea:focus {
            border-color: #3b82f6 !important;
        }
        
        .blog-form-help {
            color: #94a3b8 !important;
        }
        
        .blog-form-checkbox label, .blog-tags-checkbox label {
            color: #f1f5f9 !important;
        }
        
        .blog-tags-container {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        
        .blog-form-back {
            background: #334155 !important;
            color: #f1f5f9 !important;
        }
        
        .blog-form-back:hover {
            background: #475569 !important;
        }
        
        .blog-error-alert {
            background: #450a0a !important;
            border-color: #b91c1c !important;
            color: #fecaca !important;
        }
        
        .blog-current-image img {
            border-color: #475569 !important;
        }
        
        .blog-stats-list li {
            border-bottom-color: #334155 !important;
            color: #cbd5e1 !important;
        }
        
        .blog-stats-list li i {
            color: #94a3b8 !important;
        }
    }
    
    @media (max-width: 768px) {
        .blog-form-card {
            padding: 1rem;
        }
        
        .blog-form-header {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <!-- Header -->
    <div class="blog-form-card">
        <div class="blog-form-header">
            <h3>
                <i class="fas fa-edit ml-2"></i>
                ویرایش مقاله: {{ Str::limit($post->title, 50) }}
            </h3>
            <a href="{{ route('admin.blog.posts') }}" class="blog-form-back">
                <i class="fas fa-arrow-right"></i>
                بازگشت
            </a>
        </div>
    </div>
    
    @if($errors->any())
    <div class="blog-error-alert">
        <strong>خطاهای زیر رخ داد:</strong>
        <ul>
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    
    <form action="{{ route('admin.blog.posts.update', $post) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Post Content -->
                <div class="blog-form-card">
                    <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">محتوای مقاله</h4>
                    
                    <div class="blog-form-group">
                        <label for="title" class="blog-form-label">
                            عنوان مقاله <span class="required">*</span>
                        </label>
                        <input type="text" 
                               class="blog-form-input" 
                               id="title" 
                               name="title" 
                               value="{{ old('title', $post->title) }}" 
                               required
                               placeholder="عنوان مقاله را وارد کنید">
                    </div>
                    
                    <div class="blog-form-group">
                        <label for="slug" class="blog-form-label">نامک (Slug)</label>
                        <input type="text" 
                               class="blog-form-input" 
                               id="slug" 
                               name="slug" 
                               value="{{ old('slug', $post->slug) }}" 
                               placeholder="نامک مقاله">
                    </div>
                    
                    <div class="blog-form-group">
                        <label for="excerpt" class="blog-form-label">خلاصه مقاله</label>
                        <textarea class="blog-form-textarea" 
                                  id="excerpt" 
                                  name="excerpt" 
                                  rows="3"
                                  placeholder="خلاصه کوتاهی از مقاله را وارد کنید">{{ old('excerpt', $post->excerpt) }}</textarea>
                    </div>
                    
                    <div class="blog-form-group">
                        <label for="content" class="blog-form-label">
                            محتوای کامل <span class="required">*</span>
                        </label>
                        <textarea class="blog-form-textarea" 
                                  id="content" 
                                  name="content" 
                                  rows="15" 
                                  required>{{ old('content', $post->content) }}</textarea>
                    </div>
                    
                    <div class="blog-form-group">
                        <label for="featured_image" class="blog-form-label">تصویر شاخص</label>
                        @if($post->featured_image)
                        <div class="blog-current-image">
                            <img src="{{ asset('images/blog/posts/' . $post->featured_image) }}" 
                                 alt="{{ $post->title }}">
                            <div class="blog-form-help">تصویر فعلی</div>
                        </div>
                        @endif
                        <input type="file" 
                               class="blog-form-input" 
                               id="featured_image" 
                               name="featured_image" 
                               accept="image/*">
                        <div class="blog-form-help">برای تغییر تصویر، یک فایل جدید انتخاب کنید</div>
                    </div>
                </div>
                
                <!-- SEO Section -->
                <div class="blog-form-card">
                    <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">تنظیمات SEO</h4>
                    
                    <div class="blog-form-group">
                        <label for="meta_title" class="blog-form-label">عنوان متا</label>
                        <input type="text" 
                               class="blog-form-input" 
                               id="meta_title" 
                               name="meta_title" 
                               value="{{ old('meta_title', $post->meta_title) }}"
                               placeholder="عنوان متا برای SEO">
                    </div>
                    
                    <div class="blog-form-group">
                        <label for="meta_description" class="blog-form-label">توضیحات متا</label>
                        <textarea class="blog-form-textarea" 
                                  id="meta_description" 
                                  name="meta_description" 
                                  rows="3"
                                  placeholder="توضیحات متا برای SEO">{{ old('meta_description', $post->meta_description) }}</textarea>
                    </div>
                    
                    <div class="blog-form-group">
                        <label for="meta_keywords" class="blog-form-label">کلمات کلیدی</label>
                        <input type="text" 
                               class="blog-form-input" 
                               id="meta_keywords" 
                               name="meta_keywords" 
                               value="{{ old('meta_keywords', $post->meta_keywords) }}" 
                               placeholder="با کاما جدا کنید">
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Publish Settings -->
                <div class="blog-form-card">
                    <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">تنظیمات انتشار</h4>
                    
                    <div class="blog-form-group">
                        <label for="status" class="blog-form-label">
                            وضعیت <span class="required">*</span>
                        </label>
                        <select class="blog-form-select" id="status" name="status" required>
                            <option value="draft" {{ old('status', $post->status) == 'draft' ? 'selected' : '' }}>پیش‌نویس</option>
                            <option value="published" {{ old('status', $post->status) == 'published' ? 'selected' : '' }}>منتشر شده</option>
                            <option value="archived" {{ old('status', $post->status) == 'archived' ? 'selected' : '' }}>بایگانی</option>
                        </select>
                    </div>
                    
                    <div class="blog-form-group">
                        <label for="published_at" class="blog-form-label">تاریخ انتشار</label>
                        <input type="datetime-local" 
                               class="blog-form-input" 
                               id="published_at" 
                               name="published_at" 
                               value="{{ old('published_at', $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : '') }}">
                    </div>
                    
                    <div class="blog-form-checkbox">
                        <input type="checkbox" 
                               id="is_featured" 
                               name="is_featured" 
                               value="1" 
                               {{ old('is_featured', $post->is_featured) ? 'checked' : '' }}>
                        <label for="is_featured">مقاله ویژه</label>
                    </div>
                    
                    <div class="blog-form-checkbox">
                        <input type="checkbox" 
                               id="allow_comments" 
                               name="allow_comments" 
                               value="1" 
                               {{ old('allow_comments', $post->allow_comments) ? 'checked' : '' }}>
                        <label for="allow_comments">امکان ثبت نظر</label>
                    </div>
                    
                    <div class="blog-form-group">
                        <label class="blog-form-label">آمار مقاله</label>
                        <ul class="blog-stats-list">
                            <li>
                                <i class="far fa-eye"></i>
                                <span>بازدید: {{ number_format($post->views_count ?? 0) }}</span>
                            </li>
                            <li>
                                <i class="far fa-comments"></i>
                                <span>نظرات: {{ $post->comments()->count() }}</span>
                            </li>
                            <li>
                                <i class="far fa-clock"></i>
                                <span>زمان مطالعه: {{ $post->reading_time ?? 0 }} دقیقه</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Category -->
                <div class="blog-form-card">
                    <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">دسته‌بندی</h4>
                    
                    <div class="blog-form-group">
                        <label for="category_id" class="blog-form-label">
                            دسته‌بندی <span class="required">*</span>
                        </label>
                        <select class="blog-form-select" id="category_id" name="category_id" required>
                            <option value="">انتخاب کنید...</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $post->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <!-- Tags -->
                <div class="blog-form-card">
                    <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-200">برچسب‌ها</h4>
                    
                    <div class="blog-tags-container">
                        @forelse($tags as $tag)
                        <div class="blog-tags-checkbox">
                            <input type="checkbox" 
                                   name="tags[]" 
                                   value="{{ $tag->id }}" 
                                   id="tag_{{ $tag->id }}" 
                                   {{ in_array($tag->id, old('tags', $post->tags->pluck('id')->toArray())) ? 'checked' : '' }}>
                            <label for="tag_{{ $tag->id }}">{{ $tag->name }}</label>
                        </div>
                        @empty
                        <div class="text-center text-gray-500 py-4">برچسبی یافت نشد.</div>
                        @endforelse
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="blog-form-card">
                    <button type="submit" class="blog-form-submit">
                        <i class="fas fa-save"></i>
                        ذخیره تغییرات
                    </button>
                    <a href="{{ route('blog.show', $post->slug) }}" class="blog-form-view" target="_blank">
                        <i class="fas fa-eye"></i>
                        نمایش مقاله
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.ckeditor.com/4.21.0/full/ckeditor.js"></script>
<script>
    // Initialize CKEditor
    CKEDITOR.replace('content', {
        language: 'fa',
        contentsLangDirection: 'rtl',
        height: 400,
        toolbar: [
            { name: 'document', items: ['Source', '-', 'Save', 'NewPage', 'Preview', 'Print', '-', 'Templates'] },
            { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo'] },
            { name: 'editing', items: ['Find', 'Replace', '-', 'SelectAll', '-', 'Scayt'] },
            { name: 'forms', items: ['Form', 'Checkbox', 'Radio', 'TextField', 'Textarea', 'Select', 'Button', 'ImageButton', 'HiddenField'] },
            '/',
            { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat'] },
            { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl', 'Language'] },
            { name: 'links', items: ['Link', 'Unlink', 'Anchor'] },
            { name: 'insert', items: ['Image', 'Flash', 'Table', 'HorizontalRule', 'Smiley', 'SpecialChar', 'PageBreak', 'Iframe'] },
            '/',
            { name: 'styles', items: ['Styles', 'Format', 'Font', 'FontSize'] },
            { name: 'colors', items: ['TextColor', 'BGColor'] },
            { name: 'tools', items: ['Maximize', 'ShowBlocks'] },
            { name: 'about', items: ['About'] }
        ]
    });
</script>
@endpush
