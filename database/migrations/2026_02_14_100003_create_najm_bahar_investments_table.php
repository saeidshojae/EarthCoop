<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('najm_bahar_investments', function (Blueprint $table) {
            $table->id();
            
            // ارتباط با پروژه
            $table->unsignedBigInteger('project_id');
            
            // سرمایه‌گذار (polymorphic - می‌تواند کاربر یا گروه باشد)
            $table->morphs('investor'); // investor_id, investor_type
            
            // اطلاعات مالی
            $table->bigInteger('amount'); // مبلغ سرمایه‌گذاری (به گل)
            $table->decimal('agreed_profit_percentage', 5, 2)->nullable(); // درصد سود توافقی
            $table->bigInteger('expected_return')->nullable(); // بازگشت سرمایه پیش‌بینی شده
            
            // اطلاعات تراکنش
            $table->unsignedBigInteger('transaction_id')->nullable(); // ارتباط با تراکنش نجم بهار
            $table->string('transaction_tracking')->nullable(); // کد پیگیری
            
            // وضعیت سرمایه‌گذاری
            $table->enum('status', [
                'pending',          // در انتظار پرداخت
                'paid',             // پرداخت شده
                'active',           // فعال
                'completed',        // تکمیل شده (سود پرداخت شده)
                'cancelled',        // لغو شده
                'refunded'          // بازگشت داده شده
            ])->default('pending');
            
            // تاریخ‌ها
            $table->timestamp('invested_at')->nullable(); // تاریخ سرمایه‌گذاری
            $table->timestamp('maturity_date')->nullable(); // تاریخ سررسید
            $table->timestamp('completed_at')->nullable(); // تاریخ تکمیل
            
            // یادداشت‌ها
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // اطلاعات اضافی
            
            $table->timestamps();
            $table->softDeletes();

            // ایندکس‌ها
            $table->foreign('project_id')->references('id')->on('najm_bahar_projects')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('najm_transactions')->onDelete('set null');
            $table->index('status');
            $table->index(['project_id', 'status']);
            $table->index('invested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_investments');
    }
};
