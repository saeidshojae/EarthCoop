<div id="groupEditFormBox" class="group-edit-modal" style="display: none;" aria-hidden="true">
    <button type="button"
            class="group-edit-modal__backdrop"
            data-group-chat-action="cancel-group-edit"
            aria-label="بستن ویرایش گروه"></button>

    <div class="group-edit-modal__dialog"
         role="dialog"
         aria-modal="true"
         aria-labelledby="groupEditModalTitle">
        <div class="group-edit-modal__header">
            <div>
                <span class="group-edit-modal__eyebrow">مدیریت گروه</span>
                <h2 id="groupEditModalTitle">ویرایش اطلاعات گروه</h2>
            </div>
            <button type="button"
                    class="group-edit-modal__close"
                    data-group-chat-action="cancel-group-edit"
                    aria-label="بستن">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <form id="groupEditForm" action="{{ route('groups.update', $group) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @if($errors->any())
                <div class="alert alert-danger mb-3" role="alert">
                    <strong>تغییرات ذخیره نشد.</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form-group mb-3">
                <label for="description">توضیحات گروه</label>
                <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $group->description) }}</textarea>
                @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="form-group mb-3">
                <label for="avatar">آواتار گروه</label>
                <input type="file" name="avatar" id="avatar" class="form-control" accept="image/*">
                @error('avatar')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @if($group->avatar_url)
                    <div class="group-edit-modal__current-avatar mt-2">
                        <img src="{{ $group->avatar_url }}" alt="آواتار فعلی گروه">
                    </div>
                @endif
            </div>

            <div class="group-edit-modal__actions">
                <button type="submit" class="btn btn-success flex-grow-1">ذخیره تغییرات</button>
                <button type="button" class="btn btn-light" data-group-chat-action="cancel-group-edit">لغو</button>
            </div>
        </form>
    </div>
</div>
