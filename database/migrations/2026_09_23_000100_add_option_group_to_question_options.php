<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * option_group lets one question hold several independent blanks (MCQ_BLANKS):
 * every option belongs to a blank (group 0, 1, 2 …), with one correct option per group.
 * Default 0 keeps all existing MCQ options in a single group (unchanged behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_options', function (Blueprint $table) {
            if (! Schema::hasColumn('question_options', 'option_group')) {
                $table->integer('option_group')->default(0)->after('order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('question_options', function (Blueprint $table) {
            if (Schema::hasColumn('question_options', 'option_group')) {
                $table->dropColumn('option_group');
            }
        });
    }
};
