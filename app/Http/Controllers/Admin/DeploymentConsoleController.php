<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Deployment\DeploymentConsoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class DeploymentConsoleController extends Controller
{
    public function __construct(private DeploymentConsoleService $console)
    {
    }

    public function index(): View
    {
        $this->ensureEnabled();

        return view('admin.deployment-console.index', [
            'operations' => $this->console->operations(),
            'flags' => $this->console->flags(),
        ]);
    }

    public function run(Request $request, string $operation): RedirectResponse
    {
        $this->ensureEnabled();

        $operations = $this->console->operations();
        abort_unless(array_key_exists($operation, $operations), 404);

        $validated = $request->validate([
            'deployment_secret' => ['required', 'string'],
            'confirmation' => ['nullable', 'string'],
        ]);

        if (! $this->console->secretMatches($validated['deployment_secret'])) {
            throw ValidationException::withMessages([
                'deployment_secret' => 'کلید استقرار معتبر نیست.',
            ]);
        }

        $requiredConfirmation = $this->console->confirmationFor($operation);
        if ($requiredConfirmation !== null && ($validated['confirmation'] ?? '') !== $requiredConfirmation) {
            throw ValidationException::withMessages([
                'confirmation' => 'عبارت تأیید عملیات صحیح نیست.',
            ]);
        }

        $result = $this->console->run(
            $operation,
            (int) $request->user()->id,
            $request->ip()
        );

        return back()->with('deployment_console_result', $result);
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->console->isEnabled(), 404);
    }
}
