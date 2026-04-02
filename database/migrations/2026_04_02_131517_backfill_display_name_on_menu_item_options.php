<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copy display_name from active options to their soft-deleted predecessors.
     *
     * Old orders reference soft-deleted option IDs that pre-date the display_name
     * column. The analytics query reads display_name from the joined option, so
     * those old options need the value too.
     */
    public function up(): void
    {
        // 1. Match by menu_item_id + option_key (most precise)
        DB::statement("
            UPDATE menu_item_options AS old_opt
            SET display_name = new_opt.display_name
            FROM menu_item_options AS new_opt
            WHERE old_opt.menu_item_id = new_opt.menu_item_id
              AND old_opt.option_key   = new_opt.option_key
              AND new_opt.deleted_at IS NULL
              AND new_opt.display_name IS NOT NULL
              AND TRIM(new_opt.display_name) != ''
              AND old_opt.deleted_at IS NOT NULL
              AND (old_opt.display_name IS NULL OR TRIM(old_opt.display_name) = '')
        ");

        // 2. Match by menu_item_id + option_label (catches changed keys)
        DB::statement("
            UPDATE menu_item_options AS old_opt
            SET display_name = new_opt.display_name
            FROM menu_item_options AS new_opt
            WHERE old_opt.menu_item_id = new_opt.menu_item_id
              AND old_opt.option_label = new_opt.option_label
              AND new_opt.deleted_at IS NULL
              AND new_opt.display_name IS NOT NULL
              AND TRIM(new_opt.display_name) != ''
              AND old_opt.deleted_at IS NOT NULL
              AND (old_opt.display_name IS NULL OR TRIM(old_opt.display_name) = '')
        ");

        // 3. Single-option fallback: if a menu item has exactly 1 active option
        //    with display_name, copy it to all soft-deleted options on that item.
        DB::statement("
            UPDATE menu_item_options AS old_opt
            SET display_name = single.display_name
            FROM (
                SELECT menu_item_id, display_name
                FROM menu_item_options
                WHERE deleted_at IS NULL
                  AND display_name IS NOT NULL
                  AND TRIM(display_name) != ''
                GROUP BY menu_item_id, display_name
                HAVING COUNT(*) = 1
            ) AS single
            WHERE old_opt.menu_item_id = single.menu_item_id
              AND old_opt.deleted_at IS NOT NULL
              AND (old_opt.display_name IS NULL OR TRIM(old_opt.display_name) = '')
        ");
    }

    public function down(): void
    {
        // Cannot reliably reverse — backfilled values are indistinguishable
        // from manually-set ones.
    }
};
