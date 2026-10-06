<?php

namespace App\Http\Controllers\Admin;

use App\Chronicle\ChronicleMilestone;
use App\Http\Controllers\Controller;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ChronicleMilestoneController extends Controller
{
    public function index(): View
    {
        $milestones = ChronicleMilestone::query()
            ->orderByDesc('occurred_on')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return view('admin.chronicle.milestones.index', compact('milestones'));
    }

    public function store(
        Request $request,
        TemporalService $temporal,
        TemporalContextResolver $contexts,
    ): RedirectResponse {
        $data = $this->validatedPayload($request, $temporal, $contexts);

        ChronicleMilestone::query()->create([
            ...$data,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.chronicle.milestones.index')
            ->with('success', 'رویداد گاه‌شمار ثبت شد.');
    }

    public function edit(ChronicleMilestone $milestone): View
    {
        return view('admin.chronicle.milestones.edit', compact('milestone'));
    }

    public function update(
        Request $request,
        ChronicleMilestone $milestone,
        TemporalService $temporal,
        TemporalContextResolver $contexts,
    ): RedirectResponse {
        $data = $this->validatedPayload($request, $temporal, $contexts);
        $data['updated_by'] = $request->user()?->id;

        $milestone->update($data);

        return redirect()
            ->route('admin.chronicle.milestones.index')
            ->with('success', 'رویداد گاه‌شمار به‌روزرسانی شد.');
    }

    public function destroy(ChronicleMilestone $milestone): RedirectResponse
    {
        $milestone->delete();

        return redirect()
            ->route('admin.chronicle.milestones.index')
            ->with('success', 'رویداد گاه‌شمار حذف شد.');
    }

    private function validatedPayload(
        Request $request,
        TemporalService $temporal,
        TemporalContextResolver $contexts,
    ): array {
        $validated = $request->validate([
            'occurred_on' => ['required', 'string', 'max:40'],
            'title_fa' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'description_fa' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        try {
            $occurredOn = $temporal
                ->parseDate((string) $validated['occurred_on'], $contexts->forUser($request->user()))
                ->toCanonical();
        } catch (\InvalidArgumentException|\ValueError) {
            throw ValidationException::withMessages([
                'occurred_on' => 'تاریخ رویداد معتبر نیست.',
            ]);
        }

        return [
            'occurred_on' => $occurredOn,
            'title_translations' => [
                'fa' => trim((string) $validated['title_fa']),
                'en' => $this->nullableText($validated['title_en'] ?? null),
                'ar' => $this->nullableText($validated['title_ar'] ?? null),
            ],
            'description_translations' => [
                'fa' => $this->nullableText($validated['description_fa'] ?? null),
                'en' => $this->nullableText($validated['description_en'] ?? null),
                'ar' => $this->nullableText($validated['description_ar'] ?? null),
            ],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_published' => $request->boolean('is_published'),
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
