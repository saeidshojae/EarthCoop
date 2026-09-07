<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElectionResponsibilityContractVersion;
use App\Services\Elections\ElectionResponsibilityContractVersionService;
use Illuminate\Http\Request;

class ElectionResponsibilityContractAdminController extends Controller
{
    public function index()
    {
        $contracts = ElectionResponsibilityContractVersion::query()->orderBy('position')->orderByDesc('version')->get();
        return view('admin.system-settings.elections.contracts', compact('contracts'));
    }

    public function store(Request $request, ElectionResponsibilityContractVersionService $service)
    {
        $rules = ['position'=>'required|in:manager,inspector','change_reason'=>'required|string|max:500'];
        foreach (ElectionResponsibilityContractVersion::REQUIRED_CLAUSES as $key) $rules[$key] = 'required|string|max:10000';
        $validated = $request->validate($rules);
        $clauses = collect($validated)->only(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES)->all();
        $contract = $service->publish($validated['position'], $clauses, $request->user(), $validated['change_reason']);
        return back()->with('success', "نسخه {$contract->version} قرارداد منتشر شد.");
    }
}
