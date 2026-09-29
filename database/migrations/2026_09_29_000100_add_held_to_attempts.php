<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Hold" replaces auto-termination for malpractice: on too many violations the
 * attempt is HELD (timer keeps running, candidate is blocked) until an admin /
 * invigilator resumes it. Additive; defaults keep old attempts untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('attempts', 'held')) {
                $table->boolean('held')->default(false)->after('terminated');
            }
            if (! Schema::hasColumn('attempts', 'held_at')) {
                $table->timestamp('held_at')->nullable()->after('held');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['held', 'held_at']);
        });
    }
};
