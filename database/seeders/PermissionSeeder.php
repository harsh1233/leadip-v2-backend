<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Str;
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions =[
            [
                'id'=>Str::uuid(),
                'name'=>'Add',
                'code'=>'add'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Edit',
                'code'=>'edit'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'View',
                'code'=>'view'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Delete',
                'code'=>'delete'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Upload',
                'code'=>'upload'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Assign',
                'code'=>'assign'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Add to list',
                'code'=>'add_to_list'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Move',
                'code'=>'move'
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Share',
                'code'=>'share'

            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Export',
                'code'=>'export',
            ],
            [
                'id'=>Str::uuid(),
                'name'=>'Remove',
                'code'=>'remove'
            ],
        ];
        Permission::insert($permissions);
    }
}
