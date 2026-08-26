<?php

namespace App\Modules\Secretariat\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatAttachment;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatAttachmentService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Modules\Secretariat\Services\SecretariatRelationService;
use App\Modules\Secretariat\Services\SecretariatSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecretariatController extends Controller
{
    private const RECORD_TYPES = [
        'incoming_letter', 'outgoing_letter', 'internal_correspondence',
        'meeting_minute', 'resolution', 'formal_decision', 'contract',
        'memorandum_of_understanding', 'agreement', 'policy', 'directive',
        'official_report', 'notice', 'official_note', 'financial_record',
        'execution_record', 'election_record', 'case_record', 'other',
    ];

    private const DIRECTIONS = ['incoming', 'outgoing', 'internal', 'none'];
    private const CONFIDENTIALITIES = ['public', 'office_members', 'leadership', 'restricted', 'confidential'];
    private const RELATION_TYPES = [
        'derived_from', 'refers_to', 'supersedes', 'amends', 'implements',
        'responds_to', 'decision_of', 'action_of', 'report_of', 'part_of_case', 'related_to',
    ];

    public function __construct(
        private readonly SecretariatRecordService $records,
        private readonly SecretariatAttachmentService $attachments,
        private readonly SecretariatRelationService $relations,
        private readonly SecretariatAclService $acl,
        private readonly SecretariatSearchService $search,
    ) {
    }

    public function index(Request $request, SecretariatOffice $office)
    {
        $this->authorize('view', $office);

        $filters = $request->only(['registry_number', 'record_type', 'status', 'title', 'date_from', 'date_to']);
        $filters['office_id'] = $office->id;

        $records = $this->search->search($request->user(), $filters, 100);
        $visibleRecords = $this->search->search($request->user(), ['office_id' => $office->id], 200);

        $counts = [
            'draft' => $visibleRecords->where('status', 'draft')->count(),
            'pending_approval' => $visibleRecords->where('status', 'pending_approval')->count(),
            'registered' => $visibleRecords->whereIn('status', ['registered', 'active', 'closed', 'archived'])->count(),
        ];

        $visibleCases = SecretariatCase::query()
            ->with('office')
            ->where('office_id', $office->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(fn (SecretariatCase $case): bool => $request->user()->can('view', $case))
            ->values();

        $dashboard = [
            'visible_records' => $visibleRecords->count(),
            'correspondence' => $visibleRecords->whereIn('record_type', [
                'incoming_letter', 'outgoing_letter', 'internal_correspondence',
            ])->count(),
            'open_cases' => $visibleCases->whereNotIn('status', ['closed', 'archived', 'cancelled'])->count(),
        ];

        return view('secretariat.index', [
            'office' => $office,
            'records' => $records,
            'filters' => $filters,
            'counts' => $counts,
            'dashboard' => $dashboard,
            'recentRecords' => $visibleRecords->take(8),
            'recentCases' => $visibleCases->take(6),
            'canManageOffice' => $request->user()->can('manage', $office),
            'canInspectOffice' => $request->user()->can('inspect', $office),
            'recordTypes' => self::RECORD_TYPES,
        ]);
    }

    public function create(SecretariatOffice $office)
    {
        $probe = new SecretariatRecord(['office_id' => $office->id, 'status' => 'draft']);
        $probe->setRelation('office', $office);
        $this->authorize('create', $probe);

        return view('secretariat.create', [
            'office' => $office,
            'recordTypes' => self::RECORD_TYPES,
            'directions' => self::DIRECTIONS,
            'confidentialities' => self::CONFIDENTIALITIES,
        ]);
    }

    public function store(Request $request, SecretariatOffice $office): RedirectResponse
    {
        $probe = new SecretariatRecord(['office_id' => $office->id, 'status' => 'draft']);
        $probe->setRelation('office', $office);
        $this->authorize('create', $probe);

        $validated = $request->validate([
            'record_type' => ['required', Rule::in(self::RECORD_TYPES)],
            'direction' => ['required', Rule::in(self::DIRECTIONS)],
            'title' => ['required', 'string', 'max:500'],
            'subject' => ['nullable', 'string', 'max:1000'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'body' => ['nullable', 'string'],
            'confidentiality' => ['required', Rule::in(self::CONFIDENTIALITIES)],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ]);

        $record = $this->records->createDraft($office, $request->user(), $validated);

        if (in_array($record->confidentiality, ['restricted', 'confidential'], true)) {
            $this->acl->grant($record, 'user', $request->user()->id, $request->user());
        }

        if ($request->hasFile('attachment')) {
            $this->attachments->upload($record, $request->user(), $request->file('attachment'));
        }

        return redirect()
            ->route('secretariat.records.show', [$office, $record])
            ->with('success', 'پیش‌نویس دبیرخانه ایجاد شد.');
    }

    public function show(Request $request, SecretariatOffice $office, SecretariatRecord $record)
    {
        $this->assertOfficeRecord($office, $record);
        $this->authorize('view', $record);

        $this->acl->auditSensitiveAccess($record, $request->user(), [
            'surface' => 'secretariat_record_show',
        ]);

        $record->load([
            'office',
            'versions.attachments',
            'currentVersion',
            'attachments',
            'parties.user',
            'parties.group',
            'correspondenceDetail',
            'dispatches.targetParty',
            'dispatches.targetUser',
            'outgoingRelations.targetRecord',
            'incomingRelations.sourceRecord',
            'auditEvents.actor',
        ]);

        $linkableRecords = $this->search->search($request->user(), [
            'office_id' => $office->id,
        ], 100)->reject(fn (SecretariatRecord $candidate) => $candidate->is($record));

        $dispatchUsers = collect();
        if ($office->scope_type === 'group' && $office->scope_id !== null) {
            $memberIds = DB::table('group_user')
                ->where('group_id', $office->scope_id)
                ->limit(500)
                ->pluck('user_id');
            $dispatchUsers = User::query()->whereIn('id', $memberIds)->orderBy('id')->get();
        }

        return view('secretariat.show', [
            'office' => $office,
            'record' => $record,
            'relationTypes' => self::RELATION_TYPES,
            'linkableRecords' => $linkableRecords,
            'dispatchUsers' => $dispatchUsers,
        ]);
    }

    public function submit(Request $request, SecretariatOffice $office, SecretariatRecord $record): RedirectResponse
    {
        $this->assertOfficeRecord($office, $record);
        $this->authorize('submitForApproval', $record);
        $this->records->submitForApproval($record, $request->user());

        return back()->with('success', 'سند برای تأیید ارسال شد.');
    }

    public function register(Request $request, SecretariatOffice $office, SecretariatRecord $record): RedirectResponse
    {
        $this->assertOfficeRecord($office, $record);
        $this->authorize('register', $record);
        $this->records->register($record, $request->user());

        return back()->with('success', 'سند با شماره ثبت رسمی ثبت شد.');
    }

    public function upload(Request $request, SecretariatOffice $office, SecretariatRecord $record): RedirectResponse
    {
        $this->assertOfficeRecord($office, $record);
        $this->authorize('update', $record);

        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:20480'],
        ]);

        $this->attachments->upload($record, $request->user(), $validated['attachment']);

        return back()->with('success', 'پیوست ثبت شد.');
    }

    public function download(
        Request $request,
        SecretariatOffice $office,
        SecretariatRecord $record,
        SecretariatAttachment $attachment,
    ): StreamedResponse {
        $this->assertOfficeRecord($office, $record);
        abort_unless((int) $attachment->record_id === (int) $record->id, 404);
        $this->authorize('view', $record);

        $this->acl->auditSensitiveAccess($record, $request->user(), [
            'surface' => 'secretariat_attachment_download',
            'attachment_id' => $attachment->id,
        ]);

        abort_unless(Storage::disk($attachment->storage_disk)->exists($attachment->storage_key), 404);

        return Storage::disk($attachment->storage_disk)->download(
            $attachment->storage_key,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']
        );
    }

    public function addRelation(Request $request, SecretariatOffice $office, SecretariatRecord $record): RedirectResponse
    {
        $this->assertOfficeRecord($office, $record);
        $this->authorize('transition', $record);

        $validated = $request->validate([
            'target_record_id' => ['required', 'integer', 'exists:secretariat_records,id'],
            'relation_type' => ['required', Rule::in(self::RELATION_TYPES)],
        ]);

        $target = SecretariatRecord::query()->findOrFail($validated['target_record_id']);
        abort_unless((int) $target->office_id === (int) $office->id, 422);
        $this->authorize('view', $target);

        $this->relations->add($record, $target, $validated['relation_type'], $request->user());

        return back()->with('success', 'رابطه ثبتی افزوده شد.');
    }

    private function assertOfficeRecord(SecretariatOffice $office, SecretariatRecord $record): void
    {
        abort_unless((int) $record->office_id === (int) $office->id, 404);
    }
}
