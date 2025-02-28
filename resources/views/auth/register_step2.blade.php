@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg rounded">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="font-weight-bold">مرحله ۲: انتخاب رسته‌های صنفی و تخصص‌ها</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('register.step2.post') }}">
                        @csrf

                        <!-- انتخاب رسته‌های صنفی -->
                        <h5 class="text-primary">انتخاب رسته‌های صنفی</h5>
                        <div class="form-group">
                            <label for="job_fields">رسته‌های صنفی:</label>
                            <select id="job_fields" name="job_fields[]" class="form-control" multiple>
                                @foreach ($jobFields as $field)
                                    <optgroup label="{{ $field->title }}">
                                        @foreach ($field->children as $child)
                                            <option value="{{ $child->id }}">{{ $child->title }}</option>
                                            @foreach ($child->children as $subchild)
                                                <option value="{{ $subchild->id }}">-- {{ $subchild->title }}</option>
                                            @endforeach
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">می‌توانید چند گزینه را انتخاب کنید.</small>
                        </div>

                        <!-- انتخاب تخصص‌ها -->
                        <h5 class="text-primary mt-4">انتخاب تخصص‌ها</h5>
                        <div class="form-group">
                            <label for="specializations">تخصص‌ها:</label>
                            <select id="specializations" name="specializations[]" class="form-control" multiple>
                                @foreach ($specializations as $specialization)
                                    <optgroup label="{{ $specialization->title }}">
                                        @foreach ($specialization->children as $child)
                                            <option value="{{ $child->id }}">{{ $child->title }}</option>
                                            @foreach ($child->children as $subchild)
                                                <option value="{{ $subchild->id }}">-- {{ $subchild->title }}</option>
                                            @endforeach
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">می‌توانید چند گزینه را انتخاب کنید.</small>
                        </div>

                        <button type="submit" class="btn btn-success w-100 mt-4">ادامه</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
