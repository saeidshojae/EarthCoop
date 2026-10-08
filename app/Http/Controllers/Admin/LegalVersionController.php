<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\NajmBaharAgreement;
use App\Models\Term;
use App\Services\Legal\LegalDocumentDraftService;
use App\Services\Legal\LegalDocumentPublicationService;
use App\Services\Legal\LegalDocumentSourceSnapshotService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LegalVersionController extends Controller
{
    public function index()
    {
        return view('admin.legal-versions.index', [
            'documents' => LegalDocument::with(['versions' => fn ($query) => $query->orderByDesc('id')])->orderBy('slug')->get(),
            'termRoots' => Term::whereNull('parent_id')->orderBy('id')->get(['id', 'title']),
            'financialRoots' => NajmBaharAgreement::whereNull('parent_id')->orderBy('order')->orderBy('id')->get(['id', 'title']),
        ]);
    }

    public function createDraft(Request $request, LegalDocumentDraftService $drafts)
    {
        $data = $request->validate([
            'slug' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'source_type' => 'required|in:terms,najm_bahar_agreements',
            'source_root_id' => 'required|integer|min:1',
            'version_label' => 'required|string|max:40',
        ]);

        $drafts->createFromAdminSource(
            $data['slug'], $data['title'], $data['source_type'],
            (int) $data['source_root_id'], $data['version_label']
        );

        return redirect()->route('admin.legal-versions.index')
            ->with('success', 'پیش‌نویس نسخه ایجاد شد؛ مواد را در ویرایشگر فعلی بازبینی کنید.');
    }

    public function preview(LegalDocumentVersion $version, LegalDocumentSourceSnapshotService $snapshots)
    {
        $document = $version->document;
        $snapshot = $version->status === 'draft'
            ? $snapshots->capture($document->source_type, (int) $document->source_root_id)
            : $version->content_snapshot;

        abort_unless($snapshot !== null, 404);

        return view('admin.legal-versions.preview', [
            'version' => $version,
            'snapshot' => json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR),
        ]);
    }

    public function publish(
        Request $request,
        LegalDocumentVersion $version,
        LegalDocumentPublicationService $publications,
        LegalDocumentSourceSnapshotService $snapshots
    ) {
        $request->validate(['confirm_publish' => 'required|accepted']);
        $publications->publishFromAdminSource($version, (int) $request->user()->id, $snapshots);

        return redirect()->route('admin.legal-versions.index')
            ->with('success', 'نسخه منتشر شد و تصویر ثابت متن ثبت گردید.');
    }
}
