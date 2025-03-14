@extends('layouts.app')

@section('content')
<div class="container">
    <h1>مدیریت گروه‌ها</h1>
    <form action="{{ route('admin.groups.index') }}" method="GET" class="mb-4">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="جستجو در نام و محتوای گروه‌ها" value="{{ request('search') }}">
            <button type="submit" class="btn btn-primary">جستجو</button>
        </div>
    </form>
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
                                    <a href="{{ route('admin.groups.manage', $group->id) }}">{{ $group->name }}</a>
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
                                    <a href="{{ route('admin.groups.manage', $group->id) }}">{{ $group->name }}</a>
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
                                    <a href="{{ route('admin.groups.manage', $group->id) }}">{{ $group->name }}</a>
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
@endsection