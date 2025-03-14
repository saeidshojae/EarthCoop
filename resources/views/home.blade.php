@extends('layouts.app')

@section('content')
<div class="container mt-5" style="direction: rtl; text-align: right;">
    <!-- سربرگ صفحه -->
    <div class="row justify-content-between align-items-center mb-4">
        <div class="col-md-8">
            <h1>گروه‌های عضویت شما</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('profile.show') }}" class="btn btn-outline-primary">مشاهده پروفایل</a>
        </div>
    </div>

    @php
        /*
         * فیلتر گروه‌ها بر اساس نوع (عمومی، تخصصی، اختصاصی)
         * فرض شده که ستون group_type شامل مقادیر general, specialized, exclusive است.
         */
        $generalGroups = $groups->filter(function($group) {
            return strtolower(trim($group->group_type)) === 'general';
        });
        $specializedGroups = $groups->filter(function($group) {
            return strtolower(trim($group->group_type)) === 'specialized';
        });
        $exclusiveGroups = $groups->filter(function($group) {
            return strtolower(trim($group->group_type)) === 'exclusive';
        });
    @endphp

    <!-- Accordion برای دسته‌بندی گروه‌ها -->
    <div class="accordion" id="groupAccordion">
        <!-- گروه‌های عمومی -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="generalGroupsHeading">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#generalGroups" aria-expanded="true" aria-controls="generalGroups">
                    گروه‌های عمومی
                </button>
            </h2>
            <div id="generalGroups" class="accordion-collapse collapse show" aria-labelledby="generalGroupsHeading" data-bs-parent="#groupAccordion">
                <div class="accordion-body">
                    @if($generalGroups->isNotEmpty())
                        <ul class="list-group">
                            @foreach($generalGroups as $group)
                                <li class="list-group-item">
                                    <a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p>گروهی یافت نشد.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- گروه‌های تخصصی -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="specializedGroupsHeading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#specializedGroups" aria-expanded="false" aria-controls="specializedGroups">
                    گروه‌های تخصصی
                </button>
            </h2>
            <div id="specializedGroups" class="accordion-collapse collapse" aria-labelledby="specializedGroupsHeading" data-bs-parent="#groupAccordion">
                <div class="accordion-body">
                    @if($specializedGroups->isNotEmpty())
                        <ul class="list-group">
                            @foreach($specializedGroups as $group)
                                <li class="list-group-item">
                                    <a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p>گروهی یافت نشد.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- گروه‌های اختصاصی -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="exclusiveGroupsHeading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#exclusiveGroups" aria-expanded="false" aria-controls="exclusiveGroups">
                    گروه‌های اختصاصی
                </button>
            </h2>
            <div id="exclusiveGroups" class="accordion-collapse collapse" aria-labelledby="exclusiveGroupsHeading" data-bs-parent="#groupAccordion">
                <div class="accordion-body">
                    @if($exclusiveGroups->isNotEmpty())
                        <ul class="list-group">
                            @foreach($exclusiveGroups as $group)
                                <li class="list-group-item">
                                    <a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p>گروهی یافت نشد.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- بارگذاری اسکریپت‌های Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@endsection
