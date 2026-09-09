<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guards') || ! Schema::hasTable('guard_face_descriptors')) {
            return;
        }

        $guardIds = DB::table('guards')
            ->leftJoin('users', 'guards.user_id', '=', 'users.id')
            ->where(function ($query): void {
                $query
                    ->where('guards.employee_no', 'TEST-01')
                    ->orWhere('guards.name', 'Carmela Bihay Hernandez')
                    ->orWhere('guards.rfid_uid', 'F33C8D37')
                    ->orWhere('users.username', 'carmela.bihay.hernandez')
                    ->orWhere('users.email', 'carmela.bihay.hernandez@guard.local');
            })
            ->pluck('guards.id');

        if ($guardIds->isEmpty()) {
            return;
        }

        $faceDescriptors = DB::table('guard_face_descriptors')
            ->whereIn('guard_id', $guardIds)
            ->get(['id', 'image_path']);

        foreach ($faceDescriptors as $faceDescriptor) {
            if ($faceDescriptor->image_path) {
                Storage::disk('public')->delete($faceDescriptor->image_path);
            }
        }

        DB::table('guard_face_descriptors')
            ->whereIn('id', $faceDescriptors->pluck('id'))
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
