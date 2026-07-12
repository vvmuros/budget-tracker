<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Existing accounts predate email verification; mark them verified so
     * the new `verified` middleware doesn't lock anyone out of their own data.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Not reversible: we can't distinguish accounts that were already verified from backfilled ones.
    }
};
