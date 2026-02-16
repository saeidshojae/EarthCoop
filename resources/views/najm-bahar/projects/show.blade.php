@extends('layouts.unified')

@section('title', 'جزئیات پروژه - نجم بهار')

@section('content')
<div class="container mx-auto px-4 py-8" dir="rtl">
    <div class="flex justify-between items-start mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $project->title }}</h1>
            <p class="text-gray-600 mt-2">{{ $project->category_path }}</p>
        </div>
        <div class="flex gap-2">
            @if(in_array($project->status, ['draft', 'rejected']))
                <a href="{{ route('najm-bahar.projects.edit', $project) }}" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg">ویرایش</a>
            @endif
            @if($project->status === 'draft')
                <form action="{{ route('najm-bahar.projects.submit', $project) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">ارسال برای بررسی</button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-3">خلاصه طرح</h2>
                <p class="text-gray-700 leading-relaxed">{{ $project->summary }}</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-3">توضیحات کامل</h2>
                <p class="text-gray-700 leading-relaxed whitespace-pre-line">{{ $project->description ?? '—' }}</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-3">پیوست‌ها</h2>
                @if(!empty($project->attachments))
                    <ul class="list-disc pr-6 text-sm text-gray-700">
                        @foreach($project->attachments as $file)
                            @php $path = $file['path'] ?? null; @endphp
                            <li>
                                @if($path)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($path) }}" target="_blank" class="text-blue-600 hover:underline">
                                        {{ $file['original_name'] ?? $path }}
                                    </a>
                                @else
                                    {{ $file['original_name'] ?? 'فایل پیوست' }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-gray-500">فایلی ثبت نشده است.</p>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">اطلاعات کلیدی</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">نوع پروژه:</span>
                        <span class="font-semibold">{{ $project->project_type === 'public' ? 'عمومی' : 'خصوصی' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">سرمایه مورد نیاز:</span>
                        <span class="font-semibold">{{ number_format($project->required_capital) }} گل</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">درصد سود:</span>
                        <span class="font-semibold text-green-600">{{ $project->profit_percentage }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">مدت:</span>
                        <span class="font-semibold">{{ $project->investment_duration_months ?? '—' }} ماه</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">وضعیت:</span>
                        <span class="font-semibold">{{ $project->status }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">تاریخچه بررسی</h2>
                @if($project->reviews->isEmpty())
                    <p class="text-gray-500 text-sm">هنوز بررسی انجام نشده است.</p>
                @else
                    <ul class="space-y-3 text-sm">
                        @foreach($project->reviews as $review)
                            <li class="border-r-2 border-gray-200 pr-3">
                                <div class="text-gray-700 font-semibold">{{ $review->action_label ?? $review->action }}</div>
                                <div class="text-gray-500">{{ $review->comment ?? '—' }}</div>
                                <div class="text-xs text-gray-400">{{ $review->created_at->diffForHumans() }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
