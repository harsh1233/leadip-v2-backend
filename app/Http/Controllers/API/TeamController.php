<?php

namespace App\Http\Controllers\API;

use PDO;
use App\Models\User;
use Illuminate\Support\Str;
use App\Mail\InvitationMail;
use App\Models\UserLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Models\Role;

class TeamController extends Controller
{
    public function addMember(Request $request)
    {
        $this->validate($request, [
            'profile_picture'    =>  'nullable|mimes:png,jpg,jpeg|max:5120',
            'first_name'         =>  'required',
            'last_name'          =>  'required',
            'email'              =>  'required|email|unique:users',
            'position'           =>  'nullable|max:64',
            'role_id'            =>  'required|exists:roles,id',
            'country_code'       =>  'required|exists:countries,code',
            'city_id'            =>  'required|exists:cities,id'
        ], [
            'profile_picture.max' => 'The profile picture must not be greater than 5 MB.'
        ]);

        /*Store image in s3 Bucket */
        $url = '';
        if ($request->profile_picture) {
            $file = $request->profile_picture;
            $directory = 'team/' . auth()->user()->id;
            $url = uploadFile($file, $directory);
        }

        $token = Str::random(20);

        $user = User::create($request->only('first_name', 'last_name', 'email', 'password', 'role_id', 'position')
            + [
                'company_id' => auth()->user()->company_id,
                'password' => Hash::make(Str::random(10)),
                'profile_picture' => $url,
                'verification_token' => $token,
                'onboarding_status' => "I",
                'verification_token_expiry' => date('Y-m-d H:i:s', strtotime("+48 hours"))
            ]);

        UserLocation::create($request->only('country_code', 'city_id')
            + ['user_id' => $user->id, 'created_by' => auth()->user()->id]);

        $companyName = $user->company->name;

        $authUser = auth()->user();

        Mail::to($user->email)->send(new InvitationMail($authUser, $token, $user, $companyName));

        return ok(__('Team member added successfully!'), $user);
    }

    public function listMembers(Request $request)
    {
        $this->validate($request, [
            'sort_by'    =>  'nullable|in:asc,desc',
        ]);

        $query = User::with('role', 'location.country', 'location.city')->where('company_id', auth()->user()->company_id);

        if ($request->search) {
            $search = '%' . $request->search . '%';

            $query->where(function ($query) use ($search) {

                $query->where(DB::raw("CONCAT(`first_name`, ' ', `last_name`)"), 'LIKE', $search)
                    ->orWhere('position', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhereHas('role', function ($query) use ($search) {
                        $query->where('name', 'like', $search);
                    })
                    ->orWhereHas('location.country', function ($query) use ($search) {
                        $query->where('name', 'like', $search);
                    })
                    ->orWhereHas('location.city', function ($query) use ($search) {
                        $query->where('name', 'like', $search);
                    });
            });
        }

        if ($request->sort_by) {
            $query->orderBy('created_at', $request->sort_by);
        }

        $count = $query->count();
        if ($request->page && $request->perPage) {
            $page    = $request->page;
            $perPage = $request->perPage;
            $query->skip($perPage * ($page - 1))->take($perPage);
        }

        // $users = $query->get()->sortByDesc(function($query){
        //     if($query->role->name =='Super Admin'){
        //         return $query->role->name;
        //     }
        //  })->values();
        $loggedUser = auth()->user()->load('role', 'location.country', 'location.city');

        $users      = $query->get();

        if ($users->where('id', auth()->user()->id)->count() > 0) {
            $users = $users->where('id', '!=', auth()->user()->id);
            $users = collect($users)->prepend($loggedUser);
        }

        return ok(__('Team members list'), [
            'users' => $users,
            'count' => $count,
        ]);
    }

    public function updateMember(Request $request, $id)
    {
        $this->validate($request, [
            'profile_picture'    =>  'nullable|mimes:jpg,jpeg,png||max:5120',
            'first_name'         =>  'required',
            'last_name'          =>  'required',
            'email'              =>  'required|email|unique:users,email,' . $id,
            'position'           =>  'nullable|max:64',
            'role_id'            =>  'required|exists:roles,id',
            'country_code'       =>  'required|exists:countries,code',
            'city_id'            =>  'required|exists:cities,id'
        ], [
            'profile_picture.max' => 'The profile picture must not be greater than 5 MB.'
        ]);

        $user                     = User::findorfail($id);

        if ($profilePicture = $request->file('profile_picture')) {
            /*Store image in s3 bucket */
            // $url = time() . '.' . $profilePicture->extension();
            // $request->profile_picture->storeAs('public/teamMembers', $url);

            // $url = public_path('storage/teamMembers/'.$url);
            $file = $request->profile_picture;
            $directory = 'team/' . auth()->user()->id;
            $url = uploadFile($file, $directory);

            $user->profile_picture    = $url;
        }

        $user->first_name         = $request->first_name;
        $user->last_name          = $request->last_name;
        $user->email              = $request->email;
        $user->position           = $request->position;
        $user->role_id            = $request->role_id;
        $user->save();

        // UserLocation::whereUserId($id)->update([
        //     'country_code' => $request->country_code,
        //     'city_id' => $request->city_id
        // ]);

        UserLocation::updateOrCreate(
            ['user_id' => $id],
            [
                'country_code' => $request->country_code,
                'city_id' => $request->city_id
            ]
        );

        return ok(__('Team member updated successfully!'), $user);
    }

    public function deleteMember(Request $request)
    {

        $this->validate($request, [
            'user_id'           =>  'required|exists:users,id',
            'assign'            =>   'required|boolean',
            'new_user_id'       =>  'required_if:assign,==,1|exists:users,id',
        ]);

        $user = User::find($request->user_id);
        if (!$user) {
            return error(__('User does not exists'), [], 'validation');
        }

        $verify = $user->is_email_verified;

        if ($request->assign == 0) {
            transferOwnership($request->user_id);
        } else {
            if ($request->user_id == $request->new_user_id) {
                return error(__('Can not assign to same user'), [], 'validation');
            }
            transferOwnership($request->user_id, 1, $request->new_user_id);
        }

        $user->delete();

        UserLocation::where('user_id', $request->user_id)->delete();

        if ($verify == null) {
            return ok(__('Team member has been removed successfully!'));
        }

        if ($request->assign == 0) {
            return ok(__('Team member has been removed and all related files and contacts have been assigned to you successfully!'));
        } else {
            $user = User::find($request->new_user_id);
            return ok(__('Team member has been removed and all related files and contacts have been assigned to ' . $user->first_name . ' ' . $user->last_name .  ' successfully!'));
        }
    }

    public function updateRole(Request $request, $id)
    {
        $this->validate($request, [
            'role_id' => 'required|exists:roles,id',
        ]);

        // return auth()->user();
        /* User can not update his own role */
        $roleDetail = Role::where('id', $request->role_id)->first();

        if (auth()->user()->id == $id) {
            return error(__('You cant update the role!'), [], 'validation');
        }
        $role = User::with('role')->find(auth()->user()->id)->role->name;

        if ($role == 'Super Admin' || $role == 'Admin') {
            $user             = User::find($id);
            if (!$user) {
                return error(__('User not found'), [], 'validation');
            }
            $user->role_id    = $request->role_id;
            $user->save();
            /*Sent notification when update the role of team member */
            $usersName = $role == 'Super Admin' ? 'Owner' : auth()->user()->first_name . ' ' . auth()->user()->last_name;

            $notification = [
                'sender_id'  => auth()->user()->id,
                'receiver_id' => $id,
                'title'      => $usersName . ' has changed your role to ' . $roleDetail->name,
                'icon'       => 'team.svg',
            ];
            if ($role == 'Super Admin') {
                SystemNotification::create($notification);
            } else {

                $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has changed ' . $user->first_name . ' ' . $user->last_name . ' role to ' . $roleDetail->name;
                $type    = 'team.svg';
                sentNotification($message, $type);
                SystemNotification::create($notification);
            }

            return ok(__('Team member role update successfully!'), $user);
        } else {
            return error(__('You cant update the role!'), [], 'validation');
        }
    }

    public function createPassword(Request $request, $id)
    {
        $this->validate($request, [
            'password' => 'required|same:confirm_password',
            'confirm_password' => 'required',
        ]);

        // return auth()->user();

        // return 11;

        $user = User::findorfail($id)->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'password' => Hash::make($request->password),
        ]);

        return ok(__('Password created successfully!'), $user);
    }
    /*Team member view profile */
    public function viewMember($id)
    {
        // $user = User::with('userDetail','company','company.offices','company.certifications')->find($id);
        $user = User::with('userDetail', 'company', 'userCertifications', 'userLocations.country_details', 'userLocations.city_details', 'userLanguages', 'userLanguages.language:id,name', 'company.certifications', 'role:id,name')->find($id);
        return ok(__('User detail'), $user);
    }
    /* Get Team member */
    public function getMember()
    {
        $users = User::select('id', 'role_id', 'first_name', 'last_name', 'email')->where('id', '!=', auth()->user()->id)->where('company_id', auth()->user()->company_id)->orderBy('created_at','asc')->get();
        $users->load('role:id,name');
        return ok(__('Users'), $users);
    }
    public function activeMember()
    {
        $role  = Role::where('name', config('constants.super_admin'))->first();

        $users = User::select('id', 'role_id', 'first_name', 'last_name', 'email')->where('role_id', '!=', $role->id)->where('id', '!=', auth()->user()->id)->where('onboarding_status', 'CO')->where('company_id', auth()->user()->company_id)->get();
        //$users = User::select('id', 'role_id', 'first_name', 'last_name', 'email')->where('onboarding_status', 'CO')->where('company_id', auth()->user()->company_id)->get();
        $users->load('role:id,name');
        return ok(__('Users'), $users);
    }
    /*users list with logged user */
    public function allMembers()
    {
        $users = User::select('id', 'role_id', 'first_name', 'last_name', 'email')->where('onboarding_status', 'CO')->where('company_id', auth()->user()->company_id)->get();
        $users->load('role:id,name');
        return ok(__('Users'), $users);
    }
}
