<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemEmail;
use Illuminate\Http\Request;

class SystemEmailController extends Controller
{
    /**
     * SystemEmail is retained as a compatibility/import source only. Admin
     * presentation identities are managed exclusively by Communication Center.
     */
    public function index()
    {
        return $this->redirectToCanonicalSenders();
    }

    public function create()
    {
        return $this->redirectToCanonicalSenders();
    }

    public function store(Request $request)
    {
        return $this->redirectToCanonicalSenders();
    }

    public function edit(SystemEmail $systemEmail)
    {
        return $this->redirectToCanonicalSenders();
    }

    public function update(Request $request, SystemEmail $systemEmail)
    {
        return $this->redirectToCanonicalSenders();
    }

    public function destroy(SystemEmail $systemEmail)
    {
        return $this->redirectToCanonicalSenders();
    }

    public function setDefault(SystemEmail $systemEmail)
    {
        return $this->redirectToCanonicalSenders();
    }

    private function redirectToCanonicalSenders()
    {
        return redirect()
            ->route('admin.communications.senders.index')
            ->with('info', 'هویت‌های فرستنده از این پس در مرکز ارتباطات مدیریت می‌شوند.');
    }
}
