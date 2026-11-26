<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turn every share marked paid into a payment so balances carry over, then drop per-share paid status.
     */
    public function up(): void
    {
        DB::table('grocery_shares')
            ->join('grocery_weeks', 'grocery_weeks.id', '=', 'grocery_shares.grocery_week_id')
            ->where('grocery_shares.is_paid', true)
            ->orderBy('grocery_shares.id')
            ->select('grocery_shares.*', 'grocery_weeks.week_date')
            ->get()
            ->each(fn (object $share) => DB::table('payments')->insert([
                'sister_id' => $share->sister_id,
                'amount' => $share->amount,
                'paid_on' => substr((string) ($share->paid_at ?? $share->updated_at ?? now()), 0, 10),
                'notes' => 'Week of '.substr((string) $share->week_date, 0, 10),
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        Schema::table('grocery_shares', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'paid_at']);
        });
    }

    /**
     * Restore the columns. Payments are kept; shares come back unpaid.
     */
    public function down(): void
    {
        Schema::table('grocery_shares', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
        });
    }
};
