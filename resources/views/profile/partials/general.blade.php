@if($user->edited == 0)
    <div class="warning-box">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>توجه:</strong> اطلاعات هویتی شما فقط برای یکبار قابلیت ویرایش دارد، لطفا در وارد کردن اطلاعات دقت فرمایید.
        </div>
    </div>
@endif

<form action="{{ route('profile.update.general') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- First Name -->
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-user"></i>
                نام
            </label>
            <input type="text" 
                   name="first_name" 
                   placeholder="نام خود را وارد کنید" 
                   class="form-input-enhanced" 
                   @if($user->status == 1) {{ $user->edited == 1 ? 'disabled' : '' }} @endif 
                   @error('first_name') style="border-color: #ef4444;" @enderror 
                   value="{{ old('first_name', auth()->user()->first_name) }}">
            @error('first_name')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
        </div>

        <!-- Last Name -->
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-user"></i>
                نام خانوادگی
            </label>
            <input type="text" 
                   name="last_name" 
                   placeholder="نام خانوادگی خود را وارد کنید" 
                   class="form-input-enhanced" 
                   @if($user->status == 1) {{ $user->edited == 1 ? 'disabled' : '' }} @endif 
                   @error('last_name') style="border-color: #ef4444;" @enderror 
                   value="{{ old('last_name', auth()->user()->last_name) }}">
            @error('last_name')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
        </div>

        <!-- Email -->
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-envelope"></i>
                ایمیل
            </label>
            <input type="email" 
                   name="email" 
                   disabled 
                   placeholder="ایمیل خود را وارد کنید" 
                   class="form-input-enhanced" 
                   @error('email') style="border-color: #ef4444;" @enderror 
                   value="{{ old('email', auth()->user()->email) }}">
            @error('email')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
            <small class="text-gray-500 text-sm">ایمیل قابل تغییر نیست</small>
        </div>

        <!-- Phone -->
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-phone"></i>
                شماره تلفن
            </label>
            <div style="display: flex; position: relative;">
                <input type="text" 
                       name="phone" 
                       id="phone" 
                       {{ $user->status == 1 ? 'disabled' : '' }} 
                       class="form-input-enhanced" 
                       style="padding-right: 120px;"
                       @error('phone') style="border-color: #ef4444;" @enderror 
                       placeholder="برای مثال: 9123456789" 
                       value="{{ old('phone', auth()->user()->phone) }}">
                <select name="country_code" 
                        class="form-input-enhanced" 
                        id="country_code" 
                        onchange="updatePlaceholder()" 
                        style="position: absolute; right: 2px; width: 115px; border: none; border-right: 2px solid #e5e7eb; border-radius: 0.75rem 0 0 0.75rem; background: #f3f4f6;">
                    @foreach ($countryCodes as $country)
                        <option value="{{ $country['code'] }}"
                            data-placeholder="{{ $country['example'] }}"
                            {{ old('country_code', '+98') == $country['code'] ? 'selected' : '' }}>
                            {{ $country['flag'] }} {{ $country['code'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            @error('phone')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
        </div>

        <!-- Birth Date -->
        @php
            use Morilog\Jalali\Jalalian;
            $birthDate = $user->birth_date ? Jalalian::fromDateTime($user->birth_date) : null;
            $defaultDay   = $birthDate ? $birthDate->getDay() : null;
            $defaultMonth = $birthDate ? $birthDate->getMonth() : null;
            $defaultYear  = $birthDate ? $birthDate->getYear() : null;
        @endphp
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-calendar-alt"></i>
                تاریخ تولد
            </label>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <select name="birth_date[]" 
                        @if($user->status == 1) {{ $user->edited == 1 ? 'disabled' : '' }} @endif 
                        class="form-input-enhanced" 
                        style="flex: 1;">
                    <option value="">روز</option>
                    @for ($i = 1; $i <= 31; $i++)
                        <option value="{{ $i }}" {{ old('birth_date.0', $defaultDay) == $i ? 'selected' : '' }}>
                            {{ $i }}
                        </option>
                    @endfor
                </select>
                <select name="birth_date[]" 
                        @if($user->status == 1) {{ $user->edited == 1 ? 'disabled' : '' }} @endif 
                        class="form-input-enhanced" 
                        style="flex: 1;">
                    <option value="">ماه</option>
                    @php
                        $months = [
                            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
                            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
                            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
                            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
                        ];
                    @endphp
                    @foreach ($months as $num => $name)
                        <option value="{{ $num }}" {{ old('birth_date.1', $defaultMonth) == $num ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
                @php
                    $currentYear = Jalalian::now()->getYear() - 15;
                    $startYear = $currentYear - 135;
                @endphp
                <select name="birth_date[]" 
                        @if($user->status == 1) {{ $user->edited == 1 ? 'disabled' : '' }} @endif 
                        class="form-input-enhanced" 
                        style="flex: 1;">
                    <option value="">سال</option>
                    @for ($i = $currentYear; $i >= $startYear; $i--)
                        <option value="{{ $i }}" {{ old('birth_date.2', $defaultYear) == $i ? 'selected' : '' }}>
                            {{ $i }}
                        </option>
                    @endfor
                </select>
            </div>
            @error('birth_date')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
        </div>

        <!-- National ID -->
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-id-card"></i>
                کد ملی
            </label>
            <input type="text" 
                   name="national_id" 
                   {{ $user->status == 1 ? 'disabled' : '' }} 
                   placeholder="کد ملی خود را وارد کنید" 
                   class="form-input-enhanced" 
                   @error('national_id') style="border-color: #ef4444;" @enderror 
                   value="{{ old('national_id', auth()->user()->national_id) }}">
            @error('national_id')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
        </div>

        <!-- Gender -->
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-venus-mars"></i>
                جنسیت
            </label>
            <select name="gender" 
                    id="gender" 
                    @if($user->status == 1) {{ $user->edited == 1 ? 'disabled' : '' }} @endif
                    class="form-input-enhanced" 
                    @error('gender') style="border-color: #ef4444;" @enderror>
                <option value="">انتخاب کنید</option>
                <option value="male" {{ old('gender', auth()->user()->gender) == 'male' ? 'selected' : '' }}>مرد</option>
                <option value="female" {{ old('gender', auth()->user()->gender) == 'female' ? 'selected' : '' }}>زن</option>
            </select>
            @error('gender')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
        </div>

        <!-- Documents -->
        <div class="form-group-enhanced" id="documents-form-group">
            <label class="form-label-enhanced">
                <i class="fas fa-file-upload"></i>
                افزودن مدارک جدید
            </label>
            @if (auth()->user()->documents != null)
                <button type="button" 
                        onclick="toggleDocumentsBox()" 
                        class="text-ocean-blue hover:text-dark-blue cursor-pointer mb-2 font-semibold">
                    <i class="fas fa-eye ml-2"></i>نمایش مدارک بارگذاری شده
                </button>
            @endif
            <div id="documents-box" style="display: none; margin-top: 15px;">
                @if(auth()->user()->documents && auth()->user()->documents != null)
                    <div class="documents-list-enhanced">
                        @php
                            $documentsData = auth()->user()->documents;
                            // بررسی اینکه آیا JSON است یا comma-separated
                            $decoded = json_decode($documentsData, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $documents = $decoded;
                            } else {
                                // تبدیل comma-separated به فرمت جدید
                                $files = explode(',', $documentsData);
                                $documents = [];
                                foreach ($files as $file) {
                                    $file = trim($file);
                                    if (!empty($file)) {
                                        $extension = pathinfo($file, PATHINFO_EXTENSION);
                                        $documents[] = [
                                            'filename' => $file,
                                            'name' => 'مدرک',
                                            'type' => strtolower($extension)
                                        ];
                                    }
                                }
                            }
                        @endphp
                        @foreach($documents as $index => $doc)
                            @php
                                $filename = is_array($doc) ? $doc['filename'] : $doc;
                                $docName = is_array($doc) ? ($doc['name'] ?? 'مدرک ' . ($index + 1)) : ('مدرک ' . ($index + 1));
                                $extension = is_array($doc) ? ($doc['type'] ?? pathinfo($filename, PATHINFO_EXTENSION)) : pathinfo($filename, PATHINFO_EXTENSION);
                                $isImage = in_array(strtolower($extension), ['png', 'jpg', 'jpeg']);
                                $isPdf = strtolower($extension) === 'pdf';
                            @endphp
                            <div class="document-item-enhanced">
                                @if($isImage)
                                    <i class="fas fa-file-image text-3xl text-blue-500"></i>
                                @elseif($isPdf)
                                    <i class="fas fa-file-pdf text-3xl text-red-500"></i>
                                @else
                                    <i class="fas fa-file text-3xl text-gray-500"></i>
                                @endif
                                <a href="{{ asset('images/users/documents/' . $filename) }}" target="_blank" class="text-sm text-ocean-blue hover:underline">
                                    {{ $docName }}
                                </a>
                                <button type="button"
                                        class="remove-btn text-sm"
                                        onclick="deleteDocument({{ $index }})">
                                    <i class="fas fa-trash ml-1"></i>حذف
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center">مدارکی بارگذاری نشده است.</p>
                @endif
            </div>
            <input type="file" 
                   name="documents[]" 
                   id="documents-input"
                   class="form-input-enhanced" 
                   multiple="multiple"
                   accept=".pdf,.jpg,.jpeg,.png"
                   style="cursor: pointer !important;"
                   @error('documents') style="border-color: #ef4444; cursor: pointer !important;" @enderror>
            <div id="selected-files" class="mt-4" style="display: none;"></div>
            <div id="document-names-container" class="mt-3 space-y-3" style="display: none;"></div>
            @error('documents')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
            @error('document_names.*')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
            <small class="text-gray-500 text-sm">
                <i class="fas fa-info-circle ml-1"></i>
                می‌توانید با نگه داشتن Ctrl (یا Cmd در Mac) و کلیک روی فایل‌ها، چند فایل را همزمان انتخاب کنید. حداکثر ۵ فایل - فرمت‌های مجاز: PDF, JPG, PNG
            </small>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <!-- Avatar -->
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-image"></i>
                آواتار (اختیاری)
            </label>
            <div class="avatar-upload-container" onclick="document.getElementById('avatar-input').click()">
                <input type="file" 
                       name="avatar" 
                       id="avatar-input" 
                       accept="image/*" 
                       style="display: none; cursor: pointer !important;">
                <input type="hidden" name="cropped_avatar" id="cropped-avatar">
                <input type="hidden" name="remove_avatar" id="remove-avatar" value="0">
                <div id="avatar-preview-container" style="display: none;">
                    <img id="avatar-preview" class="avatar-preview" src="" alt="پیش‌نمایش آواتار">
                </div>
                @if(auth()->user()->avatar)
                    <div id="avatar-current-container" style="text-align: center; position: relative;">
                        <img src="{{ asset('images/users/avatars/' . auth()->user()->avatar) }}" 
                             alt="آواتار فعلی" 
                             style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid var(--color-earth-green);">
                        <button type="button" 
                                onclick="removeAvatar()" 
                                class="mt-2 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors text-sm">
                            <i class="fas fa-trash ml-1"></i>حذف آواتار
                        </button>
                    </div>
                @else
                    <div id="avatar-placeholder" style="text-align: center;">
                        <i class="fas fa-camera text-4xl text-earth-green mb-2"></i>
                        <p class="text-gray-600 font-semibold">برای تغییر آواتار کلیک کنید</p>
                    </div>
                @endif
            </div>
            @error('avatar')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
        </div>

        <!-- Biography -->
        <div class="form-group-enhanced">
            <label class="form-label-enhanced">
                <i class="fas fa-book"></i>
                بیوگرافی (اختیاری)
            </label>
            <textarea name="biografie" 
                      class="form-input-enhanced" 
                      rows="6"
                      placeholder="در مورد خودتان بنویسید..." 
                      @error('biografie') style="border-color: #ef4444;" @enderror>{{ old('biografie', auth()->user()->biografie) }}</textarea>
            @error('biografie')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
            <small class="text-gray-500 text-sm">حداکثر ۱۰۰۰ کاراکتر</small>
        </div>
    </div>

    <div class="flex justify-center mt-8">
        <button type="submit" class="submit-btn-enhanced">
            <i class="fas fa-save ml-2"></i>
            ذخیره تغییرات
        </button>
    </div>
</form>

<form id="delete-document-form" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<script>
    function toggleDocumentsBox() {
        const box = document.getElementById('documents-box');
        const formGroup = document.getElementById('documents-form-group');
        if (box.style.display === 'none' || box.style.display === '') {
            box.style.display = 'block';
            formGroup.style.gridColumn = '1 / -1';
        } else {
            box.style.display = 'none';
            formGroup.style.gridColumn = '';
        }
    }

    function deleteDocument(index) {
        if (confirm('آیا از حذف این مدرک مطمئن هستید؟')) {
            const form = document.getElementById('delete-document-form');
            form.action = `{{ route('profile.document.delete', ':index') }}`.replace(':index', index);
            form.submit();
        }
    }

    function removeAvatar() {
        if (confirm('آیا از حذف آواتار مطمئن هستید؟')) {
            document.getElementById('remove-avatar').value = '1';
            document.getElementById('avatar-current-container').style.display = 'none';
            document.getElementById('avatar-placeholder').style.display = 'block';
        }
    }

    function updatePlaceholder() {
        const select = document.getElementById('country_code');
        const selected = select.options[select.selectedIndex];
        const placeholder = selected.getAttribute('data-placeholder');
        document.getElementById('phone').placeholder = 'برای مثال: ' + placeholder;
    }

    // Avatar preview
    document.getElementById('avatar-input')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatar-preview').src = e.target.result;
                document.getElementById('avatar-preview-container').style.display = 'block';
                document.getElementById('avatar-placeholder').style.display = 'none';
                if (document.getElementById('avatar-current-container')) {
                    document.getElementById('avatar-current-container').style.display = 'none';
                }
                document.getElementById('remove-avatar').value = '0'; // Reset remove flag when new file selected
            };
            reader.readAsDataURL(file);
        }
    });

    // Show selected files for documents and add name inputs
    document.getElementById('documents-input')?.addEventListener('change', function(e) {
        const files = e.target.files;
        const selectedFilesDiv = document.getElementById('selected-files');
        const documentNamesContainer = document.getElementById('document-names-container');
        
        if (files.length > 0) {
            let fileList = '<strong class="text-gray-700">فایل‌های انتخاب شده:</strong><ul class="list-disc list-inside mt-2 space-y-1">';
            let nameInputs = '';
            
            for (let i = 0; i < files.length; i++) {
                const fileName = files[i].name;
                const fileExtension = fileName.split('.').pop().toLowerCase();
                const isImage = ['png', 'jpg', 'jpeg'].includes(fileExtension);
                const isPdf = fileExtension === 'pdf';
                
                fileList += `<li class="text-sm text-gray-600">${fileName}</li>`;
                
                // ایجاد input برای نام مدرک
                nameInputs += `
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-8">
                            ${isImage ? '<i class="fas fa-file-image text-blue-500 text-lg"></i>' : 
                              isPdf ? '<i class="fas fa-file-pdf text-red-500 text-lg"></i>' : 
                              '<i class="fas fa-file text-gray-500 text-lg"></i>'}
                        </div>
                        <input type="text" 
                               name="document_names[]" 
                               class="form-input-enhanced flex-1" 
                               placeholder="نام مدرک (مثلاً: کارت ملی، گواهینامه، ...)"
                               value=""
                               maxlength="100">
                        <span class="text-xs text-gray-400 flex-shrink-0" style="min-width: 120px;">${fileName}</span>
                    </div>
                `;
            }
            fileList += '</ul>';
            
            selectedFilesDiv.innerHTML = fileList;
            selectedFilesDiv.style.display = 'block';
            
            documentNamesContainer.innerHTML = '<label class="block text-sm font-semibold text-gray-700 mb-2"><i class="fas fa-tag ml-1"></i>نام هر مدرک (اختیاری):</label>' + nameInputs;
            documentNamesContainer.style.display = 'block';
        } else {
            selectedFilesDiv.style.display = 'none';
            documentNamesContainer.style.display = 'none';
        }
    });
</script>
