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
        Schema::create('barangay_settings', function (Blueprint $table) {
            $table->id();
            $table->string('barangay_name')->default('Barangay Sample');
            $table->string('contact_email')->default('admin@barangay.gov.ph');
            $table->string('contact_number')->default('(02) 8888-8888');
            $table->boolean('notify_email_new_complaints')->default(true);
            $table->boolean('notify_sms_urgent_cases')->default(true);
            $table->boolean('notify_daily_summary_reports')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangay_settings');
    }
};
