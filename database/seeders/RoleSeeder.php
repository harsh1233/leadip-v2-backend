<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = [
            [
                'id'                => Str::uuid(),
                'name'              => 'Super Admin',
                'created_at'        => Carbon::now(),
                'created_by'        => 1
            ],
            [
                'id'                => Str::uuid(),
                'name'              => 'Admin',
                'created_at'        => Carbon::now(),
                'created_by'        => 1
            ],
            [
                'id'                => Str::uuid(),
                'name'              => 'Employee',
                'created_at'        => Carbon::now(),
                'created_by'        => 1
            ]
        ];
        Role::insert($roles);
            
    }
}
