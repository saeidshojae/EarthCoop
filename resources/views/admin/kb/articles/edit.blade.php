@extends('layouts.admin')

@section('title', 'ویرایش مقاله')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-edit ml-2"></i>
                ویرایش مقاله
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                {{ $article->title }}
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6 text-sm text-red-700 dark:text-red-200">
            <ul class="space-y-1 list-disc pr-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.kb.articles.update', $article) }}">
        @method('PUT')
        @include('admin.kb.articles._form')
    </form>
</div>
@endsection




