<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Services\InvitationLifecycleService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class MemberInvitationController extends Controller
{
    public function __invoke(InvitationLifecycleService $invitations): RedirectResponse
    {
        $user = auth()->user();

        if (! $user || ! $invitations->canIssueMemberInvitation($user)) {
            return back()->with('error', 'شما در حال حاضر امکان ساخت کد دعوت جدید را ندارید.');
        }

        try {
            $code = $invitations->issueMemberInvitation($user);
        } catch (RuntimeException $e) {
            return back()->with('error', 'سهمیه کد دعوت شما تکمیل شده یا امکان صدور کد جدید وجود ندارد.');
        }

        return back()->with('success', 'کد دعوت جدید با موفقیت ساخته شد: ' . $code->code);
    }
}
