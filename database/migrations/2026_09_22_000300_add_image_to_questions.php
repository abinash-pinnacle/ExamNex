<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional image on a question (e.g. reasoning/puzzle diagrams). Additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (! Schema::hasColumn('questions', 'image_path')) {
                $table->string('image_path')->nullable()->after('text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
