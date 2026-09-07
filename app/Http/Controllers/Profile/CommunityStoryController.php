<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\CommunityStory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityStoryController extends Controller
{
    public function index(Request $request): View
    {
        $stories = CommunityStory::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('profile.community-stories.index', compact('stories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:40', 'max:3000'],
            'show_name' => ['nullable', 'boolean'],
            'show_avatar' => ['nullable', 'boolean'],
            'role' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'consent_publication' => ['required', 'accepted'],
        ], [
            'consent_publication.required' => 'برای ارسال داستان باید رضایت انتشار عمومی را صریحاً تأیید کنید.',
            'consent_publication.accepted' => 'برای ارسال داستان باید رضایت انتشار عمومی را صریحاً تأیید کنید.',
        ]);

        $user = $request->user();
        $showName = $request->boolean('show_name');
        $showAvatar = $request->boolean('show_avatar') && filled($user->avatar);
        $fullName = trim((string) $user->fullName());

        CommunityStory::create([
            'user_id' => $user->id,
            'body' => trim($validated['body']),
            'display_name' => $showName && $fullName !== '' ? $fullName : null,
            'show_name' => $showName,
            'avatar_path' => $showAvatar ? 'images/users/avatars/' . $user->avatar : null,
            'show_avatar' => $showAvatar,
            'role' => isset($validated['role']) ? trim($validated['role']) : null,
            'location' => isset($validated['location']) ? trim($validated['location']) : null,
            'locale' => app()->getLocale(),
            'status' => CommunityStory::STATUS_PENDING,
            'consent_publication_at' => now(),
            'is_featured' => false,
            'published_at' => null,
        ]);

        return redirect()
            ->route('community-stories.index')
            ->with('success', 'داستان شما برای بررسی ارسال شد. تا پیش از تأیید و انتشار عمومی نمایش داده نمی‌شود.');
    }

    public function withdraw(Request $request, CommunityStory $communityStory): RedirectResponse
    {
        abort_unless($communityStory->user_id === $request->user()->id, 403);

        if ($communityStory->status !== CommunityStory::STATUS_WITHDRAWN) {
            $communityStory->forceFill([
                'status' => CommunityStory::STATUS_WITHDRAWN,
                'consent_publication_at' => null,
                'is_featured' => false,
                'withdrawn_at' => now(),
            ])->save();
        }

        return redirect()
            ->route('community-stories.index')
            ->with('success', 'رضایت انتشار این داستان پس گرفته شد و دیگر در بخش عمومی نمایش داده نمی‌شود.');
    }
}
