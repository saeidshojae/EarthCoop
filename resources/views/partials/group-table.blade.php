<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<div class="location-filter-wrapper">

  @if ($type == 'specialty')
    <div class="location-filters" data-type="{{ $type }}">
      <div class="roww">
        <div class="location-tab active" data-location="all">همه گروه های شما</div>
        <div class="location-tab" data-location="global">جهانی</div>
      </div>
      <div class="roww">
        <div class="location-tab" data-location="continent">قاره</div>
        <div class="location-tab" data-location="country">کشور</div>
      </div>
      <div class="roww">
        <div class="location-tab" data-location="province">استان</div>
        <div class="location-tab" data-location="county">شهرستان</div>
      </div>
      <div class="roww">
        <div class="location-tab" data-location="section">بخش</div>
        <div class="location-tab" data-location="city">شهر/ دهستان</div>
      </div>
      <div class="roww">
        <div class="location-tab" data-location="region">منطقه/ روستا</div>
        <div class="location-tab" data-location="neighborhood">محله</div>
      </div>
      <div class="roww">
        <div class="location-tab" data-location="street">خیابان</div>
        <div class="location-tab" data-location="alley">کوچه</div>
      </div>
    </div>
  @endif

</div>

<!-- لیست گروه‌ها به سبک تلگرام -->
<div class="groups-list">
  @foreach ($groups as $group)
    @php
      $currentUser = auth()->id();
      $pivot = \App\Models\GroupUser::where('group_id', $group->id)->where('user_id', $currentUser)->first();

      $locationApproved = true;
      if ($group->address_id !== null) {
          $level = $group->location_level;
          if (!in_array($level, ['continent', 'country', 'province', 'county', 'section', 'city'])) {
              $modelMap = [
                  'region' => \App\Models\Region::class,
                  'village' => \App\Models\Village::class,
                  'rural' => \App\Models\Rural::class,
                  'neighborhood' => \App\Models\Neighborhood::class,
                  'street' => \App\Models\Street::class,
                  'alley' => \App\Models\Alley::class,
              ];
              $model = $modelMap[$level] ?? null;
              if ($model) {
                  $instance = $model::find($group->address_id);
                  if ($instance && $instance->status == 0) {
                      $locationApproved = false;
                  }
              }
          }
      }

      $specialtyApproved = true;
      if (($group->specialty && $group->specialty->status == 0) ||
          ($group->experience && $group->experience->status == 0)) {
          $specialtyApproved = false;
      }
    @endphp

    @if($pivot)
      @php
        // نقش کاربر را بر اساس location_level تعیین می‌کنیم:
        // - سطح محله و پایین‌تر (neighborhood, street, alley) → عضو فعال (role 1)
        // - سطح منطقه و بالاتر (region, village, rural, city و ...) → ناظر (role 0)
        // این منطق همیشه اعمال می‌شود، صرف نظر از مقدار pivot->role در دیتابیس
        $locationLevel = strtolower(trim((string)($group->location_level ?? '')));
        
        // اگر location_level مشخص نیست، از pivot استفاده می‌کنیم (fallback)
        if (empty($locationLevel)) {
            $pivotRole = isset($pivot->role) ? (int) $pivot->role : 0;
        } else {
            // بر اساس location_level تعیین می‌شود
            if (in_array($locationLevel, ['neighborhood', 'street', 'alley'], true)) {
                $pivotRole = 1; // عضو فعال
            } else {
                // سطوح منطقه و بالاتر
                $pivotRole = 0; // ناظر
            }
        }
        
        $memberRole = match($pivotRole) {
          0 => 'ناظر',
          1 => 'فعال',
          2 => 'بازرس',
          3 => 'مدیر',
          4 => 'مهمان',
          5 => 'فعال ۲',
        };

        $inspectors = $group->users->filter(fn($u) => $u->pivot->role == 2);
        $managers = $group->users->filter(fn($u) => $u->pivot->role == 3);
      @endphp

<div class="group-item" data-type="{{ $type }}" data-level="{{ strtolower($group->location_level) }}">
        <div class="group-avatar">
          @if($group->avatar)
            <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}">
          @else
          @php
            $fColorAvatar = rand(1,255);
            $sColorAvatar = rand(1,255);
            $tColorAvatar = rand(1,255);
          @endphp
            <div class="default-avatar" style='background: #3f7d58d1 !important;
    background: linear-gradient(90deg, rgba({{ $fColorAvatar }}, {{ $sColorAvatar }}, {{ $tColorAvatar }}, .8) 0%, rgba({{ $fColorAvatar }}, {{ $sColorAvatar }}, {{ $tColorAvatar }}, .5) 100%) !important;'>{{ substr($group->name, 0, 2) }}</div>
          @endif
        </div>
        
        <div class="group-info">
          <div class="group-main-info">
            <div class="group-name">
              @if($locationApproved && $specialtyApproved && $pivot->status == 1)
                <a href="{{ route('groups.chat', $group) }}">{{ $group->name }}</a>
              @else
                <span class="text-muted">{{ $group->name }} (در انتظار تأیید)</span>
              @endif
            </div>
            <div class="group-members-count">{{ $group->userCount() }} عضو</div>
          </div>
          
          <div class="group-secondary-info">
            <div class="member-role">
              @if($pivot->status == 1)
                <span>{{ $memberRole }}</span>
              @else
                <span>
                  خارج شده <a href="{{ route('groups.relogout', $group) }}" class="text-primary">بازگردانی</a>
                </span>
              @endif
            </div>
            
          </div>
        </div>
      </div>
    @endif
  @endforeach
</div>

<style>
.groups-list {
  max-width: 800px;
  margin: 0 auto;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.group-item {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #f0f0f0;
  transition: background-color 0.2s;
}

.group-item:last-child {
  border-bottom: none;
}

.group-item:hover {
  background-color: #f5f5f5;
}

.group-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  margin-left: 15px;
  overflow: hidden;
  background: #e0e0e0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.group-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.default-avatar {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #2196F3, #1976D2);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: bold;
}

.group-info {
  flex: 1;
}

.group-main-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.group-name {
  font-weight: 500;
  font-size: 16px;
  color: #212121;
}

.group-name a {
  color: #212121;
  text-decoration: none;
}

.group-members-count {
  color: #757575;
  font-size: 13px;
}

.group-secondary-info {
  display: flex;
  justify-content: space-between;
  color: #757575;
  font-size: 13px;
}

.member-role {
  color: #2196F3;
}

.text-muted {
  color: #757575;
}

.manager-info {
  color: #757575;
}

.text-primary {
  color: #2196F3;
  text-decoration: none;
}

.text-primary:hover {
  text-decoration: underline;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const locationTabs = document.querySelectorAll('.location-tab');
  const groupItems = document.querySelectorAll('.group-item');

  function activateTab(tab) {
    // حذف کلاس active از همه تب‌ها
    locationTabs.forEach(t => t.classList.remove('active'));
    // افزودن کلاس active به تب کلیک‌شده
    tab.classList.add('active');

    const level = tab.getAttribute('data-location');

    // فیلتر کردن گروه‌ها
    groupItems.forEach(item => {
      const itemLevel = item.getAttribute('data-level');
      if (level === 'all' || itemLevel === level) {
        item.style.display = '';
      } else {
        item.style.display = 'none';
      }
    });
  }

  // اضافه کردن لیسنر کلیک به تب‌ها
  locationTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      activateTab(tab);
    });
  });

  // فعال‌سازی تب پیش‌فرض (همه گروه‌ها) در بارگذاری اولیه
  const defaultTab = document.querySelector('.location-tab[data-location="all"]');
  if (defaultTab) {
    activateTab(defaultTab);
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const locationTabs = document.querySelectorAll('.location-tab');

  function activateTab(tab) {
    const filtersWrapper = tab.closest('.location-filters');
    const type = filtersWrapper.getAttribute('data-type');
    const groupItems = document.querySelectorAll(`.group-item[data-type="${type}"]`);

    // حذف active از تب‌های همین بخش
    filtersWrapper.querySelectorAll('.location-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');

    const level = tab.getAttribute('data-location');

    // فیلتر گروه‌های همین بخش
    groupItems.forEach(item => {
      const itemLevel = item.getAttribute('data-level');
      if (level === 'all' || itemLevel === level) {
        item.style.display = '';
      } else {
        item.style.display = 'none';
      }
    });
  }

  locationTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      activateTab(tab);
    });
  });

  // پیش‌فرض برای هر بخش: همه گروه‌ها
  document.querySelectorAll('.location-tab[data-location="all"]').forEach(defaultTab => {
    activateTab(defaultTab);
  });
});
</script>

