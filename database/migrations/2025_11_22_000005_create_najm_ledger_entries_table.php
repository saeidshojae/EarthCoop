<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('najm_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable()->index();
            $table->unsignedBigInteger('account_id')->index();
            $table->bigInteger('amount');
            $table->string('entry_type'); // debit|credit
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('najm_ledger_entries');
    }
};
