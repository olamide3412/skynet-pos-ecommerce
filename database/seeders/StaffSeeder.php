<?php

namespace Database\Seeders;

use App\Enums\RoleEnums;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate([
            'email' => 'superadmin@gmail.com',
        ], [
            'name'     => 'Olamide Johnson Isaiah',
            'role'     => RoleEnums::SuperAdministrator->value,
            'password' => bcrypt('123456'),
            'status'   => 'enable',
        ]);

        User::firstOrCreate([
            'email' => 'admin@gmail.com',
        ], [
            'name'     => 'Afen Blessing',
            'role'     => RoleEnums::Administrator->value,
            'password' => bcrypt('123456'),
            'status'   => 'enable',
        ]);

        $staff = User::firstOrCreate([
            'email' => 'staff@gmail.com',
        ], [
            'name'     => 'Isaiah Johnson Olamide',
            'role'     => RoleEnums::Staff->value,
            'password' => bcrypt('123456'),
            'status'   => 'enable',
        ]);

        // Dedicated in-house Store Rider (Staff account with Rider / Dispatch role)
        $rider = User::firstOrCreate([
            'email' => 'rider@gmail.com',
        ], [
            'name'     => 'Sunday Michael (Store Rider)',
            'phone'    => '08012345678',
            'role'     => RoleEnums::Staff->value,
            'password' => bcrypt('123456'),
            'status'   => 'enable',
        ]);
        if (!$rider->hasRole('Rider / Dispatch')) {
            $rider->assignRole('Rider / Dispatch');
        }
    }
}
