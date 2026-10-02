<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Enums\Communication\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\CommunicationRecipient;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DeliveryHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = array_map(
            static fn (DeliveryStatus $status): string => $status->value,
            DeliveryStatus::cases(),
        );

        $status = trim((string) $request->query('status', ''));
        $email = trim((string) $request->query('email', ''));

        $recipients = CommunicationRecipient::query()
            ->with(['communication', 'templateVersion.template'])
            ->when(in_array($status, $statuses, true), fn ($query) => $query->where('status', $status))
            ->when($email !== '', fn ($query) => $query->where('email', 'like', '%'.$email.'%'))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.communications.history', compact('recipients', 'statuses', 'status', 'email'));
    }
}
