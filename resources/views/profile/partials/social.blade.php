<form action="{{ route('profile.update.social-network') }}" method="POST">
    @csrf
    @method('PUT')
    
    <div class="form-group-enhanced">
        <label class="form-label-enhanced">
            <i class="fas fa-share-alt"></i>
            لینک شبکه اجتماعی
        </label>
        
        <div id="dynamic-inputs">
            @php
                $storedLinks = $user->social_networks ?? [];
                if (is_string($storedLinks)) {
                    $storedLinks = json_decode($storedLinks, true);
                }
                $socialLinks = old('options', $storedLinks);
            @endphp

            @forelse($socialLinks as $index => $link)
                <div class="dynamic-input-group">
                    <input type="url" 
                           name="options[]" 
                           value="{{ old("options.$index", $link) }}"
                           class="form-input-enhanced" 
                           placeholder="لینک را وارد کنید (مثال: https://instagram.com/username)">
                    <button type="button" class="remove-btn" onclick="removeInput(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            @empty
                <div class="dynamic-input-group">
                    <input type="url" 
                           name="options[]" 
                           class="form-input-enhanced" 
                           placeholder="لینک را وارد کنید (مثال: https://instagram.com/username)">
                    <button type="button" class="remove-btn" onclick="removeInput(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            @endforelse
        </div>

        <button type="button" onclick="addInput()" class="add-btn">
            <i class="fas fa-plus ml-2"></i>
            افزودن لینک جدید
        </button>

        @error('options.*')
            <div class="text-red-500 text-sm mt-2">{{ $message }}</div>
        @enderror
    </div>

    <div class="flex justify-center mt-8">
        <button type="submit" class="submit-btn-enhanced">
            <i class="fas fa-save ml-2"></i>
            ذخیره تغییرات
        </button>
    </div>
</form>

<script>
    function addInput() {
        const container = document.getElementById('dynamic-inputs');
        const inputGroup = document.createElement('div');
        inputGroup.className = 'dynamic-input-group';

        inputGroup.innerHTML = `
            <input type="url" name="options[]" class="form-input-enhanced" placeholder="لینک را وارد کنید (مثال: https://instagram.com/username)">
            <button type="button" class="remove-btn" onclick="removeInput(this)">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(inputGroup);
        
        // Focus on new input
        inputGroup.querySelector('input').focus();
    }

    function removeInput(button) {
        const container = document.getElementById('dynamic-inputs');
        if (container.children.length > 1) {
            button.closest('.dynamic-input-group').remove();
        } else {
            alert('حداقل یک فیلد باید وجود داشته باشد');
        }
    }
</script>
