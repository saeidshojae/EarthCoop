@extends('layouts.unified')

@section('title', 'چت خصوصی - ' . config('app.name', 'EarthCoop'))

@section('content')
@php
    $section = $section ?? request()->query('section', 'requests');
    $status = $status ?? request()->query('status', 'pending');
@endphp
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-0">پنل چت خصوصی</h4>
            <p class="text-muted mb-0">درخواست‌های چت و گفتگوهای خصوصی خود را از یک صفحه مدیریت کنید.</p>
        </div>
        <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary btn-sm">بازگشت به پروفایل</a>
    </div>

    <div id="chat-panel-body-wrapper" class="position-relative">
        <div id="chat-panel-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-flex justify-content-center align-items-center" style="z-index: 20;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">در حال بارگذاری...</span>
            </div>
        </div>

        <div id="chat-panel-body">
            @include('chat-requests.partials.body')
        </div>
    </div>
</div>
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
                    console.error('Failed to load chat panel content:', error);
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

        window.addEventListener('popstate', function(event) {
            fetchTabContent(window.location.href, false);
        });
    });
</script>
@endpush
