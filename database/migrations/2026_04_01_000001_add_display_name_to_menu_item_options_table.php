<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('menu_item_options', 'display_name')) {
            return;
        }

        Schema::table('menu_item_options', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('option_label');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('menu_item_options', 'display_name')) {
            return;
        }

        Schema::table('menu_item_options', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
