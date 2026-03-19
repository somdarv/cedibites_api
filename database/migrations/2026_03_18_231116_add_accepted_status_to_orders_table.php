<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STATUSES = "'received','accepted','preparing','ready','out_for_delivery','delivered','ready_for_pickup','completed','cancelled'";

    private const STATUSES_WITHOUT_ACCEPTED = "'received','preparing','ready','out_for_delivery','delivered','ready_for_pickup','completed','cancelled'";

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Drop existing check constraints and add new ones including 'accepted'
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('.self::STATUSES.'))');

            DB::statement('ALTER TABLE order_status_history DROP CONSTRAINT IF EXISTS order_status_history_status_check');
            DB::statement('ALTER TABLE order_status_history ADD CONSTRAINT order_status_history_status_check CHECK (status IN ('.self::STATUSES.'))');
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support ALTER TABLE for check constraints;
            // tests use RefreshDatabase so the migration file itself is the source of truth.
            // Update done via recreating with new constraint in the original migration.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('.self::STATUSES_WITHOUT_ACCEPTED.'))');

            DB::statement('ALTER TABLE order_status_history DROP CONSTRAINT IF EXISTS order_status_history_status_check');
            DB::statement('ALTER TABLE order_status_history ADD CONSTRAINT order_status_history_status_check CHECK (status IN ('.self::STATUSES_WITHOUT_ACCEPTED.'))');
        }
    }
};
