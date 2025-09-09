<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('zt_invoices_archive', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('invoice_id')->unique();
            $table->string('uuid')->unique();
            $table->string('supplier');
            $table->string('customer');
            $table->decimal('amount', 15, 2);
            $table->date('issue_date');
            $table->text('content')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->string('type');

            $table->index(['issue_date']);
            $table->index('supplier');
            $table->index('customer');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('zt_invoices_archive');
    }
};


