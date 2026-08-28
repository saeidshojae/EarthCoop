<div id="groupEditFormBox" class="modal-shell group-edit-modal" style="display: none;" aria-hidden="true">
    <div class="modal-shell__dialog group-edit-modal__dialog"
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

<style>
    /* Keep this geometry with the modal itself so later Control Center styles
       cannot move it into a partial viewport or corner. */
    html body #groupEditFormBox.modal-shell {
        position: fixed !important;
        inset: 0 !important;
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        left: 0 !important;
        z-index: 100100 !important;
        width: auto !important;
        height: auto !important;
        max-width: none !important;
        max-height: none !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        background: rgba(15, 23, 42, .52) !important;
        backdrop-filter: blur(4px);
    }

    html body #groupEditFormBox.modal-shell > .group-edit-modal__dialog {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        right: auto !important;
        bottom: auto !important;
        transform: translate(-50%, -50%) !important;
        margin: 0 !important;
        z-index: 100101 !important;
        width: min(560px, calc(100% - 2rem)) !important;
        max-width: 560px !important;
        max-height: calc(100% - 2rem) !important;
        overflow-y: auto !important;
    }

    @media (max-width: 767px) {
        html body #groupEditFormBox.modal-shell > .group-edit-modal__dialog {
            width: calc(100% - 1.25rem) !important;
            max-width: none !important;
            max-height: calc(100% - 1.25rem) !important;
        }
    }
</style>
