<?php

namespace Database\Seeders;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $supervisor = User::where('email', 'security.supervisor@campuspatrol.local')->first()
            ?? User::where('email', 'supervisor@campusrfid.test')->first()
            ?? User::where('name', 'Security Admin')->where('role', 'admin')->first()
            ?? new User();

        $supervisor->forceFill([
            'name' => 'Security Supervisor',
            'username' => 'supervisor',
            'email' => 'security.supervisor@campuspatrol.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ])->save();

        User::where('name', 'Security Admin')
            ->where('id', '!=', $supervisor->id)
            ->delete();

        $guards = [
            [
                'employee_no' => 'TEST-01',
                'name' => 'Carmela Bihay Hernandez',
                'username' => 'carmela.bihay.hernandez',
                'password' => 'password',
                'email' => 'carmela.bihay.hernandez@guard.local',
                'phone' => '09773209561',
                'rfid_uid' => 'F33C8D37',
                'face_reference' => null,
                'shift' => 'Night Shift',
                'status' => 'active',
            ],
            [
                'employee_no' => 'SG-DEMO',
                'name' => 'Demo Guard',
                'username' => 'guard.demo',
                'password' => 'password',
                'email' => 'guard.demo@example.com',
                'phone' => '0917-000-1000',
                'rfid_uid' => 'RFID-DEMO',
                'face_reference' => 'demo-guard',
                'shift' => 'Demo Shift',
                'status' => 'active',
            ],
            [
                'employee_no' => 'SG-001',
                'name' => 'Juan Dela Cruz',
                'username' => 'juan.guard',
                'password' => 'password',
                'email' => 'juan.guard@example.com',
                'phone' => '0917-000-1001',
                'rfid_uid' => 'RFID-001',
                'face_reference' => 'juan-dela-cruz',
                'shift' => 'Night Shift',
                'status' => 'active',
            ],
            [
                'employee_no' => 'SG-002',
                'name' => 'Maria Santos',
                'username' => 'maria.guard',
                'password' => 'password',
                'email' => 'maria.guard@example.com',
                'phone' => '0917-000-1002',
                'rfid_uid' => 'RFID-002',
                'face_reference' => 'maria-santos',
                'shift' => 'Day Shift',
                'status' => 'active',
            ],
        ];

        foreach ($guards as $guard) {
            $guardAccount = User::updateOrCreate(
                ['username' => $guard['username']],
                [
                    'name' => $guard['name'],
                    'email' => $guard['email'],
                    'password' => Hash::make($guard['password']),
                    'role' => 'guard',
                ],
            );

            $guard['user_id'] = $guardAccount->id;
            unset($guard['username'], $guard['password']);

            Guard::updateOrCreate(['employee_no' => $guard['employee_no']], $guard);
        }

        $checkpoints = [
            [
                'code' => 'CP-IT-01',
                'name' => 'IT',
                'location' => 'IT',
                'device_uid' => 'ESP32-IT-01',
                'status' => 'active',
                'description' => 'RFID checkpoint for the IT patrol area.',
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
                'name' => 'FI',
                'location' => 'FI',
                'device_uid' => 'ESP32-FI-01',
                'status' => 'active',
                'description' => 'RFID checkpoint for the FI patrol area.',
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
                'code' => 'CP-AG-01',
                'name' => 'AG',
                'location' => 'AG',
                'device_uid' => 'ESP32-AG-01',
                'status' => 'active',
                'description' => 'RFID checkpoint for the AG patrol area.',
            ],
        ];

        foreach ($checkpoints as $checkpoint) {
            Checkpoint::updateOrCreate(['code' => $checkpoint['code']], $checkpoint);
        }

        Checkpoint::whereIn('code', ['CP-GATE', 'CP-LAB', 'CP-PARK', 'CP-SSC-01', 'CP-FH-01', 'CP-BD-01'])
            ->update(['status' => 'inactive']);
    }
}
