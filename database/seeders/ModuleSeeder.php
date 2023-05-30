<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Str;
class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $modules =[
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Contacts',
                'code'=>'manage_contacts'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Prospects',
                'code'=>'manage_prospects'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Clients',
                'code'=>'manage_clients'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Lists',
                'code'=>'manage_lists'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Company Settings & Info - My profile',
                'code'=>'manage_company_settings_info_my_profile'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Company Settings & Info - My Company',
                'code'=>'manage_company_settings_info_my_company'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Company Settings & Info - Team',
                'code'=>'manage_company_settings_info_team'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Reports',
                'code'=>'manage_reports'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Reassign Owner Role',
                'code'=>'reassing_owner_role'
            ],
            /*added new Modules */
            [
                'id'=>Str::uuid(),
                'name'=>'Manage List Detail',
                'code'=>'manage_list_detail'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Notes',
                'code'=>'manage_notes'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Files',
                'code'=>'manage_files'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Manage Teams',
                'code'=>'manage_teams'
            ],
        ];
        Module::insert($modules);
    }
}
