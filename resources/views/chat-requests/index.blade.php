@extends('layouts.unified')

@section('title', 'گفتگوهای خصوصی - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .pm-page {
        direction: rtl;
        width: 100%;
        margin: 0;
        padding: .65rem .55rem 1rem;
    }

    .pm-page-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .2rem .35rem .8rem;
    }

    .pm-page-title {
        margin: 0;
        color: #1d2924;
        font-size: 1.12rem;
        font-weight: 850;
        line-height: 1.55;
    }

    .pm-page-back {
        display: inline-flex;
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        align-items: center;
        justify-content: center;
        border: 1px solid #e0e7e3;
        border-radius: 14px;
        background: #fff;
        color: #526159;
        text-decoration: none;
    }

    .pm-page-back:focus-visible {
        outline: 3px solid rgba(35, 122, 86, .23);
        outline-offset: 2px;
    }

    .pm-page-panel {
        position: relative;
    }

    .pm-page-loading {
        position: absolute;
        inset: 0;
        z-index: 40;
        display: flex;
        min-height: 160px;
        align-items: flex-start;
        justify-content: center;
        padding-top: 4rem;
        border-radius: 18px;
        background: rgba(255, 255, 255, .78);
        backdrop-filter: blur(3px);
    }

    .pm-page-loading.d-none { display: none !important; }

    @media (min-width: 769px) {
        .pm-page {
            max-width: 940px;
            margin: 0 auto;
            padding: 1.5rem 1rem 2rem;
        }

        .pm-page-heading {
            padding: 0 .15rem .9rem;
        }

        .pm-page-title {
            font-size: 1.3rem;
        }
    }
</style>
@endpush

@section('content')
@php
    $section = $section ?? request()->query('section', 'requests');
    $box = $box ?? request()->query('box', 'received');
    $status = $status ?? request()->query('status', 'pending');
@endphp
<section class="pm-page" data-private-messaging-page aria-labelledby="private-messaging-page-title">
    <header class="pm-page-heading">
        <h1 class="pm-page-title" id="private-messaging-page-title">گفتگوهای خصوصی</h1>
        <a href="{{ route('profile.show') }}" class="pm-page-back" aria-label="بازگشت به پروفایل">
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
    </header>

    <div id="chat-panel-body-wrapper" class="pm-page-panel">
        <div id="chat-panel-loading" class="pm-page-loading d-none" aria-live="polite" aria-busy="true">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">در حال بارگذاری...</span>
            </div>
        </div>

        <div id="chat-panel-body">
            @include('chat-requests.partials.body')
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatPanelBody = document.getElementById('chat-panel-body');

        if (!chatPanelBody) {
            return;
        }

        function handleTabClick(event) {
            const anchor = event.target.closest('a');
            if (!anchor || !anchor.href) {
                return;
            }

            const url = new URL(anchor.href);
            const currentUrl = new URL(window.location.href);

            if (url.pathname !== currentUrl.pathname) {
                return;
            }

            event.preventDefault();
            fetchTabContent(url.href, true);
        }

        function fetchTabContent(url, pushState = false) {
            showLoading(true);

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    chatPanelBody.innerHTML = html;
                    if (pushState) {
                        window.history.pushState({ path: url }, '', url);
                    }
                    attachLinkHandlers();
                })
                .catch(error => {
                    console.error('Failed to load private messaging content:', error);
                })
                .finally(() => {
                    showLoading(false);
                });
        }

        function attachLinkHandlers() {
            const links = chatPanelBody.querySelectorAll('.js-chat-tab-link');
            links.forEach(link => {
                link.removeEventListener('click', handleTabClick);
                link.addEventListener('click', handleTabClick);
            });
        }

        function showLoading(show) {
            const loadingOverlay = document.getElementById('chat-panel-loading');
            if (!loadingOverlay) return;
            loadingOverlay.classList.toggle('d-none', !show);
        }

        attachLinkHandlers();

        window.addEventListener('popstate', function() {
            fetchTabContent(window.location.href, false);
        });
    });
</script>
@endpush
