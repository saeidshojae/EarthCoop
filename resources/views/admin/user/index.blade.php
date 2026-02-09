@extends('layouts.admin')

@section('title', 'مدیریت کاربران - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت کاربران')
@section('page-description', 'ایجاد، ویرایش و مدیریت کاربران سیستم')

@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/select/2.0.3/css/select.dataTables.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    /* CSS Variables for Dark/Light Mode */
    :root {
        --bg-primary: #ffffff;
        --bg-secondary: #f9fafb;
        --bg-tertiary: #f3f4f6;
        --text-primary: #1e293b;
        --text-secondary: #4b5563;
        --text-tertiary: #6b7280;
        --border-color: #e5e7eb;
        --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
    
    .dark-mode,
    .dark-mode:root,
    html.dark-mode,
    body.dark-mode {
        --bg-primary: #0f172a;
        --bg-secondary: #1e293b;
        --bg-tertiary: #334155;
        --text-primary: #f1f5f9;
        --text-secondary: #cbd5e1;
        --text-tertiary: #94a3b8;
        --border-color: #475569;
        --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }
    
    /* Auto Dark Mode Detection - Fallback */
    @media (prefers-color-scheme: dark) {
        :root:not(.light-mode) {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-tertiary: #94a3b8;
            --border-color: #475569;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }
    }
    
    body {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        transition: background-color 0.3s ease, color 0.3s ease;
    }
    
    .user-management-card {
        background: var(--bg-primary);
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        padding: 2rem;
        margin-bottom: 2rem;
        transition: background-color 0.3s ease;
    }
    
    .user-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .user-stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .user-stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    }
    
    .user-stat-card.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .user-stat-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .user-stat-card.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .user-stat-card.danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    
    .user-stat-card.info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .user-stat-icon {
        font-size: 2.5rem;
        opacity: 0.8;
        margin-bottom: 0.5rem;
    }
    
    .user-stat-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.25rem;
    }
    
    .user-stat-label {
        font-size: 0.875rem;
        opacity: 0.9;
    }
    
    .user-filters-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        transition: background-color 0.3s ease;
    }
    
    .user-filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .user-filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .user-filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .user-filter-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }
    
    .user-filter-input {
        padding: 0.625rem;
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        font-size: 0.875rem;
        background: var(--bg-primary);
        color: var(--text-primary);
        transition: all 0.2s;
    }
    
    .user-filter-input::placeholder {
        color: var(--text-tertiary);
    }
    
    .user-filter-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .user-filter-btn {
        padding: 0.625rem 1.25rem;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .user-filter-btn:hover {
        background: #5568d3;
    }
    
    .user-management-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .user-management-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .user-header-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .user-create-btn, .user-export-btn {
        padding: 0.75rem 1.5rem;
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .user-create-btn {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .user-create-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        color: white;
    }
    
    .user-export-btn {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .user-export-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }
    
    .user-bulk-actions {
        background: var(--bg-tertiary);
        padding: 1rem;
        border-radius: 0.75rem;
        margin-bottom: 1rem;
        display: none;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        transition: background-color 0.3s ease;
    }
    
    .user-bulk-actions.active {
        display: flex;
    }
    
    .user-bulk-select-count {
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .user-bulk-btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.875rem;
    }
    
    .user-bulk-btn.activate {
        background: #10b981;
        color: white;
    }
    
    .user-bulk-btn.deactivate {
        background: #6b7280;
        color: white;
    }
    
    .user-bulk-btn.suspend {
        background: #f59e0b;
        color: white;
    }
    
    .user-bulk-btn.delete {
        background: #ef4444;
        color: white;
    }
    
    .user-bulk-btn.export {
        background: #3b82f6;
        color: white;
    }
    
    .user-table-wrapper {
        overflow-x: auto;
    }
    
    .user-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .user-table thead {
        background: var(--bg-secondary);
        transition: background-color 0.3s ease;
    }
    
    .user-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 700;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        font-size: 0.875rem;
        white-space: nowrap;
    }
    
    .user-table td {
        padding: 0.75rem;
        text-align: right;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border-color);
        font-size: 0.75rem;
    }
    
    .user-table tr:hover {
        background-color: var(--bg-secondary);
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        display: inline-block;
    }
    
    .user-avatar-placeholder {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.875rem;
    }
    
    .user-status-badge {
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .user-status-badge.active {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .user-status-badge.inactive {
        background: #e5e7eb;
        color: #374151;
        border: 1px solid #d1d5db;
    }
    
    .user-status-badge.suspended {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    
    .dark-mode .user-status-badge.active {
        background: rgba(16, 185, 129, 0.2);
        color: #6ee7b7;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    
    .dark-mode .user-status-badge.inactive {
        background: rgba(148, 163, 184, 0.2);
        color: #cbd5e1;
        border: 1px solid rgba(148, 163, 184, 0.3);
    }
    
    .dark-mode .user-status-badge.suspended {
        background: rgba(245, 158, 11, 0.2);
        color: #fcd34d;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }
    
    .user-online-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-left: 0.5rem;
    }
    
    .user-online-indicator.online {
        background: #10b981;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
    }
    
    .user-online-indicator.offline {
        background: #9ca3af;
    }
    
    .user-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .user-action-btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        text-decoration: none;
    }
    
    .user-action-btn.groups {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    
    .user-action-btn.groups:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        color: white;
    }
    
    .user-action-btn.edit {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .user-action-btn.edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }
    
    .user-action-btn.delete {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    
    .user-action-btn.delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        color: white;
    }
    
    .user-action-btn.view {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
    }
    
    .user-action-btn.view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        color: white;
    }
    
    .user-action-btn.details {
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        color: white;
    }
    
    .user-action-btn.details:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        color: white;
    }
    
    .user-action-btn.reset-password {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
    }
    
    .user-action-btn.reset-password:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        color: white;
    }
    
    .user-field-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .user-field-list li {
        padding: 0.25rem 0;
        font-size: 0.75rem;
        color: var(--text-tertiary);
    }
    
    .user-gender-badge {
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .user-gender-badge.male {
        background-color: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    
    .user-gender-badge.female {
        background-color: #fce7f3;
        color: #9f1239;
        border: 1px solid #fbcfe8;
    }
    
    .dark-mode .user-gender-badge.male {
        background-color: rgba(59, 130, 246, 0.2);
        color: #93c5fd;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    
    .dark-mode .user-gender-badge.female {
        background-color: rgba(236, 72, 153, 0.2);
        color: #f9a8d4;
        border: 1px solid rgba(236, 72, 153, 0.3);
    }
    
    .user-verified-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        color: #10b981;
        font-size: 0.75rem;
    }
    
    /* Dark Mode Gradient Cards */
    .dark-mode .user-stat-card.primary {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    }
    
    .dark-mode .user-stat-card.success {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    }
    
    .dark-mode .user-stat-card.warning {
        background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
    }
    
    .dark-mode .user-stat-card.danger {
        background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    }
    
    .dark-mode .user-stat-card.info {
        background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
    }
    
    /* DataTables Dark Mode */
    .dark-mode .dataTables_wrapper .dataTables_length,
    .dark-mode .dataTables_wrapper .dataTables_filter,
    .dark-mode .dataTables_wrapper .dataTables_info,
    .dark-mode .dataTables_wrapper .dataTables_processing,
    .dark-mode .dataTables_wrapper .dataTables_paginate {
        color: var(--text-secondary) !important;
    }
    
    .dark-mode .dataTables_wrapper .dataTables_length select,
    .dark-mode .dataTables_wrapper .dataTables_filter input {
        background: var(--bg-tertiary) !important;
        color: var(--text-primary) !important;
        border: 1px solid var(--border-color) !important;
    }
    
    .dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: var(--text-secondary) !important;
        background: transparent !important;
    }
    
    .dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--bg-tertiary) !important;
        color: var(--text-primary) !important;
        border-color: var(--border-color) !important;
    }
    
    .dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%) !important;
        color: white !important;
        border-color: transparent !important;
    }
    
    /* Chart Container Dark Mode */
    .dark-mode .user-management-card > div > div[style*="background: #f9fafb"],
    .dark-mode .user-management-card > div > div[style*="background:#f9fafb"] {
        background: var(--bg-tertiary) !important;
    }
    
    .dark-mode .user-management-card h3 {
        color: var(--text-primary) !important;
    }
    
    @media (prefers-color-scheme: dark) {
        :root:not(.light-mode) .user-management-card > div > div[style*="background: #f9fafb"],
        :root:not(.light-mode) .user-management-card > div > div[style*="background:#f9fafb"] {
            background: var(--bg-tertiary) !important;
        }
        
        :root:not(.light-mode) .user-management-card h3 {
            color: var(--text-primary) !important;
        }
        
        :root:not(.light-mode) .user-stat-card.primary {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        }
        
        :root:not(.light-mode) .user-stat-card.success {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }
        
        :root:not(.light-mode) .user-stat-card.warning {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        }
        
        :root:not(.light-mode) .user-stat-card.danger {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        }
        
        :root:not(.light-mode) .user-stat-card.info {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
        }
        
        :root:not(.light-mode) .dataTables_wrapper .dataTables_length,
        :root:not(.light-mode) .dataTables_wrapper .dataTables_filter,
        :root:not(.light-mode) .dataTables_wrapper .dataTables_info {
            color: var(--text-secondary) !important;
        }
        
        :root:not(.light-mode) .dataTables_wrapper .dataTables_length select,
        :root:not(.light-mode) .dataTables_wrapper .dataTables_filter input {
            background: var(--bg-tertiary) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-color) !important;
        }
        
        :root:not(.light-mode) .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-secondary) !important;
        }
        
        :root:not(.light-mode) .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%) !important;
            color: white !important;
        }
    }
    
    @media (max-width: 768px) {
        .user-management-card {
            padding: 1rem;
        }
        
        .user-stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .user-stat-value {
            font-size: 1.5rem;
        }
        
        /* اصلاح نمودارها برای موبایل */
        .user-management-card > div[style*="grid-template-columns"] {
            grid-template-columns: 1fr !important;
            gap: 1rem;
        }
        
        .user-management-card canvas {
            max-height: 250px !important;
        }
        
        .user-filters-grid {
            grid-template-columns: 1fr;
        }
        
        .user-management-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .user-header-actions {
            width: 100%;
        }
        
        .user-create-btn, .user-export-btn {
            width: 100%;
            justify-content: center;
        }
        
        /* تبدیل جدول به Card در موبایل */
        .user-table thead {
            display: none;
        }
        
        .user-table tbody tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1rem;
            background: var(--bg-primary);
        }
        
        .user-table tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border: none;
            text-align: right;
            font-size: 0.875rem;
        }
        
        .user-table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: var(--text-primary);
            margin-left: 1rem;
        }
        
        .user-table tbody td:first-child::before {
            content: "";
        }
        
        .user-actions {
            flex-direction: column;
            width: 100%;
            gap: 0.5rem;
        }
        
        .user-action-btn {
            width: 100%;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }
    }
    
    @media (max-width: 480px) {
        .user-management-card {
            padding: 0.75rem;
        }
        
        .user-stats-grid {
            grid-template-columns: 1fr;
        }
        
        .user-stat-value {
            font-size: 1.25rem;
        }
        
        .user-stat-icon {
            font-size: 2rem;
        }
        
        .user-action-btn span {
            display: none;
        }
        
        .user-action-btn {
            padding: 0.625rem;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <!-- نمودارهای تحلیلی -->
    <div class="user-management-card" style="margin-bottom: 2rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 2rem;">
            <!-- نمودار ثبت‌نام کاربران -->
            <div style="background: #f9fafb; border-radius: 12px; padding: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem;">
                    <i class="fas fa-chart-line ml-2"></i>
                    نمودار ثبت‌نام کاربران (30 روز گذشته)
                </h3>
                <canvas id="registrationChart" style="max-height: 300px;"></canvas>
            </div>
            
            <!-- نمودار توزیع جغرافیایی -->
            <div style="background: #f9fafb; border-radius: 12px; padding: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem;">
                    <i class="fas fa-map-marker-alt ml-2"></i>
                    توزیع جغرافیایی کاربران (10 استان اول)
                </h3>
                <canvas id="geographicChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- آمار -->
    <div class="user-stats-grid">
        <div class="user-stat-card primary">
            <div class="user-stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="user-stat-value">{{ number_format($stats['total']) }}</div>
            <div class="user-stat-label">کل کاربران</div>
        </div>
        <div class="user-stat-card success">
            <div class="user-stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="user-stat-value">{{ number_format($stats['active']) }}</div>
            <div class="user-stat-label">کاربران فعال</div>
        </div>
        <div class="user-stat-card warning">
            <div class="user-stat-icon">
                <i class="fas fa-pause-circle"></i>
            </div>
            <div class="user-stat-value">{{ number_format($stats['inactive']) }}</div>
            <div class="user-stat-label">کاربران غیرفعال</div>
        </div>
        <div class="user-stat-card danger">
            <div class="user-stat-icon">
                <i class="fas fa-ban"></i>
            </div>
            <div class="user-stat-value">{{ number_format($stats['suspended']) }}</div>
            <div class="user-stat-label">کاربران تعلیق شده</div>
        </div>
        <div class="user-stat-card info">
            <div class="user-stat-icon">
                <i class="fas fa-envelope-check"></i>
            </div>
            <div class="user-stat-value">{{ number_format($stats['verified']) }}</div>
            <div class="user-stat-label">ایمیل تایید شده</div>
        </div>
        <div class="user-stat-card success">
            <div class="user-stat-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="user-stat-value">{{ number_format($stats['today']) }}</div>
            <div class="user-stat-label">امروز</div>
        </div>
        <div class="user-stat-card info">
            <div class="user-stat-icon">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="user-stat-value">{{ number_format($stats['this_week']) }}</div>
            <div class="user-stat-label">این هفته</div>
        </div>
        <div class="user-stat-card primary">
            <div class="user-stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="user-stat-value">{{ number_format($stats['this_month']) }}</div>
            <div class="user-stat-label">این ماه</div>
        </div>
    </div>
    
    <!-- فیلترها -->
    <div class="user-management-card user-filters-card">
        <div class="user-filters-header">
            <h4 style="font-size: 1.125rem; font-weight: 700; color: #1e293b; margin: 0;">
                <i class="fas fa-filter ml-2"></i>
                فیلتر و جستجو
            </h4>
            <a href="{{ route('admin.users.index') }}" class="user-filter-btn" style="background: #6b7280;">
                <i class="fas fa-redo"></i>
                پاک کردن فیلترها
            </a>
        </div>
        <form method="GET" action="{{ route('admin.users.index') }}" id="filterForm">
            <div class="user-filters-grid">
                <div class="user-filter-group">
                    <label class="user-filter-label">جستجو</label>
                    <input type="text" name="search" class="user-filter-input" 
                           placeholder="نام، ایمیل، شماره تماس، کد ملی..." 
                           value="{{ request('search') }}">
                </div>
                <div class="user-filter-group">
                    <label class="user-filter-label">وضعیت</label>
                    <select name="status" class="user-filter-input">
                        <option value="">همه</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>فعال</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>غیرفعال</option>
                        <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>تعلیق شده</option>
                    </select>
                </div>
                <div class="user-filter-group">
                    <label class="user-filter-label">جنسیت</label>
                    <select name="gender" class="user-filter-input">
                        <option value="">همه</option>
                        <option value="male" {{ request('gender') == 'male' ? 'selected' : '' }}>مرد</option>
                        <option value="female" {{ request('gender') == 'female' ? 'selected' : '' }}>زن</option>
                    </select>
                </div>
                <div class="user-filter-group">
                    <label class="user-filter-label">ایمیل تایید شده</label>
                    <select name="email_verified" class="user-filter-input">
                        <option value="">همه</option>
                        <option value="1" {{ request('email_verified') == '1' ? 'selected' : '' }}>بله</option>
                        <option value="0" {{ request('email_verified') == '0' ? 'selected' : '' }}>خیر</option>
                    </select>
                </div>
                <div class="user-filter-group">
                    <label class="user-filter-label">استان</label>
                    <select name="province_id" class="user-filter-input">
                        <option value="">همه</option>
                        @foreach($provinces as $province)
                        <option value="{{ $province->id }}" {{ request('province_id') == $province->id ? 'selected' : '' }}>
                            {{ $province->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="user-filter-group">
                    <label class="user-filter-label">از تاریخ</label>
                    <input type="date" name="created_from" class="user-filter-input" value="{{ request('created_from') }}">
                </div>
                <div class="user-filter-group">
                    <label class="user-filter-label">تا تاریخ</label>
                    <input type="date" name="created_to" class="user-filter-input" value="{{ request('created_to') }}">
                </div>
                <div class="user-filter-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="user-filter-btn" style="width: 100%;">
                        <i class="fas fa-search"></i>
                        اعمال فیلتر
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Header و عملیات -->
    <div class="user-management-card">
        <div class="user-management-header">
            <h3>
                <i class="fas fa-users ml-2"></i>
                لیست کاربران
            </h3>
                <div class="user-header-actions">
                    <a href="{{ route('admin.users.import') }}" class="user-export-btn" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <i class="fas fa-file-upload"></i>
                        Import کاربران
                    </a>
                    <a href="{{ route('admin.users.export.all') }}" class="user-export-btn">
                        <i class="fas fa-download"></i>
                        خروجی Excel
                    </a>
                    <a href="{{ route('admin.users.create') }}" class="user-create-btn">
                        <i class="fas fa-plus"></i>
                        ایجاد کاربر جدید
                    </a>
                </div>
        </div>
        
        <!-- عملیات دسته‌ای -->
        <div class="user-bulk-actions" id="bulkActions">
            <span class="user-bulk-select-count" id="selectedCount">0 کاربر انتخاب شده</span>
            <button type="button" class="user-bulk-btn activate" onclick="bulkAction('activate')">
                <i class="fas fa-check"></i>
                فعال کردن
            </button>
            <button type="button" class="user-bulk-btn deactivate" onclick="bulkAction('deactivate')">
                <i class="fas fa-pause"></i>
                غیرفعال کردن
            </button>
            <button type="button" class="user-bulk-btn suspend" onclick="bulkAction('suspend')">
                <i class="fas fa-ban"></i>
                تعلیق
            </button>
            <button type="button" class="user-bulk-btn export" onclick="bulkAction('export')">
                <i class="fas fa-download"></i>
                خروجی
            </button>
            <button type="button" class="user-bulk-btn delete" onclick="bulkAction('delete')">
                <i class="fas fa-trash"></i>
                حذف
            </button>
        </div>
        
        <!-- Users Table -->
        <div class="user-table-wrapper">
            <table class="user-table" id="usersTable">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                        </th>
                        <th>#</th>
                        <th>آواتار</th>
                        <th>نام</th>
                        <th>ایمیل</th>
                        <th>وضعیت</th>
                        <th>آنلاین</th>
                        <th>تاریخ ثبت‌نام</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $key => $user)
                    <tr>
                        <td data-label="">
                            <input type="checkbox" class="user-checkbox" value="{{ $user->id }}" onchange="updateBulkActions()">
                        </td>
                        <td data-label="#">{{ $key + 1 }}</td>
                        <td data-label="آواتار">
                            @if($user->avatar)
                            <img src="{{ asset('images/users/avatars/' . $user->avatar) }}" alt="Avatar" class="user-avatar">
                            @else
                            <div class="user-avatar-placeholder">
                                {{ mb_substr($user->first_name, 0, 1) }}{{ mb_substr($user->last_name, 0, 1) }}
                            </div>
                            @endif
                        </td>
                        <td data-label="نام">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <strong style="color: var(--text-primary);">
                                    {{ $user->first_name . ' ' . $user->last_name }}
                                </strong>
                                @if($user->email_verified_at)
                                <span class="user-verified-badge" title="ایمیل تایید شده">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                                @endif
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-tertiary); margin-top: 0.25rem;">
                                {{ $user->phone }}
                            </div>
                        </td>
                        <td data-label="ایمیل">{{ $user->email }}</td>
                        <td data-label="وضعیت">
                            <span class="user-status-badge {{ $user->status ?? 'active' }}">
                                @if(($user->status ?? 'active') == 'active')
                                    فعال
                                @elseif(($user->status ?? 'active') == 'inactive')
                                    غیرفعال
                                @else
                                    تعلیق شده
                                @endif
                            </span>
                        </td>
                        <td data-label="آنلاین">
                            @if($user->isOnline())
                            <span class="user-online-indicator online" title="آنلاین"></span>
                            <span style="font-size: 0.75rem; color: var(--text-tertiary);">آنلاین</span>
                            @else
                            <span class="user-online-indicator offline" title="آفلاین"></span>
                            <span style="font-size: 0.75rem; color: var(--text-tertiary);">آفلاین</span>
                            @endif
                        </td>
                        <td data-label="تاریخ ثبت‌نام">
                            @if($user->created_at)
                            @php
                                $createdAt = $user->created_at instanceof \Carbon\Carbon 
                                    ? $user->created_at 
                                    : \Carbon\Carbon::parse($user->created_at);
                            @endphp
                            <div style="font-size: 0.75rem;">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($createdAt)->format('Y/m/d') }}
                            </div>
                            <div style="font-size: 0.7rem; color: var(--text-tertiary);">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($createdAt)->format('H:i') }}
                            </div>
                            @else
                            <div style="font-size: 0.75rem; color: var(--text-tertiary);">
                                تعریف نشده
                            </div>
                            @endif
                        </td>
                        <td data-label="عملیات">
                            <div class="user-actions">
                                <a href="{{ route('admin.users.show', $user->id) }}" 
                                   class="user-action-btn details"
                                   title="جزئیات">
                                    <i class="fas fa-info-circle"></i>
                                    جزئیات
                                </a>
                                <a href="{{ route('profile.member.show', $user->id) }}" 
                                   class="user-action-btn view"
                                   target="_blank"
                                   title="مشاهده پروفایل">
                                    <i class="fas fa-user"></i>
                                    پروفایل
                                </a>
                                <a href="{{ route('admin.users.edit', $user->id) }}" 
                                   class="user-action-btn edit"
                                   title="ویرایش">
                                    <i class="fas fa-edit"></i>
                                    ویرایش
                                </a>
                                <form action="{{ route('admin.users.resetPassword', $user->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید رمز عبور این کاربر را بازنشانی کنید؟')">
                                    @csrf
                                    <button type="submit" class="user-action-btn reset-password" title="بازنشانی رمز عبور">
                                        <i class="fas fa-key"></i>
                                        رمز
                                    </button>
                                </form>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این کاربر را حذف کنید؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="user-action-btn delete" title="حذف">
                                        <i class="fas fa-trash"></i>
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/select/2.0.3/js/dataTables.select.min.js"></script>
<script>
    // Helper function to check if dark mode is active
    function isDarkMode() {
        return document.documentElement.classList.contains('dark-mode') || 
               document.body.classList.contains('dark-mode') ||
               document.documentElement.getAttribute('data-theme') === 'dark';
    }
    
    function getChartColors() {
        const dark = isDarkMode();
        return {
            textColor: dark ? '#f1f5f9' : '#1e293b',
            gridColor: dark ? '#475569' : '#e5e7eb',
            tickColor: dark ? '#cbd5e1' : '#4b5563',
        };
    }
    
    let registrationChart = null;
    let geographicChart = null;
    
    function initCharts() {
        const colors = getChartColors();
        
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: { family: 'Vazir, sans-serif' },
                        color: colors.textColor
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { family: 'Vazir, sans-serif' },
                        color: colors.tickColor
                    },
                    grid: {
                        color: colors.gridColor
                    }
                },
                x: {
                    ticks: {
                        font: { family: 'Vazir, sans-serif' },
                        color: colors.tickColor,
                        maxRotation: 45,
                        minRotation: 45
                    },
                    grid: {
                        color: colors.gridColor
                    }
                }
            }
        };
        
        // نمودار ثبت‌نام کاربران
        @if(isset($registrationChartData))
        const registrationCtx = document.getElementById('registrationChart');
        if (registrationCtx) {
            if (registrationChart) registrationChart.destroy();
            
            registrationChart = new Chart(registrationCtx, {
                type: 'line',
                data: {
                    labels: [@foreach($registrationChartData as $data)'{{ $data['date'] }}',@endforeach],
                    datasets: [{
                        label: 'تعداد ثبت‌نام',
                        data: [@foreach($registrationChartData as $data){{ $data['count'] }},@endforeach],
                        borderColor: isDarkMode() ? '#93a7fd' : '#667eea',
                        backgroundColor: isDarkMode() ? 'rgba(147, 167, 253, 0.1)' : 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: chartOptions
            });
        }
        @endif
        
        // نمودار توزیع جغرافیایی
        @if(isset($geographicDistribution))
        const geographicCtx = document.getElementById('geographicChart');
        if (geographicCtx) {
            if (geographicChart) geographicChart.destroy();
            
            geographicChart = new Chart(geographicCtx, {
                type: 'bar',
                data: {
                    labels: [@foreach($geographicDistribution as $province => $count)'{{ $province }}',@endforeach],
                    datasets: [{
                        label: 'تعداد کاربران',
                        data: [@foreach($geographicDistribution as $province => $count){{ $count }},@endforeach],
                        backgroundColor: isDarkMode() ? [
                            'rgba(147, 167, 253, 0.8)', 'rgba(52, 211, 153, 0.8)',
                            'rgba(96, 165, 250, 0.8)', 'rgba(167, 139, 250, 0.8)',
                            'rgba(244, 114, 182, 0.8)', 'rgba(251, 191, 36, 0.8)',
                            'rgba(248, 113, 113, 0.8)', 'rgba(74, 222, 128, 0.8)',
                            'rgba(192, 132, 252, 0.8)', 'rgba(253, 164, 90, 0.8)',
                        ] : [
                            'rgba(102, 126, 234, 0.8)', 'rgba(16, 185, 129, 0.8)',
                            'rgba(59, 130, 246, 0.8)', 'rgba(139, 92, 246, 0.8)',
                            'rgba(236, 72, 153, 0.8)', 'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)', 'rgba(34, 197, 94, 0.8)',
                            'rgba(168, 85, 247, 0.8)', 'rgba(249, 115, 22, 0.8)',
                        ],
                        borderWidth: 1
                    }]
                },
                options: { ...chartOptions, indexAxis: 'y' }
            });
        }
        @endif
    }
    
    function reloadCharts() {
        initCharts();
    }
    
    // Listen for theme changes from the header toggle button
    window.addEventListener('themeChanged', function(e) {
        console.log('Theme changed event received:', e.detail);
        reloadCharts();
    });
    
    $(document).ready(function() {
        // Initialize charts
        initCharts();
        
        const table = new DataTable('#usersTable', {
            language: {
                "decimal": "",
                "emptyTable": "هیچ داده‌ای در جدول وجود ندارد",
                "info": "نمایش _START_ تا _END_ از _TOTAL_ رکورد",
                "infoEmpty": "نمایش 0 تا 0 از 0 رکورد",
                "infoFiltered": "(فیلتر شده از مجموع _MAX_ رکورد)",
                "infoPostFix": "",
                "thousands": ",",
                "lengthMenu": "نمایش _MENU_ رکورد",
                "loadingRecords": "در حال بارگذاری...",
                "processing": "در حال پردازش...",
                "search": "جستجو:",
                "zeroRecords": "رکوردی یافت نشد",
                "paginate": {
                    "first": "اولین",
                    "last": "آخرین",
                    "next": "بعدی",
                    "previous": "قبلی"
                },
                "aria": {
                    "sortAscending": ": فعال‌سازی مرتب‌سازی صعودی",
                    "sortDescending": ": فعال‌سازی مرتب‌سازی نزولی"
                }
            },
            responsive: true,
            pageLength: 25,
            order: [[1, 'desc']],
            columnDefs: [
                { orderable: false, targets: [0, -1] } // Disable sorting on checkbox and actions columns
            ]
        });
    });
    
    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.user-checkbox');
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
        updateBulkActions();
    }
    
    function updateBulkActions() {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        const count = checkboxes.length;
        const bulkActions = document.getElementById('bulkActions');
        
        if (count > 0) {
            bulkActions.classList.add('active');
            document.getElementById('selectedCount').textContent = count + ' کاربر انتخاب شده';
        } else {
            bulkActions.classList.remove('active');
        }
    }
    
    function bulkAction(action) {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        const userIds = Array.from(checkboxes).map(cb => cb.value);
        
        if (userIds.length === 0) {
            alert('لطفا حداقل یک کاربر را انتخاب کنید');
            return;
        }
        
        if (action === 'delete' && !confirm('آیا مطمئن هستید که می‌خواهید ' + userIds.length + ' کاربر را حذف کنید؟')) {
            return;
        }
        
        if (action === 'export') {
            // Redirect to export with user IDs
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("admin.users.bulkAction") }}';
            
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export';
            form.appendChild(actionInput);
            
            userIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
            return;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("admin.users.bulkAction") }}';
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);
        
        userIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'user_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
</script>
@endpush
