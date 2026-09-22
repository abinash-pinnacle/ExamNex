<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            // When set, the pass mark is derived as ceil(passing_percent% of total_marks)
            // and kept in sync whenever the question total is recomputed. Null = fixed marks mode.
            if (! Schema::hasColumn('tests', 'passing_percent')) {
                $table->unsignedTinyInteger('passing_percent')->nullable()->after('passing_marks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            if (Schema::hasColumn('tests', 'passing_percent')) {
                $table->dropColumn('passing_percent');
            }
        });
    }
};
