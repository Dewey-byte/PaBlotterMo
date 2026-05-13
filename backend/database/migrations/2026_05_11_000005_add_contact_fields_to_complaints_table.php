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
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('contact_method')->nullable()->after('contact_number');
            $table->string('contact_value')->nullable()->after('contact_method');
            $table->index('contact_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['contact_method']);
            $table->dropColumn(['contact_method', 'contact_value']);
        });
    }
};
