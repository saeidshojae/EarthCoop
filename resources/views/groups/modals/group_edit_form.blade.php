<div id="groupEditModalShell" class="modal-shell group-edit-modal" style="display: none;" aria-hidden="true">
    <div class="modal-shell__dialog group-edit-modal__dialog"
         style="position: relative; width: min(500px, 94vw); background: #fff; border-radius: 28px; padding: 1.75rem; box-shadow: 0 45px 95px -45px rgba(15, 23, 42, 0.6);"
         role="dialog"
         aria-modal="true"
         aria-labelledby="groupEditModalTitle">
        <div class="modal-shell__header">
            <h3 id="groupEditModalTitle" class="modal-shell__title">
                <i class="fas fa-pen-to-square text-emerald-500" aria-hidden="true"></i>
                ویرایش اطلاعات گروه
            </h3>
            <button type="button"
                    class="modal-shell__close group-edit-modal__close"
                    data-group-chat-action="cancel-group-edit"
                    aria-label="بستن">×</button>
        </div>

        <form id="groupEditForm"
              class="modal-shell__form"
              action="{{ route('groups.update', $group) }}"
              method="POST"
              enctype="multipart/form-data">
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

            <div class="modal-field">
                <label class="modal-label" for="description">توضیحات گروه</label>
                <textarea name="description" id="description" class="modal-textarea" rows="4">{{ old('description', $group->description) }}</textarea>
                @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="modal-field">
                <label class="modal-label" for="avatar">آواتار گروه</label>
                <input type="file" name="avatar" id="avatar" class="modal-input--file" accept="image/*">
                @error('avatar')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @if($group->avatar_url)
                    <div class="group-edit-modal__current-avatar mt-2">
                        <img src="{{ $group->avatar_url }}"
                             alt="آواتار فعلی گروه"
                             style="width:76px;height:76px;object-fit:cover;border:1px solid #d1fae5;border-radius:16px;">
                    </div>
                @endif
            </div>

            <div class="modal-shell__actions">
                <button type="submit" class="btn btn-success flex-grow-1">ذخیره تغییرات</button>
                <button type="button" class="btn btn-light" data-group-chat-action="cancel-group-edit">لغو</button>
            </div>
        </form>
    </div>
</div>
