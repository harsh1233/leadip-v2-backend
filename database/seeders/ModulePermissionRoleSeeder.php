<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Module;
use App\Models\ModulePermissionRole;
use Str;
class ModulePermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permission          = Permission::query();
        $role                = Role::query();
        $ownerRole           = (clone $role)->where('name',config('constants.super_admin'))->first();
        $adminRole           = (clone $role)->where('name',config('constants.admin'))->first();
        $employeeRole        = (clone $role)->where('name',config('constants.employee'))->first();
        
        $name = (clone $permission)->pluck('code','code')->toArray();
        
        $permissionCode =[];
        foreach($name as $key=>$value){
            $permissionCode[$value] = $key;
        }
       
        $module          = Module::pluck('code','code')->toArray();
        $moduleCode=[];
        foreach($module as $key=>$value){
            $moduleCode[$value] = $key;
        }
        

        /*Manage  Permission of Super Admin */
        $data = [
            /*Manage Contacts */
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['add_to_list'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['move'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['share'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['export'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            /*Manage Prospects */
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['add_to_list'],
                'role_id'         =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['move'],
                'role_id'        =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['share'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['export'],
                'role_id'         =>$ownerRole->id,
                'has_access'     =>1
            ],
            /*Manage Client */
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['view'],
                'role_id'        =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$ownerRole->id,
                'has_access'     =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['add_to_list'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['move'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['share'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['export'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
             /*Manage List */
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],

            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
        
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['move'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['export'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            /*My Profile */
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            

            /* My Compnay Manage */
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
           
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            
            /*Manage Team */
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
           

            /*Manage Report */
            [
               
                'module_code'     =>$moduleCode['manage_reports'],
                'permission_code' =>$permissionCode['export'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            /*Manage List Detail */
            [
               
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],

            [
               
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'         =>$ownerRole->id,
                'has_access'      =>1
            ],
        
            [
               
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['move'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['export'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            /*Manage Notes */
            [
               
                'module_code'     =>$moduleCode['manage_notes'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_notes'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_notes'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_notes'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            /*Manage Files */
            [
               
                'module_code'     =>$moduleCode['manage_files'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_files'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_files'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            /*Manage Team inside the card */
            [
               
                'module_code'     =>$moduleCode['manage_teams'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_teams'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$ownerRole->id,
                'has_access'     =>1
            ],
            /*Manage permission by admin */
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>0
            ],
            [
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],

            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['add_to_list'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['move'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['share'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['export'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            /*Manage Prospects */
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>0
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],

            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['add_to_list'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['move'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['share'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['export'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            /*Manage Client */
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>0
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['add_to_list'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['move'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['share'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
           
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['export'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
             /*Manage List */
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>0
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
        
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['move'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['export'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            /*My Profile */
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],                                                                                                                                                                      
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],
            

            /* My Compnay Manage */
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],
           
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],
            
            /*Manage Team */
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>0
            ],
           /*Manage Report */
            
           [
               
                'module_code'     =>$moduleCode['manage_reports'],
                'permission_code' =>$permissionCode['export'],
                'role_id'         =>$adminRole->id,
                'has_access'      =>1
           ],
           /*Manage list Detail */
           [
               
            'module_code'     =>$moduleCode['manage_list_detail'],
            'permission_code' =>$permissionCode['view'],
            'role_id'       =>$adminRole->id,
            'has_access'     =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [

                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
        
            [
            
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['move'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['export'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
           /*Manage Notes */
           [
               
            'module_code'     =>$moduleCode['manage_notes'],
            'permission_code' =>$permissionCode['view'],
            'role_id'       =>$adminRole->id,
            'has_access'     =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_notes'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_notes'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_notes'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>0
            ],
            /*Manage Files */
            [
            
                'module_code'     =>$moduleCode['manage_files'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_files'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_files'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>0
            ],
            /*Manage Team inside the card */
            [
            
                'module_code'     =>$moduleCode['manage_teams'],
                'permission_code' =>$permissionCode['view'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_teams'],
                'permission_code' =>$permissionCode['add'],
                'role_id'       =>$adminRole->id,
                'has_access'     =>1
            ],
            /*Manage Permission of employee */
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$employeeRole->id,
                'has_access'     =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],

            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['add_to_list'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['move'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['share'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_contacts'],
                'permission_code' =>$permissionCode['export'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            /*Manage Prospects */
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],

            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['add_to_list'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['share'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['move'],
                'role_id'         =>$employeeRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_prospects'],
                'permission_code' =>$permissionCode['export'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            /*Manage Client */
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'        =>$employeeRole->id,
                'has_access'     =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['add_to_list'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['share'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['move'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_clients'],
                'permission_code' =>$permissionCode['export'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
             /*Manage List */
            
             [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
        
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['move'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_lists'],
                'permission_code' =>$permissionCode['export'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            /*My Profile */
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_profile'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            

            /* My Compnay Manage */
            
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$employeeRole->id,
                'has_access'     =>0
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_my_company'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],
    
            
            /*Manage Team */
           
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            
            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],

            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],

            [
               
                'module_code'     =>$moduleCode['manage_company_settings_info_team'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],

           /*Manage Report */
            
           [
               
                'module_code'     =>$moduleCode['manage_reports'],
                'permission_code' =>$permissionCode['export'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
           ],
           /*Manage list detail */
           [
               
            'module_code'     =>$moduleCode['manage_list_detail'],
            'permission_code' =>$permissionCode['view'],
            'role_id'         =>$employeeRole->id,
            'has_access'      =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['assign'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
        
            [
            
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['move'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['export'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_list_detail'],
                'permission_code' =>$permissionCode['remove'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],
           /*Manage Notes */
           [
               
            'module_code'         =>$moduleCode['manage_notes'],
            'permission_code'     =>$permissionCode['view'],
            'role_id'             =>$employeeRole->id,
            'has_access'          =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_notes'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_notes'],
                'permission_code' =>$permissionCode['edit'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_notes'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],
            /*Manage Files */
            [
            
                'module_code'     =>$moduleCode['manage_files'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_files'],
                'permission_code' =>$permissionCode['upload'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_files'],
                'permission_code' =>$permissionCode['delete'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>0
            ],
            /*Manage Team inside the card */
            [
            
                'module_code'     =>$moduleCode['manage_teams'],
                'permission_code' =>$permissionCode['view'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
            [
            
                'module_code'     =>$moduleCode['manage_teams'],
                'permission_code' =>$permissionCode['add'],
                'role_id'         =>$employeeRole->id,
                'has_access'      =>1
            ],
  
        ];
        
       
        foreach($data as $value){
            $modulePermission []=[
                'id'=>Str::uuid(),
                'module_code'    => $value['module_code'],
                'permission_code'=> $value['permission_code'],
                'role_id'        => $value['role_id'],
                'has_access'     => $value['has_access']
            ];
            
        }
        ModulePermissionRole::insert($modulePermission);
    }
}
