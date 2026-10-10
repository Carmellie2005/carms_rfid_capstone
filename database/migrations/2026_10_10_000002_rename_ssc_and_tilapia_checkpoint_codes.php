<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->renameCheckpoint(
                'CP-GH-01',
                'CP-SSC-01',
                [
                    'name' => 'SSC',
                    'location' => 'SSC',
                    'device_uid' => 'ESP32-SSC-01',
                    'description' => 'RFID checkpoint for the SSC patrol area.',
                ]
            );

            $this->renameCheckpoint(
                'CP-FI-01',
                'CP-TH-01',
                [
                    'name' => 'Tilapia Hatchery',
                    'location' => 'Tilapia Hatchery',
                    'device_uid' => 'ESP32-TH-01',
                    'description' => 'RFID checkpoint for the Tilapia Hatchery patrol area.',
                ]
            );

            $this->renamePatrolLogCode('CP-GH-01', 'CP-SSC-01');
            $this->renamePatrolLogCode('CP-FI-01', 'CP-TH-01');
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->renameCheckpoint(
                'CP-SSC-01',
                'CP-GH-01',
                [
                    'name' => 'Guard House',
                    'location' => 'GH',
                    'device_uid' => 'ESP32-GH-01',
                    'description' => 'RFID checkpoint for the Guard House patrol area.',
                ]
            );

            $this->renameCheckpoint(
                'CP-TH-01',
                'CP-FI-01',
                [
                    'name' => 'Tilapia Hatchery',
                    'location' => 'Tilapia Hatchery',
                    'device_uid' => 'ESP32-TH-01',
                    'description' => 'RFID checkpoint for the Tilapia Hatchery patrol area.',
                ]
            );

            $this->renamePatrolLogCode('CP-SSC-01', 'CP-GH-01');
            $this->renamePatrolLogCode('CP-TH-01', 'CP-FI-01');
        });
    }

    /**
     * @param  array{name: string, location: string, device_uid: string, description: string}  $updates
     */
    private function renameCheckpoint(string $fromCode, string $toCode, array $updates): void
    {
        $source = DB::table('checkpoints')->where('code', $fromCode)->first();

        if ($source) {
            $this->retireConflictingCheckpoint($toCode, $updates['device_uid'], $source->id);

            DB::table('checkpoints')
                ->where('id', $source->id)
                ->update($updates + [
                    'code' => $toCode,
                    'status' => 'active',
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('checkpoints')
            ->where('code', $toCode)
            ->update($updates + [
                'status' => 'active',
                'updated_at' => now(),
            ]);
    }

    private function retireConflictingCheckpoint(string $code, string $deviceUid, int $sourceId): void
    {
        DB::table('checkpoints')
            ->where('id', '!=', $sourceId)
            ->where(function ($query) use ($code, $deviceUid): void {
                $query->where('code', $code)
                    ->orWhere('device_uid', $deviceUid);
            })
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $checkpoint) use ($code): void {
                DB::table('checkpoints')
                    ->where('id', $checkpoint->id)
                    ->update([
                        'code' => $code.'-LEGACY-'.$checkpoint->id,
                        'device_uid' => null,
                        'status' => 'inactive',
                        'updated_at' => now(),
                    ]);
            });
    }

    private function renamePatrolLogCode(string $fromCode, string $toCode): void
    {
        DB::table('patrol_logs')
            ->where('checkpoint_code', $fromCode)
            ->update([
                'checkpoint_code' => $toCode,
                'updated_at' => now(),
            ]);
    }
};
