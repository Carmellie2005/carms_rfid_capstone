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
        $defaultPassword = 'password123';

        $supervisor = User::where('email', 'security.supervisor@campuspatrol.local')->first()
            ?? User::where('email', 'supervisor@campusrfid.test')->first()
            ?? User::where('name', 'Security Admin')->where('role', 'admin')->first()
            ?? new User();

        $supervisor->forceFill([
            'name' => 'Security Supervisor',
            'username' => 'supervisor',
            'email' => 'security.supervisor@campuspatrol.local',
            'password' => Hash::make($defaultPassword),
            'role' => 'admin',
        ])->save();

        User::where('name', 'Security Admin')
            ->where('id', '!=', $supervisor->id)
            ->delete();

        $removedGuardEmployeeNos = ['SG-DEMO', 'SG-001', 'SG-002'];
        $removedGuardNames = ['Demo Guard', 'Juan Dela Cruz', 'Maria Santos'];
        $removedGuardEmails = ['guard.demo@example.com', 'juan.guard@example.com', 'maria.guard@example.com'];
        $removedGuardRfids = ['RFID-DEMO', 'RFID-001', 'RFID-002'];
        $removedGuardUsernames = ['guard.demo', 'juan.guard', 'maria.guard'];

        Guard::where(function ($query) use ($removedGuardEmployeeNos, $removedGuardNames, $removedGuardEmails, $removedGuardRfids): void {
            $query
                ->whereIn('employee_no', $removedGuardEmployeeNos)
                ->orWhereIn('name', $removedGuardNames)
                ->orWhereIn('email', $removedGuardEmails)
                ->orWhereIn('rfid_uid', $removedGuardRfids);
        })
            ->with('user')
            ->get()
            ->each(function (Guard $guard): void {
                $user = $guard->user;

                $guard->delete();

                if ($user && $user->role === 'guard') {
                    $user->delete();
                }
            });

        User::where(function ($query) use ($removedGuardUsernames, $removedGuardEmails, $removedGuardNames): void {
            $query
                ->whereIn('username', $removedGuardUsernames)
                ->orWhereIn('email', $removedGuardEmails)
                ->orWhereIn('name', $removedGuardNames);
        })
            ->where('role', 'guard')
            ->delete();

        $guards = [
            [
                'employee_no' => 'TEST-01',
                'name' => 'Carmela Bihay Hernandez',
                'username' => 'carmela.bihay.hernandez',
                'password' => $defaultPassword,
                'email' => 'carmela.bihay.hernandez@guard.local',
                'phone' => '09773209561',
                'rfid_uid' => 'F33C8D37',
                'face_reference' => null,
                'shift' => 'Night Shift',
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
