@csrf
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6">
        <div class="mb-4">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">عنوان</label>
            <input type="text" name="title" value="{{ old('title', $article->title ?? '') }}" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Slug (اختیاری)</label>
            <input type="text" name="slug" value="{{ old('slug', $article->slug ?? '') }}" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" placeholder="example-article">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">دسته‌بندی</label>
                <select name="category_id" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    <option value="">انتخاب کنید</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $article->category_id ?? '') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">وضعیت</label>
                <select name="status" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    <option value="draft" @selected(old('status', $article->status ?? 'draft') === 'draft')>پیش‌نویس</option>
                    <option value="published" @selected(old('status', $article->status ?? '') === 'published')>منتشر شده</option>
                    <option value="archived" @selected(old('status', $article->status ?? '') === 'archived')>آرشیو</option>
                </select>
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">چکیده</label>
            <textarea name="excerpt" rows="2" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('excerpt', $article->excerpt ?? '') }}</textarea>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">محتوا</label>
            <textarea name="content" rows="10" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" placeholder="محتوای مقاله...">{{ old('content', $article->content ?? '') }}</textarea>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">تگ‌ها</label>
            <div class="space-y-2 max-h-64 overflow-y-auto">
                @foreach($tags as $tag)
                    <label class="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="rounded border-slate-300 dark:border-slate-600"
                            @checked(in_array($tag->id, old('tags', isset($article) ? $article->tags->pluck('id')->toArray() : [])))>
                        <span>{{ $tag->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_featured" value="1" class="rounded border-slate-300 dark:border-slate-600"
                        @checked(old('is_featured', $article->is_featured ?? false))>
                    نمایش به عنوان مقاله ویژه
                </label>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">تاریخ انتشار</label>
                <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($article->published_at ?? null)->format('Y-m-d\TH:i')) }}" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
            </div>
        </div>
        <div class="flex gap-4">
            <button type="submit" class="flex-1 inline-flex justify-center items-center px-4 py-2 text-white bg-green-600 rounded-lg hover:bg-green-700 font-semibold">
                ذخیره مقاله
            </button>
            <a href="{{ route('admin.kb.articles.index') }}" class="flex-1 inline-flex justify-center items-center px-4 py-2 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700">
                انصراف
            </a>
        </div>
    </div>
</div>




