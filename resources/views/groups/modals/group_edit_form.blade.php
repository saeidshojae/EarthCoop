<div id="groupEditFormBox" style="display: none;">
    <form id="groupEditForm" action="{{ route('groups.update', $group) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <div class="form-group mb-3">
            <label for="description">توضیحات گروه</label>
            <textarea name="description" id="description" class="form-control" rows="3">{{ $group->description }}</textarea>
        </div>

        <div class="form-group mb-3">
            <label for="avatar">آواتار گروه</label>
            <input type="file" name="avatar" id="avatar" class="form-control" accept="image/*">
            @if($group->avatar)
                <div class="mt-2">
                    <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="Current avatar" style="max-width: 100px;">
                </div>
            @endif
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">ذخیره تغییرات</button>
            <button type="button" class="btn btn-secondary" onclick="cancelGroupEdit()" style='    background-color: red !important;'>لغو</button>
        </div>
    </form>
</div> 