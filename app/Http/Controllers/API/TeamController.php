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
    /**
     * add team member api
     *
     * @param  mixed $request
     * @return void
     */
    public function addMember(Request $request)
    {
        //Validation
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
            //Image store in s3 bucket
            $url = uploadFile($file, $directory);
        }

        $token = Str::random(20);
        //Create User
        $user = User::create($request->only('first_name', 'last_name', 'email', 'password', 'role_id', 'position')
            + [
                'company_id'                => auth()->user()->company_id,
                'password'                  => Hash::make(Str::random(10)),
                'profile_picture'           => $url,
                'verification_token'        => $token,
                'onboarding_status'         => "I",// I=Invited
                'verification_token_expiry' => date('Y-m-d H:i:s', strtotime("+48 hours"))
            ]);
        //Create User location
        UserLocation::create($request->only('country_code', 'city_id')
            + ['user_id' => $user->id, 'created_by' => auth()->user()->id]);

        $companyName = $user->company->name;

        $authUser = auth()->user();
        // Send Invitaion mail to team member
        Mail::to($user->email)->send(new InvitationMail($authUser, $token, $user, $companyName));

        return ok(__('Invitation sent to the added team member successfully!'), $user);
    }

    /**
     * list team member api
     *
     * @param  mixed $request
     * @return void
     */
    public function listMembers(Request $request)
    {
        // Validation
        $this->validate($request, [
            'sort_by'    =>  'nullable|in:asc,desc',
        ]);

        $query = User::with('role', 'location.country', 'location.city')->where('company_id', auth()->user()->company_id);

        // Search Filter
        if ($request->search) {
            $search = "%{$search}%";

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

        // if ($request->sort_by) {
        //     $query->orderBy('created_at', $request->sort_by);
        // }

        // Pagination and shorting filter
        $result = filterSortPagination($query);
        $users = $result['query']->get();
        $count  = $result['count'];

        $loggedUser = auth()->user()->load('role', 'location.country', 'location.city');

        if ($users->where('id', auth()->user()->id)->count() > 0) {
            $users = $users->where('id', '!=', auth()->user()->id);
            $users = collect($users)->prepend($loggedUser);
        }

        return ok(__('Team members list'), [
            'users' => $users,
            'count' => $count,
        ]);
    }

    /**
     * update team member api
     *
     * @param  mixed $request
     * @return void
     */
    public function updateMember(Request $request, $id)
    {
        //Validation
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

        $user = User::findorfail($id);

        if ($request->file('profile_picture')) {
            /*Store image in s3 bucket */
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
        // Update user info
        $user->save();
        //Update or create user location
        UserLocation::updateOrCreate(
            ['user_id' => $id],
            [
                'country_code' => $request->country_code,
                'city_id' => $request->city_id
            ]
        );

        return ok(__('Team member updated successfully!'), $user);
    }

    /**
     * delete team member api
     *
     * @param  mixed $request
     * @return void
     */
    public function deleteMember(Request $request)
    {
        //Validation
        $this->validate($request, [
            'user_id'     => 'required|exists:users,id',
            'assign'      => 'required|boolean',
            'new_user_id' => 'required_if:assign,==,1|exists:users,id',
        ]);

        $user = User::find($request->user_id);
        if (!$user) {
            return error(__('User does not exists'), [], 'validation');
        }

        $verify = $user->is_email_verified;

        // If assign 0 then Transfer all functionality rights to super admin when delete team member otherwise transfer all rights to selected users
        if ($request->assign == 0) {
            transferOwnership($request->user_id);
        } else {
            if ($request->user_id == $request->new_user_id) {
                return error(__('Can not assign to same user'), [], 'validation');
            }
            transferOwnership($request->user_id, 1, $request->new_user_id);
        }
        // Delete User
        $user->delete();
        // Delete Locations
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

    /**
     * update role api
     *
     * @param  mixed $request, $id
     * @return void
     */
    public function updateRole(Request $request, $id)
    {
        //Validation
        $this->validate($request, [
            'role_id' => 'required|exists:roles,id',
        ]);

        /* User can not update his own role */
        $roleDetail = Role::where('id', $request->role_id)->first();

        if (auth()->user()->id == $id) {
            return error(__('You cant update the role!'), [], 'validation');
        }
        $notificationQuery = SystemNotification::query();
        $userQuery = User::query();
        $role = (clone $userQuery)->with('role')->find(auth()->user()->id)->role->name;

        if ($role == config('constants.super_admin') || $role == config('constants.admin')) {
            $user = (clone $userQuery)->find($id);
            if (!$user) {
                return error(__('User not found'), [], 'validation');
            }
            $user->role_id    = $request->role_id;
            $user->save();
            /*Sent notification when update the role of team member */
            $usersName = $role == config('constants.super_admin') ? 'Owner' : auth()->user()->first_name . ' ' . auth()->user()->last_name;

            $notification = [
                'sender_id'   => auth()->user()->id,
                'receiver_id' => $id,
                'title'       => $usersName . ' has changed your role to ' . $roleDetail->name,
                'icon'        => 'team.svg',
            ];
            if ($role == config('constants.super_admin')) {
                (clone $notificationQuery)->create($notification);
            } else {

                $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has changed ' . $user->first_name . ' ' . $user->last_name . ' role to ' . $roleDetail->name;
                $type    = 'team.svg';
                sentNotification($message, $type);
                (clone $notificationQuery)->create($notification);
            }

            return ok(__('Team member role update successfully!'), $user);
        } else {
            return error(__('You cant update the role!'), [], 'validation');
        }
    }

    /**
     * create password api
     *
     * @param  mixed $request, $id
     * @return void
     */
    public function createPassword(Request $request, $id)
    {
        //Validation
        $this->validate($request, [
            'password'         => 'required|same:confirm_password',
            'confirm_password' => 'required',
        ]);

        // Find user and update new password
        $user = User::findorfail($id)->update([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'password'   => Hash::make($request->password),
        ]);

        return ok(__('Password created successfully!'), $user);
    }

    /**
     * Team member view profile api
     *
     * @param  mixed $request, $id
     * @return void
     */
    public function viewMember($id)
    {
        $user = User::with('userDetail', 'company', 'userCertifications', 'userLocations.country_details', 'userLocations.city_details', 'userLanguages', 'userLanguages.language:id,name', 'company.certifications', 'role:id,name')->find($id);
        return ok(__('View Team Member Detail'), $user);
    }

    /**
     * Get Team member api
     *
     * @param  mixed $request, $id
     * @return void
     */
    public function getMember()
    {
        $users = User::select('id', 'role_id', 'first_name', 'last_name', 'email')->where('id', '!=', auth()->user()->id)->where('company_id', auth()->user()->company_id)->orderBy('created_at', 'asc')->get();
        $users->load('role:id,name');
        return ok(__('Get Team Members'), $users);
    }

    /**
     * Get active member api
     *
     * @param  mixed $request, $id
     * @return void
     */
    public function activeMember()
    {
        $role  = Role::where('name', config('constants.super_admin'))->first();

        $users = User::select('id', 'role_id', 'first_name', 'last_name', 'email')->where('role_id', '!=', $role->id)->where('id', '!=', auth()->user()->id)->where('onboarding_status', 'CO')->where('company_id', auth()->user()->company_id)->get();
        $users->load('role:id,name');
        return ok(__('Active Members list'), $users);
    }

    /**
     * users list with logged user api
     *
     * @param  mixed $request, $id
     * @return void
     */
    public function allMembers()
    {
        $users = User::select('id', 'role_id', 'first_name', 'last_name', 'email')->where('onboarding_status', 'CO')->where('company_id', auth()->user()->company_id)->get();
        $users->load('role:id,name');
        return ok(__('All Team Members List'), $users);
    }
}
