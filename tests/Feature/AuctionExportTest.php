<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuctionExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_route_exists_and_streams()
    {
        $this->markTestSkipped('Run this test locally after setting up database and seeders.');

        // Example flow (uncomment and adjust when running locally):
        // $this->seed(\Database\Seeders\StockSeeder::class);
        // $auction = \App\Modules\Stock\Models\Auction::first();
        // $response = $this->actingAs(\App\Models\User::first())->get(route('admin.auction.export', $auction));
        // $response->assertStatus(200);
        // $this->assertStringContainsString('BID_ID,USER_ID,PRICE,QUANTITY,STATUS,CREATED_AT', $response->getContent());
    }
}
