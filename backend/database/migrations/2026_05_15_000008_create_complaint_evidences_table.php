<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('original_name', 255);
            $table->string('mime_type', 128)->nullable();
            $table->timestamps();
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb', 'tidb'], true)) {
            DB::statement('ALTER TABLE complaint_evidences ADD file_data LONGBLOB NOT NULL AFTER mime_type');
        } else {
            // SQLite / other: default blob (~65KB) — not suitable for large video; use MySQL/TiDB in production.
            Schema::table('complaint_evidences', function (Blueprint $table) {
                $table->binary('file_data');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_evidences');
    }
};
