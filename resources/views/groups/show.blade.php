@extends('layouts.app')

@section('content')
<div class="container">
    <h1>{{ $group->name }}</h1>
    <div class="messages">
        @foreach($messages as $message)
            <div class="message">
                <strong>{{ $message->user->name }}:</strong> {{ $message->message }}
            </div>
        @endforeach
    </div>
    <form action="{{ route('groups.messages.store', $group) }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="message">پیام:</label>
            <input type="text" name="message" id="message" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">ارسال</button>
    </form>

    <!-- فرم ارسال فایل -->
    <form action="{{ route('groups.files.store', $group) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group mt-3">
            <label for="file">ارسال فایل:</label>
            <input type="file" name="file" id="file" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary mt-2">ارسال</button>
    </form>
</div>

<div class="members mt-5">
    <h2>اعضای گروه</h2>
    <ul class="list-group">
        @foreach($group->users as $user)
            <li class="list-group-item">{{ $user->name }}</li>
        @endforeach
    </ul>
</div>

<!-- گوش دادن به رویدادهای Pusher -->
<script src="{{ mix('js/app.js') }}"></script>
<script>
    Echo.private('group.{{ $group->id }}')
        .listen('MessageSent', (e) => {
            console.log(e.message);
            // کد برای اضافه کردن پیام جدید به لیست پیام‌ها
            let messageElement = document.createElement('div');
            messageElement.classList.add('message');
            messageElement.innerHTML = `<strong>${e.message.user.name}:</strong> ${e.message.message}`;
            document.querySelector('.messages').appendChild(messageElement);
        });
</script>
@endsection