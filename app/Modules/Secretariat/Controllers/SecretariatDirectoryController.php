<?php

namespace App\Modules\Secretariat\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Http\Request;

class SecretariatDirectoryController extends Controller
{
    public function __construct(private readonly SecretariatOfficeService $offices)
    {
    }

    public function index(Request $request)
    {
        $offices = SecretariatOffice::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->filter(fn (SecretariatOffice $office): bool => $request->user()->can('view', $office))
            ->values();

        return view('secretariat.directory', [
            'offices' => $offices,
        ]);
    }

    public function central()
    {
        $office = SecretariatOffice::query()
            ->where('office_type', 'central')
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($office === null) {
            $probe = new SecretariatOffice([
                'code' => 'EARTHCOOP-CENTRAL',
                'name' => 'دبیرخانه مرکزی EarthCoop',
                'office_type' => 'central',
                'status' => 'active',
            ]);
            $this->authorize('manage', $probe);
            $office = $this->offices->ensureCentral();
        } else {
            $this->authorize('view', $office);
        }

        return redirect()->route('secretariat.index', $office);
    }

    public function group(Group $group)
    {
        $office = SecretariatOffice::query()
            ->where('office_type', 'group')
            ->where('scope_type', 'group')
            ->where('scope_id', $group->id)
            ->where('status', 'active')
            ->first();

        if ($office === null) {
            $probe = new SecretariatOffice([
                'code' => 'GROUP-' . $group->id,
                'name' => 'دبیرخانه ' . $group->name,
                'office_type' => 'group',
                'scope_type' => 'group',
                'scope_id' => $group->id,
                'status' => 'active',
            ]);

            // Provisioning a canonical group office is an idempotent infrastructure
            // operation, not a grant of management authority. Any active member who
            // is already entitled to view the office may trigger its lazy creation;
            // manage/inspect capabilities remain enforced by the office policies.
            $this->authorize('view', $probe);
            $office = $this->offices->ensureGroup($group);
        } else {
            $this->authorize('view', $office);
        }

        return redirect()->route('secretariat.index', $office);
    }
}
