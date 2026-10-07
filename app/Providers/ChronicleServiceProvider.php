<?php

namespace App\Providers;

use App\Http\Controllers\ChronicleController;
use App\Http\Controllers\Admin\ChronicleMilestoneController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ChronicleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')
            ->get('/chronicle', ChronicleController::class)
            ->name('chronicle.index');

        Route::middleware(['web', AdminMiddleware::class])
            ->prefix('admin/chronicle')
            ->name('admin.chronicle.')
            ->group(function (): void {
                Route::get('/milestones', [ChronicleMilestoneController::class, 'index'])->name('milestones.index');
                Route::post('/milestones', [ChronicleMilestoneController::class, 'store'])->name('milestones.store');
                Route::get('/milestones/{milestone}/edit', [ChronicleMilestoneController::class, 'edit'])->name('milestones.edit');
                Route::put('/milestones/{milestone}', [ChronicleMilestoneController::class, 'update'])->name('milestones.update');
                Route::delete('/milestones/{milestone}', [ChronicleMilestoneController::class, 'destroy'])->name('milestones.destroy');
            });
    }
}
