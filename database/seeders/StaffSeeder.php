<?php

namespace Database\Seeders;

use App\Enums\Staff\StaffDepartmentEnums;
use App\Enums\RoleEnums;
use App\Enums\Staff\StaffPositionEnums;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         User::create([
            'name' => 'Olamide Johnson Isaiah',
            'role' => RoleEnums::SuperAdministrator->value,
            'email' => 'superadmin@gmail.com',
            'password' => bcrypt('123456'),
            ]);




         User::create([
             'name' => 'Afen Blessing',
            'role' => RoleEnums::Administrator->value,
            'email' => 'admin@gmail.com',
            'password' => bcrypt('123456'),
            ]);


          User::create([
            'name' => 'Isaiah Johnson Olamide',
            'role' => RoleEnums::Staff->value,
            'email' => 'staff@gmail.com',
            'password' => bcrypt('123456'),
            ]);

    }
}
