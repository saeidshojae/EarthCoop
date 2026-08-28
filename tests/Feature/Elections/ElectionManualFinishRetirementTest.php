<?php

namespace Tests\Feature\Elections;

use App\Http\Controllers\Group\ElectionController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ElectionManualFinishRetirementTest extends TestCase
{
    public function test_manual_finish_adapter_cannot_mutate_the_election_lifecycle(): void
    {
        $route = Route::getRoutes()->getByName('finish.election');
        $this->assertNotNull($route);
        $this->assertSame(ElectionController::class.'@finishElection', $route->getActionName());

        $source = file_get_contents(app_path('Http/Controllers/Group/ElectionController.php'));
        $this->assertStringContainsString("'status' => 'retired'", $source);
        $this->assertStringContainsString('], 410)', $source);
        $this->assertStringNotContainsString('legacy_manual_finish_adapter', $source);
        $this->assertStringNotContainsString('$this->lifecycle->transition(', $source);
        $this->assertStringNotContainsString('$this->tally->tally(', $source);
        $this->assertStringNotContainsString("accept_status = 1", $source);
    }
}
