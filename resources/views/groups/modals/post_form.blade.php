<link rel="stylesheet" href="{{ asset('Css/group-chat-modals-responsive.css') }}?v={{ filemtime(public_path('Css/group-chat-modals-responsive.css')) }}">

<style>
/* The chat page historically hides CKEditor chrome globally for compact editors.
   Post composition is intentionally richer, but keep its editor compact and scoped here. */
#postFormBox .cke {
    width: 100% !important;
    max-width: 100% !important;
}

#postFormBox .cke_top {
    display: block !important;
    padding: 6px 8px !important;
}

#postFormBox .cke_bottom {
    display: none !important;
}

#postFormBox .cke_contents {
    min-height: 180px !important;
    height: 180px !important;
}

#postFormBox .cke_toolgroup {
    margin: 0 4px 4px 0 !important;
}

#postFormBox .cke_button {
    padding: 4px 5px !important;
}

@media (max-width: 767.98px) {
    #postFormBox .cke_top {
        padding: 5px 6px !important;
    }

    #postFormBox .cke_contents {
        min-height: 160px !important;
        height: 160px !important;
    }
}
</style>

<div id="postFormBox" class="modal-shell" style="display: none;" dir="rtl" data-composer-modal="post">
    <div class="modal-shell__dialog">
        <div class="modal-shell__header">
            <h3 class="modal-shell__title">
                <i class="far fa-pen-to-square me-2 text-emerald-500"></i>
                ایجاد پست جدید
            </h3>
            <button type="button" class="modal-shell__close" data-group-chat-action="close-post-modal">×</button>
        </div>

        <form id="postForm" class="modal-shell__form" action="{{ route('groups.blog.store', $group) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-field">
                <label for="post_title" class="modal-label">عنوان پست</label>
                <input id="post_title" type="text" name="title" class="modal-input" placeholder="مثلاً گزارش جلسه اخیر گروه">
            </div>

            <div class="modal-field">
                <label for="post_img" class="modal-label">تصویر یا فایل پیوست</label>
                <input id="post_img" type="file" name="img" class="modal-input modal-input--file">
                <p class="modal-hint">فرمت‌های مجاز: تصویر، ویدئو یا صوت. حداکثر حجم فایل ۱۲ مگابایت.</p>
            </div>

            <div class="modal-field">
                <label for="post_editor" class="modal-label">متن پست</label>
                <textarea name="content" id="post_editor" class="modal-textarea" placeholder="متن پست را اینجا بنویسید…" required></textarea>
                <p class="modal-hint">برای تأکید، لینک و فهرست‌ها از ویرایشگر ساده متن استفاده کنید.</p>
            </div>

            <div class="modal-field">
                <label for="post_category" class="modal-label">دسته‌بندی</label>
                <select id="post_category" class="modal-input" name="category_id">
                    <option value="">انتخاب دسته‌بندی</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="modal-shell__actions">
                <button type="button" class="btn btn-outline-secondary" data-group-chat-action="close-post-modal">انصراف</button>
                <button type="submit" class="btn btn-success" style="background-color: #10b981; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 600; cursor: pointer;">انتشار پست</button>
            </div>
        </form>

        <div class="modal-shell__errors">
            @error('img')
                <span class="text-danger d-block">{{ $message }}</span>
            @enderror
            @error('category_id')
                <span class="text-danger d-block">{{ $message }}</span>
            @enderror
            @error('content')
                <span class="text-danger d-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>
