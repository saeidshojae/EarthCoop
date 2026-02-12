<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Exports\NajmBaharTransactionsExport;
use App\Models\Candidate;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class NajmBaharReportController extends Controller
{
    /**
     * نمایش صفحه گزارش‌های مالی
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account) {
            return redirect()->route('najm-bahar.agreement')
                ->with('info', 'ابتدا باید حساب نجم بهار خود را ایجاد کنید.');
        }

        // فیلترها
        $dateFrom = $request->input('date_from', Carbon::now()->subMonths(3)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $type = $request->input('type', 'all'); // all, in, out
        $search = $request->input('search');

        // دریافت تراکنش‌ها
        $transactions = $this->getTransactions($account, $accountNumber, $dateFrom, $dateTo, $type, $search);

        // آمار خلاصه
        $summary = $this->getSummary($account, $accountNumber, $dateFrom, $dateTo);

        $routePrefix = 'najm-bahar.reports';
        $routeParams = [];
        $reportOwnerName = trim($user->first_name . ' ' . $user->last_name);
        $accountNumberDisplay = $account?->account_number;

        return view('najm-bahar.reports.index', compact('transactions', 'summary', 'dateFrom', 'dateTo', 'type', 'search', 'account', 'routePrefix', 'routeParams', 'reportOwnerName', 'accountNumberDisplay'));
    }

    public function indexForGroup(Request $request, Group $group)
    {
        $account = $this->getGroupAccountForMember($group);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به گزارش مالی گروه برای شما مجاز نیست.');
        }

        $dateFrom = $request->input('date_from', Carbon::now()->subMonths(3)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $type = $request->input('type', 'all');
        $search = $request->input('search');

        $transactions = $this->getTransactions($account, $account->account_number, $dateFrom, $dateTo, $type, $search);
        $summary = $this->getSummary($account, $account->account_number, $dateFrom, $dateTo);

        $routePrefix = 'groups.najm-bahar.reports';
        $routeParams = ['group' => $group->id];
        $reportOwnerName = 'گروه ' . $group->name;
        $accountNumberDisplay = $account->account_number;
        return view('najm-bahar.reports.index', compact('transactions', 'summary', 'dateFrom', 'dateTo', 'type', 'search', 'account', 'routePrefix', 'routeParams', 'reportOwnerName', 'accountNumberDisplay'));
    }

    public function indexForGroupLeadersList(Group $group)
    {
        $viewer = auth()->user();
        if (! $viewer || ! $this->isGroupMember($group, $viewer->id)) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به گزارش مالی مدیران و بازرسان برای شما مجاز نیست.');
        }

        $groupLeaders = $this->getGroupLeadersForReports($group);
        $groupId = $group->id;
        $reportOwnerName = 'گروه ' . $group->name;

        return view('najm-bahar.reports.leaders', compact('group', 'groupLeaders', 'groupId', 'reportOwnerName'));
    }

    public function indexForGroupLeader(Request $request, Group $group, User $leader)
    {
        $viewer = auth()->user();
        if (! $viewer || ! $this->isGroupMember($group, $viewer->id)) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به گزارش مالی مدیران و بازرسان برای شما مجاز نیست.');
        }

        $window = $this->getLeaderResponsibilityWindow($group, $leader->id);
        if (! $window) {
            return redirect()->route('groups.najm-bahar.reports', $group)
                ->with('error', 'فرد انتخاب‌شده مدیر یا بازرس این گروه نیست.');
        }

        if (Carbon::now()->greaterThan($window['allowed_to'])) {
            return redirect()->route('groups.najm-bahar.reports', $group)
                ->with('error', 'دسترسی به گزارش این منتخب به پایان رسیده است.');
        }

        [$dateFrom, $dateTo] = $this->clampDateRange(
            $request->input('date_from', Carbon::now()->subMonths(3)->format('Y-m-d')),
            $request->input('date_to', Carbon::now()->format('Y-m-d')),
            $window['allowed_from'],
            $window['allowed_to']
        );
        $type = $request->input('type', 'all');
        $search = $request->input('search');

        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($leader->id);
        $account = Account::where('account_number', $accountNumber)->first();

        $transactions = $this->getTransactions($account, $accountNumber, $dateFrom, $dateTo, $type, $search);
        $summary = $this->getSummary($account, $accountNumber, $dateFrom, $dateTo);

        $routePrefix = 'groups.najm-bahar.leader-reports';
        $routeParams = ['group' => $group->id, 'leader' => $leader->id];
        $leaderName = trim($leader->first_name . ' ' . $leader->last_name) ?: 'کاربر ' . $leader->id;
        $roleLabel = $this->resolveLeaderRoleLabel($window);
        $reportOwnerName = 'حساب شخصی ' . $leaderName . ' (' . $roleLabel . ')';
        $accountNumberDisplay = $account?->account_number ?? $accountNumber;

        return view('najm-bahar.reports.index', compact('transactions', 'summary', 'dateFrom', 'dateTo', 'type', 'search', 'account', 'routePrefix', 'routeParams', 'reportOwnerName', 'accountNumberDisplay'));
    }

    /**
     * دریافت تراکنش‌ها با فیلتر
     */
    private function getTransactions($account, $accountNumber, $dateFrom, $dateTo, $type, $search)
    {
        $query = Transaction::where(function($q) use ($account) {
            $q->where('from_account_id', $account->id)
              ->orWhere('to_account_id', $account->id);
        })
        ->whereBetween('created_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay()
        ])
        ->where('status', 'completed');

        // فیلتر نوع
        if ($type === 'in') {
            $query->where('to_account_id', $account->id);
        } elseif ($type === 'out') {
            $query->where('from_account_id', $account->id);
        }

        // جستجو در توضیحات
        if ($search) {
            $query->where('description', 'like', "%{$search}%");
        }

        return $query->orderBy('created_at', 'desc')->paginate(25);
    }

    /**
     * دریافت آمار خلاصه
     */
    private function getSummary($account, $accountNumber, $dateFrom, $dateTo)
    {
        $transactions = Transaction::where(function($q) use ($account) {
            $q->where('from_account_id', $account->id)
              ->orWhere('to_account_id', $account->id);
        })
        ->whereBetween('created_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay()
        ])
        ->where('status', 'completed')
        ->get();

        $totalIn = $transactions->where('to_account_id', $account->id)->sum('amount');
        $totalOut = $transactions->where('from_account_id', $account->id)->sum('amount');
        $count = $transactions->count();

        return [
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'net' => $totalIn - $totalOut,
            'count' => $count,
        ];
    }

    /**
     * Export به Excel
     */
    public function exportExcel(Request $request)
    {
        $user = auth()->user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account) {
            return redirect()->route('najm-bahar.agreement')
                ->with('info', 'ابتدا باید حساب نجم بهار خود را ایجاد کنید.');
        }

        // دریافت فیلترها
        $dateFrom = $request->input('date_from', Carbon::now()->subMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $type = $request->input('type', 'all');
        $search = $request->input('search');

        // دریافت تمام تراکنش‌ها (بدون pagination)
        $transactions = $this->getTransactionsForExport($account, $accountNumber, $dateFrom, $dateTo, $type, $search);

        $fileName = 'najm-bahar-transactions-' . Carbon::now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new NajmBaharTransactionsExport($transactions, $account), $fileName);
    }

    public function exportExcelForGroup(Request $request, Group $group)
    {
        $account = $this->getGroupAccountForMember($group);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به گزارش مالی گروه برای شما مجاز نیست.');
        }

        $dateFrom = $request->input('date_from', Carbon::now()->subMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $type = $request->input('type', 'all');
        $search = $request->input('search');

        $transactions = $this->getTransactionsForExport($account, $account->account_number, $dateFrom, $dateTo, $type, $search);

        $fileName = 'najm-bahar-group-' . $group->id . '-transactions-' . Carbon::now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new NajmBaharTransactionsExport($transactions, $account), $fileName);
    }

    /**
     * دریافت تراکنش‌ها برای Export (بدون pagination)
     */
    private function getTransactionsForExport($account, $accountNumber, $dateFrom, $dateTo, $type, $search)
    {
        $query = Transaction::where(function($q) use ($account) {
            $q->where('from_account_id', $account->id)
              ->orWhere('to_account_id', $account->id);
        })
        ->whereBetween('created_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay()
        ])
        ->where('status', 'completed');

        if ($type === 'in') {
            $query->where('to_account_id', $account->id);
        } elseif ($type === 'out') {
            $query->where('from_account_id', $account->id);
        }

        if ($search) {
            $query->where('description', 'like', "%{$search}%");
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Export به PDF
     */
    public function exportPdf(Request $request)
    {
        $user = auth()->user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account) {
            return redirect()->route('najm-bahar.agreement')
                ->with('info', 'ابتدا باید حساب نجم بهار خود را ایجاد کنید.');
        }

        // دریافت فیلترها
        $dateFrom = $request->input('date_from', Carbon::now()->subMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $type = $request->input('type', 'all');
        $search = $request->input('search');

        // دریافت تراکنش‌ها
        $transactions = $this->getTransactionsForExport($account, $accountNumber, $dateFrom, $dateTo, $type, $search);
        $summary = $this->getSummary($account, $accountNumber, $dateFrom, $dateTo);

        $reportOwnerName = trim($user->first_name . ' ' . $user->last_name);
        $accountNumberDisplay = $account?->account_number;

        // استفاده از view برای PDF
        $html = view('najm-bahar.reports.pdf', compact('transactions', 'summary', 'dateFrom', 'dateTo', 'user', 'reportOwnerName', 'accountNumberDisplay', 'account'))->render();

        // استفاده از DomPDF (اگر نصب باشد) یا بازگشت HTML
        // برای حال حاضر، HTML را برمی‌گردانیم
        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="najm-bahar-report-' . Carbon::now()->format('Y-m-d') . '.html"');
    }

    public function exportPdfForGroup(Request $request, Group $group)
    {
        $account = $this->getGroupAccountForMember($group);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به گزارش مالی گروه برای شما مجاز نیست.');
        }

        $dateFrom = $request->input('date_from', Carbon::now()->subMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $type = $request->input('type', 'all');
        $search = $request->input('search');

        $transactions = $this->getTransactionsForExport($account, $account->account_number, $dateFrom, $dateTo, $type, $search);
        $summary = $this->getSummary($account, $account->account_number, $dateFrom, $dateTo);

        $reportOwnerName = 'گروه ' . $group->name;
        $accountNumberDisplay = $account->account_number;

        $html = view('najm-bahar.reports.pdf', compact('transactions', 'summary', 'dateFrom', 'dateTo', 'reportOwnerName', 'accountNumberDisplay', 'account'))->render();

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="najm-bahar-group-' . $group->id . '-report-' . Carbon::now()->format('Y-m-d') . '.html"');
    }

    public function exportExcelForGroupLeader(Request $request, Group $group, User $leader)
    {
        $viewer = auth()->user();
        if (! $viewer || ! $this->isGroupMember($group, $viewer->id)) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به گزارش مالی مدیران و بازرسان برای شما مجاز نیست.');
        }

        $window = $this->getLeaderResponsibilityWindow($group, $leader->id);
        if (! $window) {
            return redirect()->route('groups.najm-bahar.reports', $group)
                ->with('error', 'فرد انتخاب‌شده مدیر یا بازرس این گروه نیست.');
        }

        if (Carbon::now()->greaterThan($window['allowed_to'])) {
            return redirect()->route('groups.najm-bahar.reports', $group)
                ->with('error', 'دسترسی به گزارش این منتخب به پایان رسیده است.');
        }

        [$dateFrom, $dateTo] = $this->clampDateRange(
            $request->input('date_from', Carbon::now()->subMonths(3)->format('Y-m-d')),
            $request->input('date_to', Carbon::now()->format('Y-m-d')),
            $window['allowed_from'],
            $window['allowed_to']
        );
        $type = $request->input('type', 'all');
        $search = $request->input('search');

        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($leader->id);
        $account = Account::where('account_number', $accountNumber)->first();

        $transactions = $this->getTransactionsForExport($account, $accountNumber, $dateFrom, $dateTo, $type, $search);

        $fileName = 'najm-bahar-group-' . $group->id . '-leader-' . $leader->id . '-transactions-' . Carbon::now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new NajmBaharTransactionsExport($transactions, $account), $fileName);
    }

    public function exportPdfForGroupLeader(Request $request, Group $group, User $leader)
    {
        $viewer = auth()->user();
        if (! $viewer || ! $this->isGroupMember($group, $viewer->id)) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به گزارش مالی مدیران و بازرسان برای شما مجاز نیست.');
        }

        $window = $this->getLeaderResponsibilityWindow($group, $leader->id);
        if (! $window) {
            return redirect()->route('groups.najm-bahar.reports', $group)
                ->with('error', 'فرد انتخاب‌شده مدیر یا بازرس این گروه نیست.');
        }

        if (Carbon::now()->greaterThan($window['allowed_to'])) {
            return redirect()->route('groups.najm-bahar.reports', $group)
                ->with('error', 'دسترسی به گزارش این منتخب به پایان رسیده است.');
        }

        [$dateFrom, $dateTo] = $this->clampDateRange(
            $request->input('date_from', Carbon::now()->subMonths(3)->format('Y-m-d')),
            $request->input('date_to', Carbon::now()->format('Y-m-d')),
            $window['allowed_from'],
            $window['allowed_to']
        );
        $type = $request->input('type', 'all');
        $search = $request->input('search');

        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($leader->id);
        $account = Account::where('account_number', $accountNumber)->first();

        $transactions = $this->getTransactionsForExport($account, $accountNumber, $dateFrom, $dateTo, $type, $search);
        $summary = $this->getSummary($account, $accountNumber, $dateFrom, $dateTo);

        $leaderName = trim($leader->first_name . ' ' . $leader->last_name) ?: 'کاربر ' . $leader->id;
        $roleLabel = $this->resolveLeaderRoleLabel($window);
        $reportOwnerName = 'حساب شخصی ' . $leaderName . ' (' . $roleLabel . ')';
        $accountNumberDisplay = $account?->account_number ?? $accountNumber;

        $html = view('najm-bahar.reports.pdf', compact('transactions', 'summary', 'dateFrom', 'dateTo', 'reportOwnerName', 'accountNumberDisplay', 'account'))->render();

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="najm-bahar-group-' . $group->id . '-leader-' . $leader->id . '-report-' . Carbon::now()->format('Y-m-d') . '.html"');
    }

    private function getGroupAccountForMember(Group $group): ?Account
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $isMember = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->exists();

        if (! $isMember) {
            return null;
        }

        return app(AccountService::class)->ensureLegalEntityAccountForGroup($group);
    }

    private function isGroupMember(Group $group, int $userId): bool
    {
        return GroupUser::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->exists();
    }

    private function getGroupLeadersForReports(Group $group)
    {
        $candidateUsers = Candidate::with('user', 'election')
            ->where('accept_status', 2)
            ->whereHas('election', fn($q) => $q->where('group_id', $group->id))
            ->orderByDesc('updated_at')
            ->get()
            ->unique('user_id');

        $currentLeaders = GroupUser::with('user')
            ->where('group_id', $group->id)
            ->where('status', 1)
            ->whereIn('role', [2, 3])
            ->get()
            ->unique('user_id');

        $leaderUserIds = $candidateUsers->pluck('user_id')
            ->merge($currentLeaders->pluck('user_id'))
            ->unique()
            ->values();

        $leaders = [];
        foreach ($leaderUserIds as $leaderUserId) {
            $window = $this->getLeaderResponsibilityWindow($group, $leaderUserId);
            if (! $window) {
                continue;
            }

            if (Carbon::now()->greaterThan($window['allowed_to'])) {
                continue;
            }

            $leaderUser = User::find($leaderUserId);
            $leaderName = $leaderUser
                ? trim($leaderUser->first_name . ' ' . $leaderUser->last_name)
                : '';

            $leaders[] = [
                'id' => $leaderUserId,
                'name' => $leaderName ?: 'کاربر ' . $leaderUserId,
                'role_label' => $this->resolveLeaderRoleLabel($window),
                'account_number' => AccountNumberService::makeMainAccountNumberForUser($leaderUserId),
            ];
        }

        return collect($leaders)->values();
    }

    private function getLeaderResponsibilityWindow(Group $group, int $userId): ?array
    {
        $groupUser = GroupUser::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->first();

        $candidate = Candidate::with('election')
            ->where('user_id', $userId)
            ->where('accept_status', 2)
            ->whereHas('election', fn($q) => $q->where('group_id', $group->id))
            ->orderByDesc('updated_at')
            ->first();

        if (! $candidate && ! ($groupUser && in_array((int) $groupUser->role, [2, 3], true))) {
            return null;
        }

        $isCurrentLeader = $groupUser && in_array((int) $groupUser->role, [2, 3], true);
        $startAt = $candidate
            ? ($candidate->updated_at ?? $candidate->created_at)
            : ($groupUser?->updated_at ?? $groupUser?->created_at ?? now());
        $endAt = $isCurrentLeader
            ? Carbon::now()
            : ($groupUser?->updated_at ?? ($candidate?->election?->ends_at ?? $startAt));

        return [
            'start_at' => Carbon::parse($startAt),
            'end_at' => Carbon::parse($endAt),
            'allowed_from' => Carbon::parse($startAt)->subMonths(3)->startOfDay(),
            'allowed_to' => Carbon::parse($endAt)->addMonths(3)->endOfDay(),
            'is_current' => $isCurrentLeader,
            'role' => $groupUser?->role,
            'position' => $candidate?->position ?? null,
        ];
    }

    private function resolveLeaderRoleLabel(array $window): string
    {
        if ($window['position'] === 'manager') {
            return 'مدیر';
        }

        if ($window['position'] === 'inspector') {
            return 'بازرس';
        }

        return (int) ($window['role'] ?? 2) === 3 ? 'مدیر' : 'بازرس';
    }

    private function clampDateRange(string $dateFrom, string $dateTo, Carbon $allowedFrom, Carbon $allowedTo): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        if ($from->lessThan($allowedFrom)) {
            $from = $allowedFrom->copy();
        }

        if ($to->greaterThan($allowedTo)) {
            $to = $allowedTo->copy();
        }

        if ($from->greaterThan($to)) {
            $from = $allowedFrom->copy();
            $to = $allowedTo->copy();
        }

        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }
}

