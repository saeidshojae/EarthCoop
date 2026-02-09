<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>

    .group-avatar {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background-color: #ecf5ff;
    color: rgb(61, 131, 175);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
    margin-left: 16px !important; 
    }
    .rounded-circle{
            width: 3.5rem;
    height: 3.5rem;
    margin-left: 0.5rem;
        }
    @media only screen and (max-width: 990px) {
        .mySwiper{
            padding: 0 !important;
        }
    }
    
    
@keyframes blink {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0.25;
    }
    100% {
        opacity: 1;
    }
}

.blinking-text {
    animation: blink 2s infinite;
}
</style>
<div class="col-lg-4 col-md-12 d-lg-block navbarprofile">
    <div class="panel-side">
        <div class="content-box pb-0">
            <div class="container-fluid">
                <div class="panel-box">
                    <div class="d-flex align-items-center">
                        {!! auth()->user()->profile() !!}
                        <div class="d-grid gap-2">
                            <h6 class="font-14 main-color-one-color"><a href='{{ route('profile.show') }}' style='text-decoration: none; color: rgba(var(--bs-link-color-rgb),var(--bs-link-opacity, 1))'>حساب کاربری من</a></h6>
                            <h6 class="font-14"><a href='{{ route('profile.show') }}' style='text-decoration: none; color: #333'>{{ Auth::user()->fullName() }}</a></h6>
                        </div>
                    </div>
                </div>
                <div class="profile-box">
                    <nav class="navbar profile-box-nav" style="background: #fff !important">
                        <ul class="navbar-nav flex-column">
                            @if (auth()->user()->is_admin == 1)
                                <li class="nav-item"><a class="nav-link" href="{{ route('admin.dashboard') }}">
                                    <i class="bi bi-house-door"></i>ورود به پنل مدیریت</a>
                                </li>
                            @endif
                            <li class="nav-item position-relative">
                                <a class="nav-link" href="{{ route('notifications.index') }}">
                                    <i class="bi bi-bell"></i><b>اعلان‌ها</b>
                                    @if(auth()->user()->unreadNotifications->count() > 0)
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                                              style="font-size: 0.65rem;">
                                            {{ auth()->user()->unreadNotifications->count() }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('groups.index') }}">
                                <i class="bi bi-house-door"></i><b>گروه های من</b></a>
                            </li>
                                                        <li class="nav-item"><a class="nav-link" href="{{ route('history.index') }}">
                                <i class="bi bi-person-circle"></i><b>مشارکت های من</b></a>
                            </li>
                            
                            <li class="nav-item"><a class="nav-link" href="{{ route('history.election') }}">
                                <i class="bi bi-bank"></i><b>انتخابات جاری</b></a>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('history.poll') }}">
                                <i class="bi bi-credit-card"></i><b>نظرسنجی های جاری</b></a>
                            </li>
                            @php
                                $accountService = app(\App\Modules\NajmBahar\Services\AccountService::class);
                                $needsNajmAgreement = !$accountService->hasMainAccount(auth()->id());
                            @endphp
                            <li class="nav-item {{ $needsNajmAgreement ? 'blinking-text' : '' }}"><a class="nav-link" href="{{ route('spring-accounts') }}">
                                <i class="bi bi-piggy-bank"></i><b>حساب مالی نجم بهار</b></a>
                            </li>
                            
                            <li class="nav-item"><a class="nav-link" href="{{ route('my-invation-code') }}">
                                <i class="bi bi-person-circle"></i><b>دعوت از دوستان</b></a>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('profile.edit') }}">
                                <i class="bi bi-graph-up-arrow"></i><b>ویرایش حساب کاربری</b></a>
                            </li>
                            <hr style="margin: .5rem 0">
                            
                            <li class="nav-item">
                                <a class="nav-link" href="#" onclick="event.preventDefault(); document.getElementById('logout-form-navbar').submit();">
                                    <i class="bi bi-graph-up-arrow"></i><b>خروج از حساب کاربری</b>
                                </a>
                                <form id="logout-form-navbar" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </li>
                            
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>