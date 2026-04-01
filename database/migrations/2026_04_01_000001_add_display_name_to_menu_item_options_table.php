<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_item_options', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('option_label');
        });
    }

    public function down(): void
    {
        Schema::table('menu_item_options', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
