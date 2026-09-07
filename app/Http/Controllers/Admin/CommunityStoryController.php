<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityStory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityStoryController extends Controller
{
    public function index(): View
    {
        $stories = CommunityStory::query()
            ->with(['user', 'reviewer'])
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected', 'withdrawn')")
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.community-stories.index', compact('stories'));
    }

    public function approve(Request $request, CommunityStory $communityStory): RedirectResponse
    {
        if (! $this->hasActivePublicationConsent($communityStory)) {
            return $this->consentError();
        }

        $communityStory->forceFill([
            'status' => CommunityStory::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => null,
            'published_at' => now(),
            'withdrawn_at' => null,
        ])->save();

        return redirect()->route('admin.community-stories.index')
            ->with('success', 'داستان تأیید و برای انتشار آماده شد.');
    }

    public function reject(Request $request, CommunityStory $communityStory): RedirectResponse
    {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $communityStory->forceFill([
            'status' => CommunityStory::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
            'is_featured' => false,
            'published_at' => null,
        ])->save();

        return redirect()->route('admin.community-stories.index')
            ->with('success', 'داستان رد شد و از انتشار عمومی خارج شد.');
    }

    public function feature(CommunityStory $communityStory): RedirectResponse
    {
        if (! $this->hasActivePublicationConsent($communityStory)
            || $communityStory->status !== CommunityStory::STATUS_APPROVED
            || $communityStory->published_at === null) {
            return $this->consentError();
        }

        $communityStory->forceFill(['is_featured' => true])->save();

        return redirect()->route('admin.community-stories.index')
            ->with('success', 'داستان برای نمایش در صفحه خوش‌آمد انتخاب شد.');
    }

    public function unfeature(CommunityStory $communityStory): RedirectResponse
    {
        $communityStory->forceFill(['is_featured' => false])->save();

        return redirect()->route('admin.community-stories.index')
            ->with('success', 'داستان از بخش منتخب صفحه خوش‌آمد خارج شد.');
    }

    private function hasActivePublicationConsent(CommunityStory $story): bool
    {
        return $story->consent_publication_at !== null && $story->withdrawn_at === null;
    }

    private function consentError(): RedirectResponse
    {
        return redirect()->route('admin.community-stories.index')
            ->withErrors(['story' => 'این داستان رضایت فعال برای انتشار عمومی ندارد.']);
    }
}
