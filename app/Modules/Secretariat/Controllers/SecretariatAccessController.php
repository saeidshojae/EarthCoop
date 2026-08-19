<?php

namespace App\Modules\Secretariat\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Secretariat\Models\SecretariatAclEntry;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecretariatAccessController extends Controller
{
    public function __construct(private readonly SecretariatAclService $acl)
    {
    }

    public function index(SecretariatOffice $office, SecretariatRecord $record)
    {
        $this->assertOfficeRecord($office, $record);
        $this->authorize('manageAcl', $record);

        $entries = $record->aclEntries()
            ->with(['grantedBy', 'revokedBy'])
            ->orderByDesc('id')
            ->get();

        return view('secretariat.access', [
            'office' => $office,
            'record' => $record,
            'entries' => $entries,
        ]);
    }

    public function grant(Request $request, SecretariatOffice $office, SecretariatRecord $record): RedirectResponse
    {
        $this->assertOfficeRecord($office, $record);
        $this->authorize('manageAcl', $record);

        $validated = $request->validate([
            'principal_type' => ['required', Rule::in(['user', 'group'])],
            'principal_id' => ['required', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $this->acl->grant(
            $record,
            $validated['principal_type'],
            (int) $validated['principal_id'],
            $request->user(),
            'view',
            $validated['expires_at'] ?? null,
            ['surface' => 'secretariat_acl_ui']
        );

        return back()->with('success', 'دسترسی صریح دبیرخانه ثبت شد.');
    }

    public function revoke(
        Request $request,
        SecretariatOffice $office,
        SecretariatRecord $record,
        SecretariatAclEntry $aclEntry,
    ): RedirectResponse {
        $this->assertOfficeRecord($office, $record);
        abort_unless((int) $aclEntry->record_id === (int) $record->id, 404);
        $this->authorize('manageAcl', $record);

        $this->acl->revoke($aclEntry, $request->user());

        return back()->with('success', 'دسترسی صریح لغو شد؛ سابقه آن حفظ شده است.');
    }

    private function assertOfficeRecord(SecretariatOffice $office, SecretariatRecord $record): void
    {
        abort_unless((int) $record->office_id === (int) $office->id, 404);
    }
}
