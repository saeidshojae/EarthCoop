<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_import_runs', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->index();
            $table->string('source');
            $table->string('dataset_version');
            $table->string('mode');
            $table->string('status')->index();
            $table->unsignedInteger('creates')->default(0);
            $table->unsignedInteger('updates')->default(0);
            $table->unsignedInteger('deactivates')->default(0);
            $table->unsignedInteger('conflicts')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->string('dataset_hash', 64)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['country_code', 'source', 'dataset_version'], 'location_import_run_identity_index');
        });

        Schema::create('location_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_import_run_id')->constrained('location_import_runs')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('action')->index();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
            $table->index(['location_import_run_id', 'external_id'], 'location_import_item_identity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_import_items');
        Schema::dropIfExists('location_import_runs');
    }
};
