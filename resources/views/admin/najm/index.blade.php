@extends('layouts.admin')

@section('title', 'مدیریت صفحه نجم بهار - ' . config('app.name', 'EarthCoop'))

@section('content')
<div class="container py-4 py-md-5" dir="rtl">
    <div class="mx-auto" style="max-width: 980px;">
        <div class="bg-white rounded-3 shadow-sm border p-4 p-md-5">
            <div class="mb-4">
                <h1 class="h3 fw-bold mb-2">مدیریت صفحه نجم بهار</h1>
                <p class="text-muted mb-0">متن معرفی صفحه نجم بهار را ویرایش و ذخیره کنید.</p>
            </div>

            @php
                $setting = \App\Models\Setting::find(1);
            @endphp

            <form action="{{ route('admin.update.najm') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="editor" class="form-label fw-semibold">متن صفحه نجم</label>
                    <textarea
                        class="form-control"
                        id="editor"
                        name="najm_summary"
                        rows="10"
                        required
                    >{{ old('najm_summary', $setting?->najm_summary) }}</textarea>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success px-4">ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/ckeditor/ckeditor.js') }}"></script>
<script>
    CKEDITOR.replace('editor', {
        filebrowserUploadUrl: "{{ route('admin.pages.upload') }}?_token={{ csrf_token() }}",
        filebrowserUploadMethod: 'form',
        language: 'fa',
        height: 100,
        extraPlugins: 'uploadimage',
        removeButtons: '',
        toolbarGroups: [
            { name: 'basicstyles', groups: ['basicstyles', 'cleanup'] },
            { name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align'] },
            { name: 'styles' },
            { name: 'colors' },
            { name: 'insert' },
            { name: 'tools' },
            { name: 'editing' },
            { name: 'document', groups: ['mode', 'document'] },
            { name: 'clipboard', groups: ['clipboard', 'undo'] },
            { name: 'links' }
        ]
    });
</script>
@endpush
