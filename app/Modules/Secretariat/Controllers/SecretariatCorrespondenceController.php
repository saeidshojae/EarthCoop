<?php

namespace App\Modules\Secretariat\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatAttachmentService;
use App\Modules\Secretariat\Services\SecretariatCorrespondenceService;
use App\Modules\Secretariat\Services\SecretariatDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SecretariatCorrespondenceController extends Controller
{
    private const CORRESPONDENCE_TYPES = ['incoming_letter', 'outgoing_letter', 'internal_correspondence'];

    public function __construct(
        private readonly SecretariatCorrespondenceService $correspondence,
        private readonly SecretariatDispatchService $dispatches,
        private readonly SecretariatAttachmentService $attachments,
        private readonly SecretariatAclService $acl,
    ) {
    }

    public function create(Request $request, SecretariatOffice $office)
    {
        $probe = new SecretariatRecord(['office_id' => $office->id, 'status' => 'draft']);
        $probe->setRelation('office', $office);
        $this->authorize('create', $probe);

        $direction = in_array($request->query('direction'), ['incoming', 'outgoing', 'internal'], true)
            ? (string) $request->query('direction')
            : 'incoming';
        $group = $this->officeGroup($office);

        return view('secretariat.correspondence.create', [
            'office' => $office,
            'direction' => $direction,
            'group' => $group,
            'members' => $this->groupMembers($group),
            'confidentialities' => ['public', 'office_members', 'leadership', 'restricted', 'confidential'],
            'channels' => ['internal', 'email', 'physical', 'api', 'other'],
        ]);
    }

    public function store(Request $request, SecretariatOffice $office): RedirectResponse
    {
        $probe = new SecretariatRecord(['office_id' => $office->id, 'status' => 'draft']);
        $probe->setRelation('office', $office);
        $this->authorize('create', $probe);

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['incoming', 'outgoing', 'internal'])],
            'title' => ['required', 'string', 'max:500'],
            'subject' => ['nullable', 'string', 'max:500'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'body' => ['nullable', 'string'],
            'confidentiality' => ['required', Rule::in(['public', 'office_members', 'leadership', 'restricted', 'confidential'])],
            'channel' => ['nullable', Rule::in(['internal', 'email', 'physical', 'api', 'other'])],
            'external_reference_number' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable', 'date'],
            'sent_at' => ['nullable', 'date'],
            'external_party_name' => ['nullable', 'string', 'max:255'],
            'external_party_email' => ['nullable', 'email', 'max:320'],
            'internal_recipient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ]);

        $group = $this->officeGroup($office);
        abort_if($group === null, 422, 'S4 correspondence UI currently requires a group-scoped Secretariat office.');

        $record = $this->correspondence->createDraft(
            $office,
            $request->user(),
            $validated['direction'],
            $validated,
            $this->partyPayload($validated, $group),
        );

        if (in_array($record->confidentiality, ['restricted', 'confidential'], true)) {
            $this->acl->grant($record, 'user', $request->user()->id, $request->user());
        }

        if ($request->hasFile('attachment')) {
            $this->attachments->upload($record, $request->user(), $request->file('attachment'));
        }

        return redirect()
            ->route('secretariat.correspondence.show', [$office, $record])
            ->with('success', 'پیش‌نویس مکاتبه دبیرخانه ایجاد شد.');
    }

    public function show(Request $request, SecretariatOffice $office, SecretariatRecord $record)
    {
        $this->assertOfficeRecord($office, $record);
        abort_unless(in_array($record->record_type, self::CORRESPONDENCE_TYPES, true), 404);
        $this->authorize('view', $record);

        $this->acl->auditSensitiveAccess($record, $request->user(), [
            'surface' => 'secretariat_correspondence_show',
        ]);

        $record->load([
            'currentVersion',
            'attachments',
            'parties.user',
            'parties.group',
            'correspondenceDetail',
            'dispatches.targetParty',
            'dispatches.targetUser',
            'outgoingRelations.targetRecord',
            'incomingRelations.sourceRecord',
        ]);

        return view('secretariat.correspondence.show', [
            'office' => $office,
            'record' => $record,
            'dispatchUsers' => $this->groupMembers($this->officeGroup($office)),
            'nextDispatchStatuses' => [
                'pending' => ['sent', 'cancelled'],
                'sent' => ['received', 'failed', 'cancelled'],
                'received' => ['acknowledged', 'completed'],
                'acknowledged' => ['completed'],
                'completed' => [],
                'failed' => [],
                'cancelled' => [],
            ],
        ]);
    }

    public function dispatch(Request $request, SecretariatOffice $office, SecretariatRecord $record): RedirectResponse
    {
        $this->assertOfficeRecord($office, $record);
        $this->authorize('transition', $record);

        $validated = $request->validate([
            'dispatch_type' => ['required', Rule::in(['referral', 'notification', 'delivery', 'return'])],
            'channel' => ['required', Rule::in(['internal', 'email', 'physical', 'api', 'other'])],
            'target_party_id' => ['nullable', 'integer', 'exists:secretariat_parties,id'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'external_reference_number' => ['nullable', 'string', 'max:255'],
        ]);

        $this->dispatches->create($record, $request->user(), $validated);

        return back()->with('success', 'گردش/ابلاغ سند ثبت شد.');
    }

    public function transitionDispatch(
        Request $request,
        SecretariatOffice $office,
        SecretariatRecord $record,
        SecretariatDispatch $dispatch,
    ): RedirectResponse {
        $this->assertOfficeRecord($office, $record);
        abort_unless((int) $dispatch->record_id === (int) $record->id, 404);
        $this->authorize('transition', $record);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['sent', 'received', 'acknowledged', 'completed', 'failed', 'cancelled'])],
        ]);

        $this->dispatches->transition($dispatch, $validated['status'], $request->user());

        return back()->with('success', 'وضعیت گردش سند به‌روزرسانی شد.');
    }

    /** @param array<string,mixed> $validated */
    private function partyPayload(array $validated, Group $group): array
    {
        $direction = $validated['direction'];
        $externalName = trim((string) ($validated['external_party_name'] ?? ''));

        if (in_array($direction, ['incoming', 'outgoing'], true) && $externalName === '') {
            abort(422, 'Incoming/outgoing correspondence requires the external party name.');
        }

        $groupParty = [
            'party_type' => 'group',
            'group_id' => $group->id,
            'display_name' => $group->name,
        ];
        $externalParty = [
            'party_type' => 'external',
            'display_name' => $externalName,
            'email' => $validated['external_party_email'] ?? null,
        ];

        if ($direction === 'incoming') {
            return [
                array_merge($externalParty, ['role' => 'sender']),
                array_merge($groupParty, ['role' => 'recipient']),
            ];
        }
        if ($direction === 'outgoing') {
            return [
                array_merge($groupParty, ['role' => 'sender']),
                array_merge($externalParty, ['role' => 'recipient']),
            ];
        }

        $recipientId = (int) ($validated['internal_recipient_user_id'] ?? 0);
        $member = $recipientId > 0 && DB::table('group_user')
            ->where('group_id', $group->id)
            ->where('user_id', $recipientId)
            ->exists();
        abort_unless($member, 422, 'Internal correspondence recipient must be a member of the office group.');
        $user = User::query()->findOrFail($recipientId);
        $displayName = trim((string) ($user->first_name ?? '') . ' ' . (string) ($user->last_name ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($user->email ?? ('User #' . $user->id));
        }

        return [
            array_merge($groupParty, ['role' => 'sender']),
            [
                'role' => 'recipient',
                'party_type' => 'user',
                'user_id' => $user->id,
                'display_name' => $displayName,
                'email' => $user->email,
            ],
        ];
    }

    private function groupMembers(?Group $group)
    {
        if ($group === null) {
            return collect();
        }

        return User::query()
            ->join('group_user', 'users.id', '=', 'group_user.user_id')
            ->where('group_user.group_id', $group->id)
            ->select('users.*')
            ->distinct()
            ->orderBy('users.id')
            ->limit(500)
            ->get();
    }

    private function officeGroup(SecretariatOffice $office): ?Group
    {
        return $office->scope_type === 'group' && $office->scope_id !== null
            ? Group::query()->find($office->scope_id)
            : null;
    }

    private function assertOfficeRecord(SecretariatOffice $office, SecretariatRecord $record): void
    {
        abort_unless((int) $record->office_id === (int) $office->id, 404);
    }
}
