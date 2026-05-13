<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->json('evidence_paths')->nullable()->after('evidence_path');
        });

        DB::table('complaints')
            ->whereNotNull('evidence_path')
            ->orderBy('id')
            ->chunkById(200, function ($complaints): void {
                foreach ($complaints as $complaint) {
                    DB::table('complaints')
                        ->where('id', $complaint->id)
                        ->update([
                            'evidence_paths' => json_encode([$complaint->evidence_path]),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('evidence_paths');
        });
    }
};
