<?php

use App\Models\AssignedContact;
use App\Models\AssignedList;
use App\Models\CompanyContact;
use App\Models\ContactList;
use App\Models\GlobalFile;
use App\Models\Protocol;
use App\Models\Folder;
use App\Models\Note;
use Illuminate\Support\Facades\Storage;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\Role;

if (!function_exists('uploadFile')) {
    function uploadFile($attachment, $directory)
    {
        $originalName = $attachment->getClientOriginalName();
        $extension = $attachment->getClientOriginalExtension();
        $fileName = pathinfo($originalName, PATHINFO_FILENAME);
        $path = $directory . '/' . $fileName . '-' . mt_rand(1000000000, time()) . '.' . $extension;

        Storage::disk('s3')->put($path, fopen($attachment, 'r+'), 'public');
        $url = Storage::disk('s3')->url($path);
        return $url;
    }
}

if (!function_exists('deleteFile')) {
    function deleteFile($url)
    {
        $path = parse_url($url)['path'];

        if ($path && Storage::disk('s3')->exists($path)) {
            Storage::disk('s3')->delete($path);
        }
    }
}

/* Store notification in table */
if (!function_exists('sentNotification')) {
    function sentNotification($message, $type)
    {
        $role = Role::where('name', 'Super Admin')->first();
        $user = User::where('company_id', auth()->user()->company_id)->where('id', '!=', auth()->user()->id)->where('role_id', $role->id)->first();
        if ($user) {
            if ($type == 'G') {
                $icon = 'Contacts.svg';
            } elseif ($type == 'P') {
                $icon = 'Prospects.svg';
            } elseif ($type == 'CL') {
                $icon = 'Clients.svg';
            } elseif ($type == 'lists') {
                $icon = 'list.svg';
            } elseif ($type == 'note') {
                $icon = 'Notes.svg';
            } elseif ($type == 'file') {
                $icon = 'pin.svg';
            } else {
                $icon = 'team.svg';
            }
            $notification = [
                'sender_id' => auth()->user()->id,
                'receiver_id' => $user->id,
                'title'      => $message,
                'icon'       => $icon,
            ];
            SystemNotification::create($notification);
        }
    }
}
/* Store multiple notification in table */
if (!function_exists('sentMultipleNotification')) {
    function sentMultipleNotification($type, $sub_type, $count = null)
    {
        $users = User::where('company_id', auth()->user()->company_id)->where('id', '!=', auth()->user()->id)->pluck('id')->toArray();

        if ($users) {
            if ($type == 'G') {
                $type = 'contact';
                $icon = 'Contacts.svg';
            } elseif ($type == 'P') {
                $type = 'prospect';
                $icon = 'Prospects.svg';
            } elseif ($type == 'CL') {
                $type = 'client';
                $icon = 'Clients.svg';
            }
            if ($sub_type == 'C') {
                $sub_type = 'company';
            } elseif ($sub_type == 'P') {
                $sub_type = 'people';
            }
            if ($count) {
                $message = $count . ' ' . $sub_type . ' ' . $type . 's are added';
            } else {
                $message = 'one new ' . $sub_type . ' ' . $type . ' is added';
            }
            foreach ($users as $key => $value) {
                $notification[] = [
                    'id'          => Str::uuid(),
                    'title'       => $message,
                    'sender_id'   => auth()->user()->id,
                    'receiver_id' => $value,
                    'icon'        => $icon,
                    'created_at'  => now()
                ];
            }
            SystemNotification::insert($notification);
        }
    }
}
/*Get User profile percentage */
if (!function_exists('profilePercentage')) {
    function profilePercentage()
    {
        $auth = auth()->user();

        /*Count profile percentage*/
        $profile_percentage = config('constants.profile_percentage') / config('constants.profile_column');
        $percentage  = $auth->first_name ? $profile_percentage : 0;

        $percentage += $auth->company    ? $profile_percentage : 0;

        $percentage += $auth->position   ? $profile_percentage : 0;

        $percentage += $auth->email      ? $profile_percentage : 0;

        if ($auth->userDetail) {
            $percentage += $auth->userDetail->point_of_contact ? $profile_percentage : 0;
            $percentage += $auth->userDetail->phone_number     ? $profile_percentage : 0;
            $percentage += $auth->userDetail->expertises       ? $profile_percentage : 0;
            $percentage += $auth->userDetail->interests        ? $profile_percentage : 0;
        }

        if ($auth->location) {
            $percentage += $auth->location->address      ? $profile_percentage : 0;
            $percentage += $auth->location->city         ? $profile_percentage : 0;
            $percentage += $auth->location->country_code ? $profile_percentage : 0;
        }

        /*Count company percentage*/
        $company_percentage = config('constants.company_percentage') / config('constants.company_column');
        if ($auth->company) {
            $percentage += $auth->company->name       ? $company_percentage : 0;
            $percentage += $auth->company->email      ? $company_percentage : 0;
            $percentage += $auth->company->phone      ? $company_percentage : 0;
            $percentage += $auth->company->website    ? $company_percentage : 0;
            $percentage += $auth->company->services   ? $company_percentage : 0;
            $percentage += $auth->company->expertises ? $company_percentage : 0;
            $percentage += $auth->company->regions    ? $company_percentage : 0;

            if ($auth->company->offices->count() > 0) {
                $percentage += $auth->company->offices[0]->type         ? $company_percentage : 0;
                $percentage += $auth->company->offices[0]->address      ? $company_percentage : 0;
                $percentage += $auth->company->offices[0]->country_code ? $company_percentage : 0;
                $percentage += $auth->company->offices[0]->city_id      ? $company_percentage : 0;
            }
        }
        /*Count upload contact percentage */

        if ($auth->companyContacts()->count() > 0) {
            $percentage += config('constants.contact_percentage');
        }

        return $percentage;
    }
}

/*Sent notification when profile incomplete */
if (!function_exists('sentUserNotification')) {
    function sentUserNotification()
    {
        $percentage = profilePercentage();
        if ($percentage < config('constants.max_percentage')) {
            $notification = [
                'sender_id' => auth()->user()->id,
                'receiver_id' => auth()->user()->id,
                'title' => "Your profile is $percentage % completed, please complete your profile by uploading/adding the contact and the profile details",
                'icon'  => 'profile.svg',
            ];
            SystemNotification::create($notification);
        }
    }
}

if (!function_exists('transferOwnership')) {
    function transferOwnership($id, $assign = 0, $newuser = null)
    {
        if ($assign == 0) {
            /* Transfer all functionality rights to super admin when delete team member */
            $role              = Role::where('name', config('constants.super_admin'))->first();
            $superAdmin        = User::where('company_id', auth()->user()->company_id)->where('role_id', $role->id)->first();

            if ($superAdmin) {
                AssignedContact::where('assigned_to_id', $id)->update(['assigned_to_id' => $superAdmin->id]);
                AssignedContact::where('assigned_by_id', $id)->update(['assigned_by_id' => $superAdmin->id]);
                AssignedContact::where('created_by', $id)->update(['created_by' => $superAdmin->id]);
                AssignedContact::where('updated_by', $id)->update(['updated_by' => $superAdmin->id]);

                $assignedList  = AssignedList::query();
                (clone $assignedList)->whereIn('assigned_to', [$id])->update(['assigned_to' => $superAdmin->id]);
                (clone $assignedList)->whereIn('assigned_from', [$id])->update(['assigned_from' => $superAdmin->id]);
                (clone $assignedList)->whereIn('owned_by', [$id])->update(['owned_by' => $superAdmin->id]);

                CompanyContact::where('created_by', $id)->update(['created_by' => $superAdmin->id]);
                CompanyContact::where('updated_by', $id)->update(['updated_by' => $superAdmin->id]);

                GlobalFile::where('created_by', $id)->update(['created_by' => $superAdmin->id]);
                GlobalFile::where('updated_by', $id)->update(['updated_by' => $superAdmin->id]);

                ContactList::whereIn('created_by', [$id])->update(['created_by' => $superAdmin->id]);

                $protocol = Protocol::query();
                (clone $protocol)->whereIn('assigned_to_id', [$id])->update(['assigned_to_id' => $superAdmin->id]);
                (clone $protocol)->whereIn('assigned_by_id', [$id])->update(['assigned_by_id' => $superAdmin->id]);
                (clone $protocol)->whereIn('created_by', [$id])->update(['created_by' => $superAdmin->id]);

                Folder::whereIn('created_by', [$id])->update(['created_by' => $superAdmin->id]);
                Note::whereIn('created_by', [$id])->update(['created_by' => $superAdmin->id]);
            }
        } else {

            /* Transfer all functionality rights to given team member when delete team member */
            AssignedContact::where('assigned_to_id', $id)->update(['assigned_to_id' => $newuser]);
            AssignedContact::where('assigned_by_id', $id)->update(['assigned_by_id' => $newuser]);
            AssignedContact::where('created_by', $id)->update(['created_by' => $newuser]);
            AssignedContact::where('updated_by', $id)->update(['updated_by' => $newuser]);

            $assignedList  = AssignedList::query();
            (clone $assignedList)->whereIn('assigned_to', [$id])->update(['assigned_to' => $newuser]);
            (clone $assignedList)->whereIn('assigned_from', [$id])->update(['assigned_from' => $newuser]);
            (clone $assignedList)->whereIn('owned_by', [$id])->update(['owned_by' => $newuser]);

            CompanyContact::where('created_by', $id)->update(['created_by' => $newuser]);
            CompanyContact::where('updated_by', $id)->update(['updated_by' => $newuser]);

            GlobalFile::where('created_by', $id)->update(['created_by' => $newuser]);
            GlobalFile::where('updated_by', $id)->update(['updated_by' => $newuser]);

            ContactList::whereIn('created_by', [$id])->update(['created_by' => $newuser]);

            $protocol = Protocol::query();
            (clone $protocol)->whereIn('assigned_to_id', [$id])->update(['assigned_to_id' => $newuser]);
            (clone $protocol)->whereIn('assigned_by_id', [$id])->update(['assigned_by_id' => $newuser]);
            (clone $protocol)->whereIn('created_by', [$id])->update(['created_by' => $newuser]);

            Folder::whereIn('created_by', [$id])->update(['created_by' => $newuser]);
            Note::whereIn('created_by', [$id])->update(['created_by' => $newuser]);
        }
    }
}

/*Sent notification when profile incomplete */
if (!function_exists('sentEnrichContactNotification')) {
    function sentEnrichContactNotification($message)
    {
        $notification = [
            'sender_id'   => auth()->user()->id,
            'receiver_id' => auth()->user()->id,
            'title'       => $message,
            'icon'        => 'profile.svg',
        ];
        SystemNotification::create($notification);
    }
}
