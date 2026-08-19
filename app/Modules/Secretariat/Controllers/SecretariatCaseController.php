<?php

namespace App\Modules\Secretariat\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SecretariatCaseController extends Controller
{
    private const CONFIDENTIALITIES = ['public', 'office_members', 'leadership', 'restricted', 'confidential'];
    private const ROLES = ['related', 'evidence', 'decision', 'correspondence', 'report', 'contract', 'other'];

    public function __construct(private readonly SecretariatCaseService $cases)
    {
    }

    public function index(Request $request, SecretariatOffice $office)
    {
        $this->authorize('view', $office);

        $cases = SecretariatCase::query()
            ->where('office_id', $office->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(fn (SecretariatCase $case): bool => $request->user()->can('view', $case))
            ->values();

        return view('secretariat.cases.index', compact('office', 'cases'));
    }

    public function create(Request $request, SecretariatOffice $office)
    {
        $probe = new SecretariatCase([
            'office_id' => $office->id,
            'status' => 'open',
            'confidentiality' => 'office_members',
        ]);
        $probe->setRelation('office', $office);
        $this->authorize('create', $probe);

        return view('secretariat.cases.create', [
            'office' => $office,
            'confidentialities' => $this->allowedConfidentialities($request),
        ]);
    }

    public function store(Request $request, SecretariatOffice $office): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'confidentiality' => ['required', Rule::in($this->allowedConfidentialities($request))],
        ]);

        $probe = new SecretariatCase([
            'office_id' => $office->id,
            'status' => 'open',
            'confidentiality' => $validated['confidentiality'],
        ]);
        $probe->setRelation('office', $office);
        $this->authorize('create', $probe);

        $case = $this->cases->create($office, $request->user(), $validated);

        return redirect()
            ->route('secretariat.cases.show', [$office, $case])
            ->with('success', 'پرونده دبیرخانه ایجاد شد.');
    }

    public function show(Request $request, SecretariatOffice $office, SecretariatCase $case)
    {
        $this->assertOfficeCase($office, $case);
        $this->authorize('view', $case);

        $case->load(['office', 'createdBy', 'closedBy', 'records.office']);

        // A Case may aggregate records with stricter confidentiality than the Case
        // itself. Never leak even titles/registry metadata for a record that fails
        // its own RecordPolicy.
        $visibleRecords = $case->records
            ->filter(fn (SecretariatRecord $record): bool => Gate::forUser($request->user())->allows('view', $record))
            ->values();

        $linkableRecords = collect();
        $referenceOffices = collect();
        if ($request->user()->can('manage', $case) && $case->status !== 'archived') {
            $memberIds = $case->records->pluck('id');
            $linkableRecords = SecretariatRecord::query()
                ->where('office_id', $office->id)
                ->whereNotNull('registry_number')
                ->when($memberIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $memberIds))
                ->orderByDesc('registered_at')
                ->limit(200)
                ->get()
                ->filter(fn (SecretariatRecord $record): bool => Gate::forUser($request->user())->allows('view', $record))
                ->values();

            // Only offices the actor can actually enter are offered as a source
            // namespace. Exact record lookup still re-runs RecordPolicy below.
            $referenceOffices = SecretariatOffice::query()
                ->where('status', 'active')
                ->whereKeyNot($office->id)
                ->orderBy('name')
                ->limit(200)
                ->get()
                ->filter(fn (SecretariatOffice $candidate): bool => Gate::forUser($request->user())->allows('view', $candidate))
                ->values();
        }

        return view('secretariat.cases.show', [
            'office' => $office,
            'case' => $case,
            'visibleRecords' => $visibleRecords,
            'linkableRecords' => $linkableRecords,
            'referenceOffices' => $referenceOffices,
            'roles' => self::ROLES,
        ]);
    }

    public function addRecord(Request $request, SecretariatOffice $office, SecretariatCase $case): RedirectResponse
    {
        $this->assertOfficeCase($office, $case);
        $this->authorize('manage', $case);

        $validated = $request->validate([
            'record_id' => ['required', 'integer', 'exists:secretariat_records,id'],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        $record = SecretariatRecord::query()->findOrFail($validated['record_id']);
        abort_unless((int) $record->office_id === (int) $office->id, 422);
        $this->authorize('view', $record);

        $this->cases->addRecord($case, $record, $request->user(), $validated['role']);

        return back()->with('success', 'سند رسمی به پرونده افزوده شد.');
    }

    public function addCrossOfficeReference(Request $request, SecretariatOffice $office, SecretariatCase $case): RedirectResponse
    {
        $this->assertOfficeCase($office, $case);
        $this->authorize('manage', $case);

        $validated = $request->validate([
            'source_office_id' => ['required', 'integer', 'exists:secretariat_offices,id'],
            'registry_number' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        abort_if((int) $validated['source_office_id'] === (int) $office->id, 422, 'Use local case membership for records from this office.');

        $sourceOffice = SecretariatOffice::query()->findOrFail($validated['source_office_id']);
        $this->authorize('view', $sourceOffice);

        $record = SecretariatRecord::query()
            ->where('office_id', $sourceOffice->id)
            ->where('registry_number', trim($validated['registry_number']))
            ->whereNotNull('registry_number')
            ->firstOrFail();
        $this->authorize('view', $record);

        $this->cases->addCrossOfficeReference($case, $record, $request->user(), $validated['role']);

        return back()->with('success', 'ارجاع بین‌دفتری بدون کپی سند به پرونده افزوده شد.');
    }

    public function transition(Request $request, SecretariatOffice $office, SecretariatCase $case): RedirectResponse
    {
        $this->assertOfficeCase($office, $case);
        $this->authorize('manage', $case);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'on_hold', 'closed', 'archived'])],
        ]);

        $this->cases->transition($case, $validated['status'], $request->user());

        return back()->with('success', 'وضعیت پرونده به‌روزرسانی شد.');
    }

    /** @return array<int,string> */
    private function allowedConfidentialities(Request $request): array
    {
        $user = $request->user();
        $isAdmin = (bool) ($user->is_admin ?? false)
            || (method_exists($user, 'hasRole') && $user->hasRole('super-admin'));

        return $isAdmin
            ? self::CONFIDENTIALITIES
            : ['public', 'office_members', 'leadership'];
    }

    private function assertOfficeCase(SecretariatOffice $office, SecretariatCase $case): void
    {
        abort_unless((int) $case->office_id === (int) $office->id, 404);
    }
}
