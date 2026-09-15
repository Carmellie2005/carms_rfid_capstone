<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Artisan::call('guard:clear-carmela-records', [
                '--force' => true,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Carmela records cleanup migration could not run.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
