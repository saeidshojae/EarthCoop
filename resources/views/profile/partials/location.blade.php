<div class="warning-box">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>توجه:</strong> تغییر موقعیت مکانی شما به منزله خروج از گروه‌های فعلی شما می‌باشد.
    </div>
</div>

@push('scripts')
<script>
(function () {
    const levelLabels = ['قاره', 'کشور', 'استان', 'شهرستان', 'بخش', 'شهر / دهستان', 'منطقه/ روستا', 'محله', 'خیابان', 'کوچه'];
    const levelKeys = ['continent', 'country', 'province', 'county', 'section', 'city', 'region', 'neighborhood', 'street', 'alley'];
    const nameKeys = ['continent_id', 'country_id', 'province_id', 'county_id', 'section_id', 'city_id', 'region_id', 'neighborhood_id', 'street_id', 'alley_id'];
    const allowAddModal = ['region', 'neighborhood', 'street', 'alley'];
    const optionalLevels = [9, 10];
    const defaultContinentId = '4';
    const defaultCountryId = '74';

    let pathParts = [];
    let pathValues = [];
    let currentCreateLevel = null;
    let currentCreateType = null;
    let pendingDefaultCountry = true;
    const levelRequestTokens = {};
    let isPrefilling = false;

    function parseTypedId(val) {
        if (!val) return { type: null, id: null };
        const m = String(val).match(/^(city|rural)_(\d+)$/);
        if (m) return { type: m[1], id: m[2] };
        return { type: null, id: String(val) };
    }

    function waitForDependencies(callback) {
        if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
            setTimeout(() => waitForDependencies(callback), 50);
            return;
        }
        callback(window.jQuery);
    }

    function initialiseProfileLocation($) {
        if (window._profileLocationInitialised) {
            return;
        }
        window._profileLocationInitialised = true;

        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        });

        $('.location-select').select2({
            width: '100%',
            placeholder: 'انتخاب کنید',
            allowClear: true,
            dir: 'rtl',
            language: 'fa'
        });

        const address = window.userAddress || {};
        if (address && Object.keys(address).length) {
            setTimeout(() => loadUserAddressChain(address), 300);
        } else {
            initializeDefaultPath();
        }

        $(document).on('change', '.location-select', function () {
            if (isPrefilling) return;

            const level = parseInt($(this).data('level'), 10);
            const value = $(this).val();

            if (value) {
                const text = $(this).find('option:selected').text();
                pathParts[level - 1] = text;
                pathValues[level - 1] = value;
        pathParts = pathParts.slice(0, level);
        pathValues = pathValues.slice(0, level);
        if (level === 6) {
                    const type = value?.startsWith('rural_') ? 'rural' : 'city';
                    const id = value ? value.replace(/^(city|rural)_/, '') : '';
                    $('input[name="city_id"]').val(id ? `${type}_${type}_${id}` : '');
                }
                removeLevelContainersFrom(level + 1);

                if (level < levelKeys.length) {
                    loadNextLevel(value, level + 1);
                }
            } else {
                removeLevelContainersFrom(level + 1);
                pathParts = pathParts.slice(0, level - 1);
                pathValues = pathValues.slice(0, level - 1);
                if (level === 6) {
                    $('input[name="city_id"]').val('');
                }
            }

            updatePathDisplay();
        });

        $(document).on('click', '#location_path_display span', function () {
            const level = parseInt($(this).data('level'), 10);
            const value = $(this).data('value');

            if ($(this).hasClass('last')) return;

            if (level === 0) {
                resetToContinent();
                return;
            }

            removeLevelContainersFrom(level + 1);
            pathParts = pathParts.slice(0, level);
            pathValues = pathValues.slice(0, level);
            if (level === 6) {
                const type = value?.startsWith('rural_') ? 'rural' : 'city';
                const id = value ? value.replace(/^(city|rural)_/, '') : '';
                $('input[name="city_id"]').val(id ? `${type}_${id}` : '');
            }
            updatePathDisplay();

            if (level < levelKeys.length) {
                loadNextLevel(value, level + 1);
            }
        });

        $(document).on('click', '.create-location-btn', function () {
            const level = parseInt($(this).data('level'), 10);
            const type = $(this).data('type');
            const explicitParent = $(this).data('parentId');
            const fallbackParent = pathValues[level - 2];
            const parentId = explicitParent || fallbackParent;

            if (!parentId) {
                showWarningAlert('لطفاً ابتدا سطح قبلی را انتخاب کنید، سپس گزینه جدید ایجاد نمایید.');
                return;
            }

            openCreateLocationModal(level, type, parentId);
        });

        $('#createLocationForm').on('submit', function (e) {
            e.preventDefault();
            const name = ($('#location_name').val() || '').trim();
            const parentId = $('#location_parent_id').val();
            const type = $('#location_type').val();
            const level = parseInt($('#location_level').val(), 10);

            if (!name) {
                $('#create_location_error').removeClass('hidden').text('نام را وارد کنید.');
                return;
            }

            if (!parentId) {
                $('#create_location_error').removeClass('hidden').text('شناسه والد نامعتبر است.');
                return;
            }

            $('#create_location_error').addClass('hidden');

            $.post(`/profile/add-${type}`, {
                name: name,
                parent_id: parentId
            }, function (response) {
                const newId = response.id ?? response.data?.id;
                const newName = response.name ?? response.data?.name ?? name;

                if (!newId) {
                    $('#create_location_error').removeClass('hidden').text('پاسخ سرور معتبر نیست.');
                    return;
                }

                const optionValue = String(newId);
                const $select = $(`select[data-level="${level}"]`);

                if ($select.length) {
                    const opt = new Option(newName, optionValue, true, true);
                    $select.append(opt).trigger('change');
                }

                pathParts[level - 1] = newName;
                pathValues[level - 1] = optionValue;
                updatePathDisplay();

                closeCreateLocationModal();
                showSuccessAlert(`${newName} با موفقیت ایجاد شد.`);
            }).fail(function (xhr) {
                const message = xhr.responseJSON?.message || 'خطا در ثبت اطلاعات.';
                $('#create_location_error').removeClass('hidden').text(message);
            });
        });
    }

    function initializeDefaultPath() {
        const $continent = $('#continent-select');
        if (!$continent.length) return;

        pathParts = [];
        pathValues = [];
        pendingDefaultCountry = true;

        const continentText = $continent.find(`option[value="${defaultContinentId}"]`).text().trim();
        const resolvedContinent = continentText || 'آسیا';
        pathParts[0] = resolvedContinent;
        pathValues[0] = defaultContinentId;
        updatePathDisplay();

        $continent.closest('.form-group-enhanced').show();
        $continent.val(defaultContinentId).trigger('change');
    }

    function removeLevelContainersFrom(startLevel) {
        for (let i = startLevel; i <= levelKeys.length; i++) {
            const $container = $(`select[data-level="${i}"]`).closest('.form-group-enhanced');
            if ($container.length) {
                $container.remove();
            }
        }
    }

    function loadUserAddressChain(address) {
        if (!address || !address.continent_id) {
            initializeDefaultPath();
            return;
        }

        isPrefilling = true;
        pathParts = [];
        pathValues = [];

        $('#continent-select').val(address.continent_id).trigger('change.select2');
        const continentText = $('#continent-select option:selected').text();
        if (continentText && continentText !== 'انتخاب کنید') {
            pathParts[0] = continentText;
            pathValues[0] = address.continent_id;
            updatePathDisplay();
        }

        loadNextLevelForUser(address.continent_id, 2, address);
    }

    function loadNextLevel(parentId, level, preselectValue = null, onComplete = null) {
        if (level > levelKeys.length) return;

        const key = levelKeys[level - 1];
        const label = levelLabels[level - 1];
        const name = nameKeys[level - 1];
        const isOptional = optionalLevels.includes(level);
        const hasAdd = allowAddModal.includes(key);
        const showLabel = isOptional ? `${label} (اختیاری)` : label;
        const requestToken = Date.now();

        levelRequestTokens[level] = requestToken;

        $.get(`/api/locations?level=${key}&parent_id=${parentId}`, function (data) {
            if (levelRequestTokens[level] !== requestToken) {
                return;
            }

            removeLevelContainersFrom(level);

            let optionsHtml = `<option value="">انتخاب ${showLabel}</option>`;
            if (Array.isArray(data)) {
                data.forEach(item => {
                    let value = '';
                    if (item && item.id !== undefined) {
                        if (typeof item.id === 'string' && item.id.includes('_')) {
                            value = item.id;
                        } else if (item.type) {
                            value = `${item.type}_${item.id}`;
                        } else {
                            value = String(item.id);
                        }
                    }
                    optionsHtml += `<option value="${value}">${item.name}</option>`;
                });
            }

            const icon = getIconForLevel(level);
            const parentAttr = pathValues[level - 2] ?? (parentId != null ? parentId : '');

            const $container = $(`
                <div class="form-group-enhanced level-container">
                    <label class="form-label-enhanced">
                        <i class="${icon}"></i>
                        ${showLabel}:
                    </label>
                    <div class="level-input-group">
                        <select name="${name === 'city_id' ? 'city_id_visible' : name}" class="form-input-enhanced location-select" data-level="${level}">
                            ${optionsHtml}
                        </select>
                        ${name === 'city_id' ? `<input type="hidden" name="city_id" class="hidden-city-value" value="${preselectValue ?? ''}">` : ''}
                        ${hasAdd ? `
                            <button type="button"
                                    class="create-location-btn"
                                    data-level="${level}"
                                    data-type="${key}"
                                    data-parent-id="${parentAttr}">
                                <i class="fas fa-plus-circle"></i>
                                ایجاد ${label}
                            </button>
                        ` : ''}
                    </div>
                </div>
            `);

            $('#location-selects').append($container);
            const $select = $container.find('select');
            $select.select2({
                width: '100%',
                placeholder: 'انتخاب کنید',
                allowClear: true,
                dir: 'rtl',
                language: 'fa'
            });
            if (name === 'city_id') {
                $select.on('select2:select', function (event) {
                    const val = event.params.data.id || '';
                    const type = val.startsWith('rural_') ? 'rural' : 'city';
                    const cleanId = val.replace(/^(city|rural)_/, '');
                    $('input[name="city_id"]').val(cleanId ? `${type}_${type}_${cleanId}` : '');
                });
            }

            if (preselectValue != null) {
                $select.val(preselectValue).trigger('change');
                const text = $select.find('option:selected').text();
            if (text) {
                pathParts[level - 1] = text;
                pathValues[level - 1] = preselectValue;
                updatePathDisplay();
                if (name === 'city_id') {
                    const type = (preselectValue || '').startsWith('rural_') ? 'rural' : 'city';
                    const cleanId = (preselectValue || '').replace(/^(city|rural)_/, '');
                    $('input[name="city_id"]').val(cleanId ? `${type}_${type}_${cleanId}` : '');
                }
            }
            }

            if (pendingDefaultCountry && level === 2 && parentId === defaultContinentId) {
                const $defaultOption = $select.find(`option[value="${defaultCountryId}"]`);
                if ($defaultOption.length) {
                    pendingDefaultCountry = false;
                    $select.val(defaultCountryId).trigger('change');
                }
            }

            if (typeof onComplete === 'function') {
                onComplete($select);
            }
        }).fail(function (error) {
            console.error(`Failed to load ${key}:`, error);
        });
    }

    function loadNextLevelForUser(parentId, level, address) {
        if (level > levelKeys.length) {
            isPrefilling = false;
            rebuildPathFromSelects();
            return;
        }

        const key = levelKeys[level - 1];
        const name = nameKeys[level - 1];
        const addressValue = address[name];
        if (!addressValue) {
            isPrefilling = false;
            rebuildPathFromSelects();
            return;
        }

        let apiParentId = parentId;
        if (key === 'country') {
            apiParentId = address.continent_id;
        } else if (key === 'province') {
            apiParentId = address.country_id;
        } else if (key === 'county') {
            apiParentId = address.province_id;
        } else if (key === 'section') {
            apiParentId = address.county_id;
        } else if (key === 'city') {
            apiParentId = address.section_id;
        } else if (key === 'region') {
            apiParentId = address.city_id || address.rural_id;
            if (apiParentId && !apiParentId.toString().startsWith('city_') && !apiParentId.toString().startsWith('rural_')) {
                apiParentId = 'city_' + apiParentId;
            }
        } else if (key === 'neighborhood') {
            apiParentId = address.region_id || address.village_id;
        } else if (key === 'street') {
            apiParentId = address.neighborhood_id || address.village_id;
        } else if (key === 'alley') {
            apiParentId = address.street_id || address.village_id;
        }

        loadNextLevel(apiParentId, level, addressValue, function ($select) {
            const text = $select.find('option:selected').text();
            if (text) {
                pathParts[level - 1] = text;
                pathValues[level - 1] = addressValue;
                updatePathDisplay();
                if (key === 'city') {
                    const type = (addressValue || '').startsWith('rural_') ? 'rural' : 'city';
                    const cleanId = (addressValue || '').replace(/^(city|rural)_/, '');
                    $('input[name="city_id"]').val(cleanId ? `${type}_${type}_${cleanId}` : '');
                }
            }

        let nextParent = addressValue;
        if (key === 'country') {
            nextParent = address.country_id;
        } else if (key === 'province') {
            nextParent = address.province_id;
        } else if (key === 'county') {
            nextParent = address.county_id;
        } else if (key === 'section') {
            nextParent = address.section_id;
        } else if (key === 'city') {
            nextParent = address.city_id || address.rural_id;
        } else if (key === 'region') {
            nextParent = address.region_id || address.village_id;
        } else if (key === 'neighborhood') {
            nextParent = address.neighborhood_id || address.village_id;
        } else if (key === 'street') {
            nextParent = address.street_id || address.village_id;
        } else if (key === 'alley') {
            nextParent = address.alley_id;
        } else {
            nextParent = null;
        }

            loadNextLevelForUser(nextParent, level + 1, address);
        });
    }

    function rebuildPathFromSelects() {
        pathParts = new Array(levelKeys.length).fill(null);
        pathValues = new Array(levelKeys.length).fill(null);

        const continentValue = $('#continent-select').val();
        const continentText = $('#continent-select option:selected').text();
        if (continentValue && continentText && continentText !== 'انتخاب کنید') {
            pathValues[0] = continentValue;
            pathParts[0] = continentText;
        }

        for (let level = 2; level <= levelKeys.length; level++) {
            const nameKey = nameKeys[level - 1];
            const selectName = nameKey === 'city_id' ? 'city_id_visible' : nameKey;
            const $select = $(`select[name="${selectName}"]`);
            if (!$select.length) {
                continue;
            }

            const value = $select.val();
            const text = $select.find('option:selected').text();

            if (value && text && text !== 'انتخاب کنید') {
                pathValues[level - 1] = value;
                pathParts[level - 1] = text;

                if (nameKey === 'city_id') {
                    const type = value.startsWith('rural_') ? 'rural' : 'city';
                    const cleanId = value.replace(/^(city|rural)_/, '');
                    $('input[name="city_id"]').val(cleanId ? `${type}_${type}_${cleanId}` : '');
                }
            }
        }

        updatePathDisplay();
    }

    function getIconForLevel(level) {
        const icons = [
            'fas fa-globe',
            'fas fa-flag',
            'fas fa-map',
            'fas fa-city',
            'fas fa-building',
            'fas fa-home',
            'fas fa-map-marked-alt',
            'fas fa-map-pin',
            'fas fa-road',
            'fas fa-route'
        ];
        return icons[level - 1] || 'fas fa-map-marker-alt';
    }

    function updatePathDisplay() {
        const display = [
            `<span class="location-breadcrumb" data-level="0" data-value="world" style="cursor: pointer;"><i class="fas fa-globe-asia ml-1"></i>زمین</span>`,
            ...pathParts
                .filter(part => part !== null && part !== undefined && part !== '')
                .map((part, index, arr) => {
                    const level = index + 1;
                    const isLast = index === arr.length - 1;
                    const extraClass = isLast ? 'last' : '';
                    return `<span class="location-breadcrumb ${extraClass}" data-level="${level}" data-value="${pathValues[index]}" style="cursor: ${isLast ? 'default' : 'pointer'};">${part}</span>`;
                })
        ].join(' <i class="fas fa-angle-left mx-1 text-sm"></i> ');

        $('#location_path_display').html(display || '<i class="fas fa-map-marker-alt ml-2"></i><span>مسیر انتخاب نشده</span>');
    }

    function openCreateLocationModal(level, type, parentId) {
        currentCreateLevel = level;
        currentCreateType = type;

        $('#location_level').val(level);
        $('#location_parent_id').val(parentId || '');
        $('#location_type').val(type);
        $('#location_name').val('');
        $('#modal_location_type').text(levelLabels[level - 1]);
        $('#modal_location_type_label').text(levelLabels[level - 1]);
        $('#create_location_error').addClass('hidden').text('خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.');
        $('#createLocationModal').removeClass('hidden');
    }

    function closeCreateLocationModal() {
        $('#createLocationModal').addClass('hidden');
        currentCreateLevel = null;
        currentCreateType = null;
    }

    function resetToContinent() {
        pathParts = [];
        pathValues = [];
        $('#location-selects .form-group-enhanced').not(':first').remove();
        $('#continent-select').val('').closest('.form-group-enhanced').show();
        $('input[name="city_id"]').val('');
        pathParts = [];
        pathValues = [];
        updatePathDisplay();
        initializeDefaultPath();
    }

    function showWarningAlert(message) {
        alert('⚠️ ' + message);
    }

    function showSuccessAlert(message) {
        alert('✅ ' + message);
    }

    window.closeCreateLocationModal = closeCreateLocationModal;

    document.addEventListener('DOMContentLoaded', function () {
        waitForDependencies(initialiseProfileLocation);
    });
})();
</script>
@endpush
<style>
    .location-container .level-container {
        margin-bottom: 1.5rem;
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .location-container .level-input-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        align-items: stretch;
    }

    @media (min-width: 640px) {
        .location-container .level-input-group {
            flex-direction: row;
            align-items: center;
        }
    }

    .location-container .create-location-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        white-space: nowrap;
        border-radius: 9999px;
        font-weight: 700;
        font-size: 0.95rem;
        padding: 0.6rem 1.45rem;
        background: linear-gradient(135deg, var(--color-earth-green, #10b981), var(--color-dark-green, #047857));
        color: #fff;
        box-shadow: 0 10px 22px rgba(16, 185, 129, 0.28);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .location-container .create-location-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(16, 185, 129, 0.38);
    }

    .location-container .create-location-btn i {
        font-size: 0.95rem;
    }

.modal {
    display: none;
}

.modal.show {
    display: block;
}
</style>

<form action="{{ route('profile.update.address') }}" method="POST" class="location-form">
    @csrf
    @method('PUT')

    <div class="location-container" style="direction: rtl; max-width: 800px; margin: 0 auto;">
        <!-- Location Path Display -->
        <div class="location-path-display" id="location_path_display">
            <i class="fas fa-map-marker-alt ml-2"></i>
            <span>مسیر انتخاب نشده</span>
        </div>

        <!-- Location Selects -->
        <div id="location-selects">
            <div class="form-group-enhanced level-container">
                <label class="form-label-enhanced">
                    <i class="fas fa-globe"></i>
                    انتخاب قاره
                </label>
                <div class="level-input-group">
                    <select class="form-input-enhanced location-select" 
                            name="continent_id" 
                            data-level="1" 
                            id="continent-select">
                        <option value="">انتخاب کنید</option>
                        @foreach($continents as $continent)
                            <option value="{{ $continent->id }}" {{ $continent->id == $user->address->continent_id ? 'selected' : '' }}>
                                {{ $continent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex justify-center mt-8">
            <button type="submit" class="submit-btn-enhanced">
                <i class="fas fa-save ml-2"></i>
                ذخیره تغییرات موقعیت مکانی
            </button>
        </div>
    </div>
</form>

<!-- Inline Modal for Creating New Locations -->
<div id="createLocationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md mx-auto p-6 sm:p-8 w-full">
        <h3 class="text-xl sm:text-2xl font-bold text-gentle-black mb-6" style="color: var(--color-gentle-black, #1e293b);">
            ایجاد <span id="modal_location_type"></span> جدید
        </h3>
        
        <form id="createLocationForm">
            <input type="hidden" id="location_level">
            <input type="hidden" id="location_parent_id">
            <input type="hidden" id="location_type">
            
            <div class="mb-6">
                <label for="location_name" class="block text-base sm:text-lg font-bold text-gray-800 mb-3">
                    نام <span id="modal_location_type_label"></span>:
                </label>
                <input type="text" id="location_name" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-ocean-blue"
                       placeholder="نام را وارد کنید">
            </div>
            
            <div id="create_location_error" class="hidden bg-red-100 text-red-700 px-4 py-2 rounded-lg mb-4">
                خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit"
                        class="flex-1 px-6 py-3 rounded-full text-white font-bold shadow-lg hover:shadow-xl transition duration-300"
                        style="background-color: var(--color-earth-green, #10b981);">
                    ذخیره
                </button>
                <button type="button" onclick="closeCreateLocationModal()"
                        class="flex-1 px-6 py-3 rounded-full text-gray-700 font-bold border-2 border-gray-300 hover:bg-gray-100 transition duration-300">
                    لغو
                </button>
            </div>
        </form>
    </div>
</div>

