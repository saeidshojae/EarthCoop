<?php

namespace App\Modules\Secretariat\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Secretariat\Models\SecretariatOffice;
use Illuminate\Http\Request;

class SecretariatDirectoryController extends Controller
{
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
}
