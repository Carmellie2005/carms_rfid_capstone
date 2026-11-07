<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove the retired BCP-0008 guard profile and its linked guard login.
     * Patrol and incident history remains available according to the existing
     * foreign-key rules for the guards table.
     */
    public function up(): void
    {
        $guard = DB::table('guards')
            ->where('employee_no', 'BCP-0008')
            ->first(['id', 'user_id']);

        if (! $guard) {
            return;
        }

        DB::transaction(function () use ($guard): void {
            DB::table('guards')
                ->where('id', $guard->id)
                ->delete();

            if ($guard->user_id) {
                DB::table('users')
                    ->where('id', $guard->user_id)
                    ->where('role', 'guard')
                    ->delete();
            }
        });
    }

    public function down(): void
    {
        // The deleted profile contains user-provided data and cannot be restored safely.
    }
};
