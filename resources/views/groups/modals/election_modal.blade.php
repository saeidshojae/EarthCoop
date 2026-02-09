<style>
  /* مطمئن شو مودال بالاتر از هر چیز دیگه‌ست */
  .modal { z-index: 10001 !important; }
  .modal-backdrop { z-index: 10000 !important; }
  #topVotesModal,
  #candidatesModal,
  #guidelineModal {
    z-index: 10002 !important;
  }
  
  .tab-content {
    display: block !important;
  }
</style>

<div class="election-box election-card" onclick="event.stopPropagation()" dir="rtl">
  <button type="button" class="election-close" aria-label="بستن فرم انتخابات" onclick="closeElectionBox()">
    <i class="fas fa-times"></i>
  </button>
  
  <div class="election-modal-header">
    @php 
      $groupId = isset($election) && $election ? ($election->group_id ?? null) : null;
      if (!$groupId && isset($group) && $group) {
        $groupId = $group->id;
      }
      $groupUser = null;
      if ($groupId) {
        $groupUser = \App\Models\GroupUser::where('group_id', $groupId)
          ->where('user_id', auth()->id())
          ->first();
      }
    @endphp 
      
    @if(!$groupUser || $groupUser->role == 0 || $groupUser->role == 4)
      <div class="election-not-allowed">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>شما مجاز به شرکت در انتخابات نیستید</h3>
        <p>شما در این گروه دارای نقش ناظر می‌باشید</p>
      </div>
    @else
      <div class="election-title-section">
        @if(isset($election) && $election && $election->second_finish_time == null)
          <div class="election-icon-wrapper">
            <i class="fas fa-vote-yea"></i>
          </div>
          <h2 class="election-title">فرم انتخابات</h2>
          <p class="election-subtitle">انتخاب هیأت مدیره و بازرسان گروه</p>
        @else 
          <div class="election-icon-wrapper election-icon-wrapper--warning">
            <i class="fas fa-redo"></i>
          </div>
          <h2 class="election-title">فرم مجدد انتخابات</h2>
          <p class="election-subtitle">انتخاب مجدد هیأت مدیره و بازرسان</p>
        @endif
      </div>
    @endif
  </div>
  
  @if($groupUser && !in_array($groupUser->role, [0, 4]))
    <div class="election-modal-body">
    <form action="{{ route('vote', $group) }}" method="POST" id="electionForm">
        @csrf
  @if (isset($election) && $election)

        <div id="countdownText" style="direction: rtl; text-align: center; width: 100%;"></div>
        <div style="background: rgba(236, 253, 245, 0.5); border-radius: 12px; overflow: hidden; width: 100%; margin: 1rem 0; height: 8px;">
          <div id="progressBar"></div>
        </div>
        
          <script>
              const startsAt = new Date("{{ $election->starts_at ?? '' }}").getTime();
              const endsAt = new Date("{{ $election->ends_at ?? '' }}").getTime();
              const countdownText = document.getElementById('countdownText');
              const progressBar = document.getElementById('progressBar');
              const timer = setInterval(() => {
                const now = new Date().getTime();
                const total = endsAt - startsAt;
                const elapsed = now - startsAt;
                const remaining = endsAt - now;

                // درصد پیشرفت
                const progress = Math.min(100, (elapsed / total) * 100);
                document.querySelector('#progressBar').style.width = progress + '%'

                // محاسبه زمان باقی‌مانده
                if (remaining <= 0) {
                  clearInterval(timer);
                  countdownText.innerHTML = "⏰ انتخابات به پایان رسید";
                  progressBar.style.width = '100%';
                // همهٔ فیلدها را disabled کن
                const formElements = electionForm.querySelectorAll('input, select, textarea, button');
                // formElements.forEach(el => el.disabled = true);
                let electionId = "{{ $election->id ?? '' }}";
                console.log('pokl')
                finishElectionAjax(electionId);

                return;
                }
                const days = Math.floor(remaining / (1000 * 60 * 60 * 24));
                const hours = Math.floor((remaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((remaining % (1000 * 60)) / 1000);
        
                document.querySelector('#countdownText').innerHTML = `${days} روز ${hours} ساعت ${minutes} دقیقه ${seconds} ثانیه باقی مانده تا اتمام انتخابات`;
              }, 1000);
        
              function finishElectionAjax(electionId) {
                    $.ajax({
                        url: '/finish-election/' + electionId,
                        type: 'POST',
                        dataType: 'json',
                        headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                        election_id: electionId
                        },
                        success: function(data) {
                        console.log('نتیجه:', data);
                        // در صورت نیاز:
                        // window.location.href = '/somewhere';
                        },
                        error: function(xhr, status, error) {
                        console.error('خطا در ارسال درخواست پایان انتخابات:', error);
                        }
                    });
                }



          </script>
        
        
          @endif
          
        @php
        
        $groupSetting = \App\Models\GroupSetting::where('level', $group->location_level)->first();
        
        if($group->specialty_id != null){
            $groupSetting = \App\Models\GroupSetting::where('level', $group->location_level . '_job')->first();
        }elseif($group->experience_id != null){
            $groupSetting = \App\Models\GroupSetting::where('level', $group->location_level . '_experience')->first();
        }elseif($group->age_group_id != null){
            $groupSetting = \App\Models\GroupSetting::where('level', $group->location_level . '_age')->first();
        }elseif($group->gender != null){
            $groupSetting = \App\Models\GroupSetting::where('level', $group->location_level . '_gender')->first();
        }
        @endphp
<div class="election-action-buttons">
<button type="button"
          class="election-action-btn election-action-btn--resume"
          onclick="window.openCandidatesModal && window.openCandidatesModal()">
    <i class="fas fa-user-tie"></i>
  مشاهده رزومه کاندیدها
</button>

<button type="button"
          class="election-action-btn election-action-btn--guideline"
          onclick="window.openGuidelineModal && window.openGuidelineModal()">
    <i class="fas fa-book"></i>
  شیوه‌نامه انتخابات
</button>

@if($election && $election->second_finish_time)
    <button type="button"
            class="election-action-btn election-action-btn--votes"
            onclick="window.openTopVotesModal && window.openTopVotesModal()">
      <i class="fas fa-chart-bar"></i>
    نمایش بیشترین آرا
  </button>
@endif
</div>

<div class="modal fade" id="topVotesModal" tabindex="-1" aria-labelledby="topVotesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="direction: rtl; text-align: right;">
      <div class="modal-header" style="display:flex; justify-content:space-between;">
        <h5 class="modal-title" id="topVotesModalLabel">لیست آرا (مرتب‌شده)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
      </div>

      <div class="modal-body">
          <ul class="nav nav-tabs" role="tablist" style="margin-bottom:1rem;">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#managers-pane" type="button" role="tab">
      هیات‌مدیره
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#inspectors-pane" type="button" role="tab">
      بازرس
    </button>
  </li>
</ul>

<!-- این خط باید دقیقا tab-content باشد -->
<div class="tab-content">
  <div class="tab-pane fade show active" id="managers-pane" role="tabpanel">
    <div class="table-responsive">
              <table class="table table-striped table-hover align-middle">
                <thead>
                  <tr>
                    <th style="width:60px">#</th>
                    <th>نام</th>
                    <th style="white-space:nowrap">تعداد رأی</th>
                    <th style="width:160px">اقدامات</th>
                  </tr>
                </thead>
                <tbody>
  @foreach($managersSorted as $i => $u)
    @php
      $name = is_array($u) ? ($u['name'] ?? (trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: '—'))
                           : (trim(($u->name ?? '') ?: ( ($u->first_name ?? '').' '.($u->last_name ?? '') )) ?: '—');

      $votes = is_array($u) ? ($u['manager_votes'] ?? $u['votes'] ?? 0)
                            : ($u->manager_votes ?? $u->votes ?? 0);

      $id = is_array($u) ? ($u['id'] ?? null) : ($u->id ?? null);
    @endphp
    @if($votes != 0)
    <tr>
      <td>{{ $i+1 }}</td>
      <td>{{ $name }}</td>
      <td>{{ (int)$votes }}</td>
      <td>
        @if($id)
          <a href="{{ url('/profile-member/'.$id) }}" target="_blank"
             class="btn btn-sm btn-outline-primary btn-primary">مشاهده پروفایل</a>
        @endif
      </td>
    </tr>
    @endif
  @endforeach
</tbody>

              </table>
            </div>
  </div>

  <div class="tab-pane fade" id="inspectors-pane" role="tabpanel">
    <div class="table-responsive">
              <table class="table table-striped table-hover align-middle">
                <thead>
                  <tr>
                    <th style="width:60px">#</th>
                    <th>نام</th>
                    <th style="white-space:nowrap">تعداد رأی</th>
                    <th style="width:160px">اقدامات</th>
                  </tr>
                </thead>
               <tbody>
  @foreach($inspectorsSorted as $i => $u)
    @php
      $name = is_array($u) ? ($u['name'] ?? (trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: '—'))
                           : (trim(($u->name ?? '') ?: ( ($u->first_name ?? '').' '.($u->last_name ?? '') )) ?: '—');

      $votes = is_array($u) ? ($u['inspector_votes'] ?? $u['votes'] ?? 0)
                            : ($u->inspector_votes ?? $u->votes ?? 0);

      $id = is_array($u) ? ($u['id'] ?? null) : ($u->id ?? null);
    @endphp
    @if($votes != 0)
    <tr>
      <td>{{ $i+1 }}</td>
      <td>{{ $name }}</td>
      <td>{{ (int)$votes }}</td>
      <td>
        @if($id)
          <a href="{{ url('/profile-member/'.$id) }}" target="_blank"
             class="btn btn-sm btn-outline-primary btn-primary">مشاهده پروفایل</a>
        @endif
      </td>
    </tr>
     @endif
  @endforeach
</tbody>

              </table>
            </div>
  </div>
</div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">بستن</button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="guidelineModal" tabindex="-1" aria-labelledby="guidelineModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="direction: rtl; text-align: right;">
      <div class="modal-header" style="display:flex; justify-content:space-between;">
        <h5 class="modal-title" id="guidelineModalLabel">شیوه‌نامه انتخابات</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
      </div>
      <div class="modal-body" style="line-height:1.8;">
        <p>
          🔹 <strong>بند ۱:</strong> هر عضو فقط می‌تواند تا سقف تعداد مشخص‌شده در آیین‌نامه رأی دهد.
        </p>
        <p>
          🔹 <strong>بند ۲:</strong> رأی‌ها محرمانه هستند و تنها نتایج نهایی منتشر خواهد شد.
        </p>
        <p>
          🔹 <strong>بند ۳:</strong> هرگونه تخلف در روند رأی‌گیری طبق قوانین گروه بررسی خواهد شد.
        </p>
        <p>
          🔹 <strong>بند ۴:</strong> اعضا موظف به مطالعه کامل این شیوه‌نامه قبل از شرکت در انتخابات هستند.
        </p>
        <p class="text-muted">
          (اینجا می‌تونی متن کامل شیوه‌نامه‌ی واقعی خودتون رو بذاری یا از دیتابیس بخونی)
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">بستن</button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="candidatesModal" tabindex="-1" aria-labelledby="candidatesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="direction: rtl; text-align: right;">
      <div class="modal-header" style='    display: flex;
    justify-content: space-between;'>
        <h5 class="modal-title" id="candidatesModalLabel">رزومه کاندیدها</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <input type="text" id="candidateSearch" class="form-control" placeholder="جستجو بر اساس نام...">

        </div>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle" id="candidatesTable">
            <thead>
              <tr>
                <th style="width:60px">#</th>
                <th>نام</th>
                <th style="white-space:nowrap">رأی مدیر</th>
                <th style="white-space:nowrap">رأی بازرس</th>
                <th style="width:160px">اقدامات</th>
              </tr>
            </thead>
            <tbody><!-- با JS پر می‌شود --></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">بستن</button>
      </div>
    </div>
  </div>
</div>

<div class="form-group mt-3" style="direction: rtl; text-align: right; margin-bottom: 1.5rem;">
    <label style="display: block; font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-bottom: 0.75rem; direction: rtl; text-align: right;">
        هیات مدیره (حداکثر {{ $groupSetting ? $groupSetting->manager_count : 0 }} نفر را انتخاب کنید)
    </label>
  @php
    $rolesByUser = \App\Models\GroupUser::where('group_id', $group->id)->pluck('role','user_id');
  @endphp
  <select id="manager_vote" name="manager[]" multiple class="form-control" style="direction: rtl; text-align: right; width: 100%; padding: 0.75rem 1rem; border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 14px; background: rgba(248, 250, 252, 0.95); font-size: 0.9rem;">
    @foreach ($group->users as $user)
      @php $role = $rolesByUser[$user->id] ?? null; @endphp
      @if(($role !== 4) && ($role !== 0))
                <option value="{{ $user->id }}"
                        @if (in_array($user->id, $selectedVotesManager)) selected @endif
                        style="direction: rtl; text-align: right;">
                    {{ $user->first_name . ' ' . $user->last_name }}
                    ({{ $managerCounts[$user->id] ?? 0 }} رأی)
                </option>
            @endif
        @endforeach
    </select>
</div>

<div class="form-group" style="direction: rtl; text-align: right; margin-bottom: 1.5rem;">
    <label style="display: block; font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-bottom: 0.75rem; direction: rtl; text-align: right;">
        بازرس ها (حداکثر {{ $groupSetting ? $groupSetting->inspector_count : 0 }} نفر را انتخاب کنید)
    </label>
  <select id="inspector_vote" name="inspector[]" multiple class="form-control" style="direction: rtl; text-align: right; width: 100%; padding: 0.75rem 1rem; border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 14px; background: rgba(248, 250, 252, 0.95); font-size: 0.9rem;">
    @foreach ($group->users as $user)
      @php $role = $rolesByUser[$user->id] ?? null; @endphp
      @if(($role !== 4) && ($role !== 0))
                <option value="{{ $user->id }}"
                        @if (in_array($user->id, $selectedVotesInspector)) selected @endif
                        style="direction: rtl; text-align: right;">
                    {{ $user->first_name . ' ' . $user->last_name }}
                    ({{ $inspectorCounts[$user->id] ?? 0 }} رأی)
                </option>
            @endif
        @endforeach
    </select>
</div>


                  <input type="submit" value="ثبت" class="election-submit-btn">

    </form>
    
    @endif
</div>
@php
$allOptions = $group->users->map(function ($u) use ($managerCounts, $inspectorCounts) {
    return [
        'id' => $u->id,
        'text' => trim($u->first_name . ' ' . $u->last_name),
        'role' => $u->pivot->role ?? $u->role,
        'manager_votes'   => (int)($managerCounts[$u->id] ?? 0),
        'inspector_votes' => (int)($inspectorCounts[$u->id] ?? 0),
    ];
});
@endphp

<script>
// تعریف global برای allOptions
window.electionAllOptions = @json($allOptions);

$(document).ready(function(){
  // انتقال مدال‌ها به body برای جلوگیری از مشکلات z-index
  $('#candidatesModal').appendTo('body');
  $('#guidelineModal').appendTo('body');
  $('#topVotesModal').appendTo('body');
  
  // مقداردهی اولیه جدول کاندیدها
  if (typeof applyFilters === 'function') {
    applyFilters();
  }
});

// تابع‌های باز کردن مدال‌ها - باید global باشند و بعد از لود شدن Bootstrap
window.openCandidatesModal = function() {
  console.log('openCandidatesModal called');
  try {
    // ریست فیلترها
    if (jQuery && jQuery('#candidateSearch').length) {
      jQuery('#candidateSearch').val('');
    }
    if (window.applyFilters && typeof window.applyFilters === 'function') {
      window.applyFilters();
    }
    // استفاده از Bootstrap modal
    const modalElement = document.getElementById('candidatesModal');
    if (modalElement) {
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        console.log('Using Bootstrap 5 Modal');
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
      } else if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        console.log('Using jQuery Bootstrap Modal');
        jQuery(modalElement).modal('show');
      } else {
        console.error('No modal library found');
        // Fallback: نمایش ساده
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modalBackdrop';
        document.body.appendChild(backdrop);
      }
    } else {
      console.error('candidatesModal element not found');
    }
  } catch(e) {
    console.error('Error opening candidates modal:', e);
  }
};

window.openGuidelineModal = function() {
  console.log('openGuidelineModal called');
  try {
    const modalElement = document.getElementById('guidelineModal');
    if (modalElement) {
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
      } else if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalElement).modal('show');
      } else {
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modalBackdrop2';
        document.body.appendChild(backdrop);
      }
    }
  } catch(e) {
    console.error('Error opening guideline modal:', e);
  }
};

window.openTopVotesModal = function() {
  console.log('openTopVotesModal called');
  try {
    const modalElement = document.getElementById('topVotesModal');
    if (modalElement) {
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
      } else if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalElement).modal('show');
      } else {
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modalBackdrop3';
        document.body.appendChild(backdrop);
      }
    }
  } catch(e) {
    console.error('Error opening top votes modal:', e);
  }
};

</script>

<script>
  // تابع profileUrlOf - باید global باشد
  window.profileUrlOf = function(id){
      return '/profile-member/' + id; 
  };

  // تعریف توابع برای جدول کاندیدها
  (function(){
    // استفاده از allOptions global
    const allOptions = window.electionAllOptions || @json($allOptions);

    // فقط کاندیدهای فعال (role == 1)
    function candidatesBase(){
      return allOptions.filter(u => String(u.role) === '1');
    }

    function renderTable(rows){
      const $tbody = $('#candidatesTable tbody');
      $tbody.empty();
      if(!rows.length){
        $tbody.append('<tr><td colspan="5" class="text-center text-muted">چیزی یافت نشد</td></tr>');
        return;
      }
      rows.forEach((u, idx) => {
        const tr = `
          <tr>
            <td>${idx+1}</td>
            <td>${u.text}</td>
            <td>${u.manager_votes ?? 0}</td>
            <td>${u.inspector_votes ?? 0}</td>
            <td>
              <a href="${window.profileUrlOf(u.id)}" target="_blank" class="btn btn-sm btn-outline-primary btn-primary">
                مشاهده پروفایل
              </a>
            </td>
          </tr>
        `;
        $tbody.append(tr);
      });
    }

    // تعریف global برای applyFilters
    window.applyFilters = function(){
      const q = ($('#candidateSearch').val() || '').trim();
      const f = $('#candidateFilter').val() || 'all'; // all | manager | inspector

      let list = candidatesBase();

      if (q.length){
        const qn = q.toLowerCase();
        list = list.filter(u => (u.text || '').toLowerCase().includes(qn));
      }

      if (f === 'manager'){
        // فقط کسانی که حداقل یک رأی مدیر دارند
        list = list.filter(u => Number(u.manager_votes || 0) > 0);
      } else if (f === 'inspector'){
        list = list.filter(u => Number(u.inspector_votes || 0) > 0);
      }

      renderTable(list);
    };

    // سرچ و فیلتر زنده
    $(document).on('input', '#candidateSearch', window.applyFilters);
    $(document).on('change', '#candidateFilter', window.applyFilters);

    // مقداردهی اولیه
    if ($('#candidatesModal').length) {
      window.applyFilters();
    }

  })();
</script>


<script>
// تابع برای بروزرسانی Select2 با نمایش تعداد رأی - باید global باشد
window.updateElectionSelect2 = function() {
  console.log('updateElectionSelect2 called');
  
  if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
    console.error('jQuery or Select2 not loaded');
    setTimeout(window.updateElectionSelect2, 500);
    return;
  }
  
  const $inspector = jQuery('#inspector_vote');
  const $manager = jQuery('#manager_vote');
  const allOptions = window.electionAllOptions || [];
  
  console.log('Inspector element:', $inspector.length, 'Manager element:', $manager.length);
  console.log('All options:', allOptions.length);
  
  if (!$inspector.length || !$manager.length) {
    console.error('Select elements not found in DOM');
    return;
        }
  
  // نابود کردن Select2 قبلی اگر وجود دارد
  try {
    if ($inspector.data('select2')) {
      $inspector.select2('destroy');
    }
    if ($manager.data('select2')) {
      $manager.select2('destroy');
    }
  } catch(e) {
    console.warn('Error destroying select2:', e);
  }
  
  // تابع برای بروزرسانی Select boxes
  function updateSelectBoxes() {
    const selectedInspectors = ($inspector.val() || []).map(String);
    const selectedManagers = ($manager.val() || []).map(String);
    
    console.log('Selected inspectors:', selectedInspectors);
    console.log('Selected managers:', selectedManagers);
    
          // بروزرسانی لیست بازرس‌ها
            $inspector.empty();
            allOptions.forEach(user => {
      if (String(user.role) === '1' && !selectedManagers.includes(String(user.id))) {
        const votes = user.inspector_votes || 0;
        const label = `${user.text} (${votes} رأی)`;
        const isSelected = selectedInspectors.includes(String(user.id));
        const newOption = new Option(label, user.id, isSelected, isSelected);
                    $inspector.append(newOption);
                }
            });
            
            // بروزرسانی لیست مدیرها
            $manager.empty();
            allOptions.forEach(user => {
      if (String(user.role) === '1' && !selectedInspectors.includes(String(user.id))) {
        const votes = user.manager_votes || 0;
        const label = `${user.text} (${votes} رأی)`;
        const isSelected = selectedManagers.includes(String(user.id));
        const newOption = new Option(label, user.id, isSelected, isSelected);
                    $manager.append(newOption);
                }
            });
  }
  
  // مقداردهی اولیه Select boxes
  updateSelectBoxes();
  
  // راه‌اندازی Select2 با تنظیمات RTL
  try {
    $inspector.select2({
      dir: "rtl",
      placeholder: "انتخاب بازرس",
      language: {
        noResults: function() { return "نتیجه‌ای یافت نشد"; },
        searching: function() { return "در حال جستجو..."; }
      },
      width: '100%',
      dropdownAutoWidth: true
    });
    
    $manager.select2({
      dir: "rtl",
      placeholder: "انتخاب مدیر",
      language: {
        noResults: function() { return "نتیجه‌ای یافت نشد"; },
        searching: function() { return "در حال جستجو..."; }
      },
      width: '100%',
      dropdownAutoWidth: true
    });
    
    console.log('Select2 initialized successfully');
    
    // Event listener برای تغییرات
    $inspector.off('change.select2-update').on('change.select2-update', function() {
      console.log('🔄 Inspector changed');
      updateSelectBoxes();
      // تریگر مجدد برای refresh
      setTimeout(function() {
            $inspector.trigger('change.select2');
            $manager.trigger('change.select2');
      }, 100);
    });
    
    $manager.off('change.select2-update').on('change.select2-update', function() {
      console.log('🔄 Manager changed');
      updateSelectBoxes();
      // تریگر مجدد برای refresh
      setTimeout(function() {
        $inspector.trigger('change.select2');
        $manager.trigger('change.select2');
      }, 100);
    });
    
    // رفع مشکل نمایش dropdown و راست‌چین
    $inspector.on('select2:open', function() {
      console.log('📂 Inspector dropdown opened');
      setTimeout(function() {
        jQuery('.select2-dropdown').css({
          'direction': 'rtl',
          'text-align': 'right'
        });
        jQuery('.select2-results__option').css({
          'direction': 'rtl',
          'text-align': 'right'
        });
        jQuery('.select2-search__field').css({
          'direction': 'rtl',
          'text-align': 'right'
        });
      }, 10);
    });
    
    $manager.on('select2:open', function() {
      console.log('📂 Manager dropdown opened');
      setTimeout(function() {
        jQuery('.select2-dropdown').css({
          'direction': 'rtl',
          'text-align': 'right'
        });
        jQuery('.select2-results__option').css({
          'direction': 'rtl',
          'text-align': 'right'
        });
        jQuery('.select2-search__field').css({
          'direction': 'rtl',
          'text-align': 'right'
        });
      }, 10);
    });
  } catch(e) {
    console.error('Error initializing Select2:', e);
  }
};

// اجرای کد بعد از لود شدن کامل صفحه
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function() {
    setTimeout(window.updateElectionSelect2, 500);
  });
} else {
  setTimeout(window.updateElectionSelect2, 500);
}

// همچنین بعد از باز شدن مدال انتخابات
window.addEventListener('electionModalOpened', function() {
  setTimeout(window.updateElectionSelect2, 300);
});

    </script>

    