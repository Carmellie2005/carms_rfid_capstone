<?php

namespace Database\Seeders;

use App\Models\Checkpoint;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    private const DEFAULT_SUPERVISOR_PASSWORD = 'password123';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedSupervisorAccount();
        $this->seedCheckpoints();
        $this->deactivateLegacyCheckpoints();
    }

    private function seedSupervisorAccount(): void
    {
        $supervisor = User::query()
            ->where('email', 'security.supervisor@campuspatrol.local')
            ->orWhere('username', 'supervisor')
            ->orWhere('email', 'supervisor@campusrfid.test')
            ->orWhere(function ($query): void {
                $query->where('name', 'Security Admin')
                    ->where('role', 'admin');
            })
            ->first() ?? new User();

        $isNewSupervisor = ! $supervisor->exists;

        $supervisor->forceFill([
            'name' => 'Security Supervisor',
            'username' => 'supervisor',
            'email' => 'security.supervisor@campuspatrol.local',
            'role' => 'admin',
            'must_change_password' => false,
        ]);

        if ($isNewSupervisor || blank($supervisor->password) || $this->shouldResetSupervisorPassword()) {
            $supervisor->password = Hash::make($this->supervisorPassword());
        }

        $supervisor->save();
    }

    private function seedCheckpoints(): void
    {
        foreach ($this->checkpoints() as $checkpoint) {
            Checkpoint::updateOrCreate(
                ['code' => $checkpoint['code']],
                $checkpoint
            );
        }
    }

    private function deactivateLegacyCheckpoints(): void
    {
        Checkpoint::whereIn('code', [
            'CP-GATE',
            'CP-LAB',
            'CP-PARK',
            'CP-SSC-01',
            'CP-FH-01',
            'CP-BD-01',
            'CP-AG-01',
        ])->update(['status' => 'inactive']);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function checkpoints(): array
    {
        return [
            [
                'code' => 'CP-IT-01',
                'name' => 'BITS',
                'location' => 'BITS',
                'device_uid' => 'ESP32-IT-01',
                'status' => 'active',
                'description' => 'RFID checkpoint for the BITS patrol area.',
            ],
            [
                'code' => 'CP-GH-01',
                'name' => 'Guard House',
                'location' => 'GH',
                'device_uid' => 'ESP32-GH-01',
                'status' => 'active',
                'description' => 'RFID checkpoint for the Guard House patrol area.',
            ],
            [
                'code' => 'CP-CAN-01',
                'name' => 'Campus Canteen',
                'location' => 'Campus Canteen',
                'device_uid' => 'ESP32-CAN-01',
                'status' => 'active',
                'description' => 'RFID checkpoint for the Campus Canteen patrol area.',
            ],
            [
                'code' => 'CP-MPC-01',
                'name' => 'MPC',
                'location' => 'MPC',
                'device_uid' => 'ESP32-MPC-01',
                'status' => 'active',
                'description' => 'RFID checkpoint for the MPC patrol area.',
            ],
            [
                'code' => 'CP-FI-01',
                'name' => 'Tilapia Hatchery',
                'location' => 'Tilapia Hatchery',
                'device_uid' => 'ESP32-TH-01',
                'status' => 'active',
                'description' => 'RFID checkpoint for the Tilapia Hatchery patrol area.',
            ],
        ];
    }

    private function supervisorPassword(): string
    {
        return env('DEFAULT_SUPERVISOR_PASSWORD', self::DEFAULT_SUPERVISOR_PASSWORD);
    }

    private function shouldResetSupervisorPassword(): bool
    {
        return filter_var(env('RESET_DEFAULT_SUPERVISOR_PASSWORD', false), FILTER_VALIDATE_BOOLEAN);
    }
}
