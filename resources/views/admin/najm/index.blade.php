@extends('layouts.app')

@section('content')
<div class="container py-4" dir="rtl">
        <h2 class="text-center mb-4 fw-bold">مدیریت صفحه نجم بهار</h2>

    
    @if(session('success'))
        <div class="alert alert-success text-center">{{ session('success') }}</div>
    @endif

    @php
            $setting = \App\Models\Setting::find(1);

    @endphp


        <form action="{{ route('admin.update.najm') }}" method="POST" class="mb-5">
            @csrf
            @method('PUT')
            <div class="mb-3">

                <label class="form-label mt-3">متن صفحه نجم</label>
                <textarea class="form-control" id="editor" name="najm_summary" rows="10" required>{{ old('najm_summary', $setting->najm_summary) }}</textarea>
                

            </div>
            <button type="submit" class="btn btn-success">ذخیره تغییرات</button>
        </form>



@push('scripts')
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>
    CKEDITOR.replace('editor', {
        filebrowserUploadUrl: "{{ route('admin.pages.upload') }}?_token={{ csrf_token() }}",
        filebrowserUploadMethod: 'form',
        language: 'fa',
        height: 100,
        extraPlugins: 'uploadimage',
        removeButtons: '',
        toolbarGroups: [
            { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
            { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align' ] },
            { name: 'styles' },
            { name: 'colors' },
            { name: 'insert' },
            { name: 'tools' },
            { name: 'editing' },
            { name: 'document', groups: [ 'mode', 'document' ] },
            { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
            { name: 'links' }
        ]
    });
</script>
@endpush
@endsection
