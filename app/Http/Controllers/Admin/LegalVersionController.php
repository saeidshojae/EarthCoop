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

    public function importPreview(string $slug, \App\Services\Legal\LegalMarkdownImportService $imports)
    {
        return view('admin.legal-versions.import-preview', ['data' => $imports->preview($slug)]);
    }

    public function import(Request $request, string $slug, \App\Services\Legal\LegalMarkdownImportService $imports)
    {
        $request->validate(['confirm_import' => 'required|accepted']);
        $rootId = \Illuminate\Support\Facades\DB::transaction(function () use ($slug, $imports): int {
            if (LegalDocument::where('slug', $slug)->exists()) {
                throw ValidationException::withMessages(['document' => 'این سند قبلاً برای نسخه‌بندی ثبت شده است.']);
            }
            $rootId = $imports->importToNewRoot($slug);
            $preview = $imports->preview($slug);
            LegalDocument::create([
                'slug' => $slug,
                'title' => $preview['title'],
                'source_type' => $preview['source_type'],
                'source_root_id' => $rootId,
                'is_staged_import' => true,
            ]);
            return $rootId;
        });
        return redirect()->route('admin.legal-versions.index')
            ->with('success', 'متن به‌صورت والد و فرزندان مستقل وارد شد (ریشه شماره ' . $rootId . '). اکنون آن را بازبینی و نسخه‌گذاری کنید.');
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
            'previewSha256' => hash('sha256', $snapshot),
        ]);
    }

    public function publish(
        Request $request,
        LegalDocumentVersion $version,
        LegalDocumentPublicationService $publications,
        LegalDocumentSourceSnapshotService $snapshots
    ) {
        $data = $request->validate([
            'confirm_publish' => 'required|accepted',
            'preview_sha256' => 'required|regex:/^[a-f0-9]{64}$/',
        ]);
        if ($version->status !== 'draft') {
            throw ValidationException::withMessages(['version' => 'فقط پیش‌نویس قابل انتشار است.']);
        }
        $document = $version->document()->firstOrFail();
        $snapshot = $snapshots->capture($document->source_type, (int) $document->source_root_id);
        if (! hash_equals($data['preview_sha256'], hash('sha256', $snapshot))) {
            throw ValidationException::withMessages(['content' => 'متن سند پس از پیش‌نمایش تغییر کرده است؛ لطفاً نسخه جدید را بازبینی کنید.']);
        }
        $publications->publish($version, $snapshot, (int) $request->user()->id);

        return redirect()->route('admin.legal-versions.index')
            ->with('success', 'نسخه منتشر شد و تصویر ثابت متن ثبت گردید.');
    }
}
