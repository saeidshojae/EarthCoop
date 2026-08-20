<?php

namespace Tests\Feature\NajmHoda;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FounderOperationsRoutesTest extends TestCase
{
    public function test_founder_operations_exposes_only_read_routes_in_initial_rollout(): void
    {
        foreach ([
            'admin.najm-hoda.founder-ops.index',
            'admin.najm-hoda.founder-ops.brief',
            'admin.najm-hoda.founder-ops.snapshot',
            'admin.najm-hoda.founder-ops.approvals',
            'admin.najm-hoda.founder-ops.authority',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains('GET', $route->methods());
            $this->assertNotContains('POST', $route->methods());
            $this->assertNotContains('PUT', $route->methods());
            $this->assertNotContains('PATCH', $route->methods());
            $this->assertNotContains('DELETE', $route->methods());
        }
    }
}
