@extends('layouts.unified')

@section('title', 'انتخابات جاری - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    /* Elections Section Styles from entekhabat.html */
    .elections-section {
        width: 100%;
        background-color: var(--color-pure-white);
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 2.5rem;
        margin-top: 0;
        border: 1px solid #e2e8f0;
        animation: fadeIn 0.8s ease-out;
    }

    .elections-title {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--color-dark-green);
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 3px solid var(--color-earth-green);
        position: relative;
        text-align: right;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .elections-title::after {
        content: '';
        position: absolute;
        bottom: -3px;
        right: 0;
        width: 80px;
        height: 3px;
        background: linear-gradient(90deg, var(--color-digital-gold), var(--color-accent-peach));
        border-radius: 2px;
    }

    /* Table wrapper for horizontal scrolling */
    .data-table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--color-light-gray);
    }

    .data-table {
        width: 100%;
        min-width: 700px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .data-table th, .data-table td {
        padding: 15px 20px;
        text-align: center;
        border-bottom: 1px solid var(--color-light-gray);
        border-left: 1px solid var(--color-light-gray);
    }

    .data-table th {
        background: linear-gradient(180deg, var(--color-light-gray) 0%, var(--color-pure-white) 100%);
        color: var(--color-dark-green);
        font-weight: 700;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .data-table th:first-child {
        border-top-right-radius: 0.75rem;
    }

    .data-table th:last-child {
        border-top-left-radius: 0.75rem;
        border-left: none;
    }

    .data-table tbody tr:nth-child(even) {
        background-color: var(--color-light-gray);
    }

    .data-table tbody tr:hover {
        background-color: var(--color-light-gray);
        color: var(--color-dark-green);
        transform: scale(1.005);
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    }

    .data-table td {
        color: var(--color-gentle-black);
        font-size: 0.95rem;
        transition: all 0.2s ease-in-out;
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    .data-table tbody tr:last-child td:first-child {
        border-bottom-right-radius: 0.75rem;
    }
    .data-table tbody tr:last-child td:last-child {
        border-bottom-left-radius: 0.75rem;
        border-left: none;
    }

    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 9999px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .status-badge.active {
        background-color: #dcfce7;
        color: #166534;
    }

    .status-badge.pending {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-badge.finished {
        background-color: #e5e7eb;
        color: #374151;
    }

    /* Action Button */
    .action-button {
        background: linear-gradient(45deg, var(--color-ocean-blue) 0%, var(--color-dark-blue) 100%);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        text-decoration: none;
        display: inline-block;
    }

    .action-button:hover {
        background: linear-gradient(45deg, var(--color-dark-blue) 0%, var(--color-ocean-blue) 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        color: white;
        text-decoration: none;
    }

    .action-button:active {
        transform: translateY(1px);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .action-button.secondary {
        background: var(--color-gentle-black);
    }

    .action-button.secondary:hover {
        background: #0f172a;
    }

    /* Votes List */
    .votes-list {
        list-style: none;
        padding: 0;
        margin: 0;
        text-align: right;
    }

    .votes-list li {
        padding: 4px 0;
        color: var(--color-gentle-black);
    }

    /* Dark Mode Support */
    body.dark-mode .elections-section {
        background-color: #2d2d2d;
        border-color: #404040;
    }

    body.dark-mode .elections-title {
        color: #e0e0e0;
    }

    body.dark-mode .data-table th {
        background: linear-gradient(180deg, #3a3a3a 0%, #2d2d2d 100%);
        color: #e0e0e0;
    }

    body.dark-mode .data-table tbody tr:nth-child(even) {
        background-color: #3a3a3a;
    }

    body.dark-mode .data-table tbody tr:hover {
        background-color: #404040;
        color: #e0e0e0;
    }

    body.dark-mode .data-table td {
        color: #e0e0e0;
    }

    body.dark-mode .status-badge.active {
        background-color: #166534;
        color: #dcfce7;
    }

    body.dark-mode .status-badge.pending {
        background-color: #92400e;
        color: #fef3c7;
    }

    body.dark-mode .status-badge.finished {
        background-color: #374151;
        color: #e5e7eb;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .elections-section {
            padding: 1.5rem;
        }
        .elections-title {
            font-size: 1.6rem;
        }
        .data-table th, .data-table td {
            padding: 8px 10px;
            font-size: 0.75rem;
        }
        .action-button {
            padding: 5px 10px;
            font-size: 0.7rem;
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
@endpush

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Elections Section -->
    <section class="elections-section">
        <h2 class="elections-title">انتخابات جاری</h2>
        
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>گروه</th>
                        <th>وضعیت</th>
                        <th>رای‌های شما</th>
                        <th>تاریخ ثبت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($currentElections as $election)
                    <tr>
                        <td>
                            <strong>{{ $election->group->name }}</strong>
                        </td>
                        <td>
                            @php
                                $now = now();
                                $status = 'pending';
                                if ($election->ends_at < $now) {
                                    $status = 'finished';
                                } elseif ($election->starts_at <= $now && $election->ends_at >= $now) {
                                    $status = 'active';
                                }
                            @endphp
                            <span class="status-badge {{ $status }}">
                                @if($status == 'active')
                                    فعال
                                @elseif($status == 'finished')
                                    به اتمام رسیده
                                @else
                                    در انتظار شروع
                                @endif
                            </span>
                        </td>
                        <td>
                            @if($election->yourVotes->isEmpty())
                                <span class="text-gray-500">هنوز رأی نداده‌اید</span>
                            @else
                                <ul class="votes-list">
                                    @foreach($election->yourVotes as $vote)
                                        <li>
                                            <i class="fas fa-check-circle text-earth-green ml-2"></i>
                                            {{ $vote->user->fullName() }} 
                                            <span class="text-gray-600">({{ $vote->position == 0 ? 'بازرس' : 'مدیر' }})</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td>{{ verta($election->created_at)->format('Y-m-d') }}</td>
                        <td>
                            @if($election->yourVotes->isEmpty())
                                <a href="{{ route('groups.chat', [$election->group->id, '#electionRedirect']) }}" 
                                   class="action-button">
                                    <i class="fas fa-vote-yea ml-2"></i>
                                    مشاهده و رأی
                                </a>
                            @else
                                <a href="{{ route('groups.chat', [$election->group->id, '#electionRedirect']) }}" 
                                   class="action-button secondary">
                                    <i class="fas fa-edit ml-2"></i>
                                    {{ $status == 'finished' ? 'نتایج' : 'ویرایش رأی' }}
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-500">
                            <i class="fas fa-info-circle text-4xl mb-4 text-gray-400"></i>
                            <p class="text-lg">در حال حاضر انتخابات فعالی وجود ندارد</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
