<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\{Auth, Hash, Redirect};
use App\Models\{Company, User, UserDetail, UserLocation, CompanyOffice, Role, UserLanguage};
use App\Mail\{InvitationMail};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Models\SystemNotification;
use App\Models\Industry;
use App\Models\PreferenceIndustry;
use App\Models\PreferenceCountry;
use App\Models\PreferenceAgent;
use App\Models\Module;

class UserController extends Controller
{
    /**
     * add company information api
     * Onboarding step 1
     * @param  mixed $request
     * @return void
     */
    public function aboutCompanyUpdate(Request $request)
    {
        //Validation
        $this->validate($request, [
            'organization_name' => 'nullable', // this filed will be used for social media signup user
            'country_code'      => 'required|exists:countries,code',
            'city_id'           => 'required|exists:cities,id',
            'website'           => 'required',
            'email'             => 'required|email'
        ]);

        $user = User::with('company.offices')->where('id', auth()->user()->id)->first();
        // store company information
        if (isset($request->organization_name)) {
            $company = Company::create([
                'name'      => $request->organization_name,
                'user_id'   => auth()->user()->id,
                'email'     => $request->email,
                'website'   => $request->website
            ]);
            $user->update(['company_id' => $company->id]);
        } else {
            $user->company->updateOrCreate(
                ['id'        => $user->company->id],
                [
                    'email'     =>  $request->email,
                    'website'    =>  $request->website
                ]
            );
        }

        // update company office information
        if ($user->company->offices) {
            $user->company->offices()->updateOrCreate(
                [
                    'id'  => $user->company->offices->first() ? $user->company->offices()->first()->id : null
                ],

                [
                    'country_code' => $request->country_code,
                    'city_id' => $request->city_id,
                ]
            );
        }
        // else {
        //     CompanyOffice::create([
        //         'company_id'    => $user->company->id,
        //         'country_code'  => $request->country_code,
        //         'city_id'       => $request->city_id,
        //     ]);
        // }

        // After completing first step, user status should be update to AT
        $newStatus = 'AT';

        if ($user->onboarding_status == 'SC') {
            $newStatus = 'SC';
        }
        $user->update(['onboarding_status' => $newStatus]);

        $user->load('company.offices');
        return ok(__('Company registered successfully!'), $user);
    }

    /**
     * update company details api
     *
     * @param  mixed $request
     * @return void
     */
    public function update(Request $request)
    {
        //Validation
        $this->validate($request, [
            'profile_picture'                         =>  'nullable|mimes:png,jpg,jpeg|max:5120',
            'first_name'                              =>  'required|min:3|max:30|regex:/^[\pL\s\-]+$/u',
            'last_name'                               =>  'required|min:3|max:30|regex:/^[\pL\s\-]+$/u',
            'company'                                 =>  'required|max:64',
            'position'                                =>  'required|max:64',
            'point_of_contact'                        =>  'nullable|in:M,L,HR,F,BD', //('M:Management, L:Legal, HR, F:Finance, BD:Bisiness Development');
            'email'                                   =>  'required|email',
            'phone_number'                            =>  'required|min:12|numeric',
            'location'                                =>  'required|array',
            'location.*.address'                      =>  'required',
            'location.*.country_code'                 =>  'required|exists:countries,code',
            'location.*.city_id'                      =>  'required|exists:cities,id',
            "expertises"                              =>  'required|array',
            "interests"                               =>  'required|array',
            'description'                             =>  'nullable|string|max:250',
            "languages"                               =>  'nullable|array',
            "languages.*.language_id"                 =>  'nullable|exists:languages,id', //Todo : add validation
            "languages.*.proficiency_level"           =>  'nullable|in:NP,PP,FP', // NP,PP,FP
            'whatsapp_number'                         =>  'nullable',
            'linkedin_profile'                        =>  'nullable|url',
            'facebook_profile'                        =>  'nullable|url',
            'other_profile'                           =>  'nullable|url',
            'extra_channels'                          =>  'nullable|array',
            "certifications"                          =>  'nullable|array',
            "certifications.*.name"                   =>  'nullable|max:100',
            "certifications.*.issuing_organization"   =>  'nullable|max:64',
            "certifications.*.issue_date"             =>  'nullable|date_format:Y-m-d H:i:s',
        ], [
            'profile_picture.max' => 'The profile picture must not be greater than 5 MB.'
        ]);

        //$user = User::findOrFail($request->user_id);
        $user = auth()->user();
        if ($request->profile_picture) {
            $file = $request->profile_picture;
            $directory = 'profile/' . auth()->user()->id;
            $profile_picture = uploadFile($file, $directory);
            // TODO: Temporary fixes
            $user->update([
                'profile_picture'  =>  $profile_picture,
            ]);
        }

        $user->update([
            'first_name'       =>  $request->first_name,
            'last_name'        =>  $request->last_name,
            'position'         =>  $request->position,
        ]);

        // add company information
        $user->company->update([
            'name'  =>  $request->company,
        ]);

        $extra_channels = NULL;
        if ($request->input('extra_channels')) {
            $extra_channels = serialize($request->input('extra_channels'));
        }

        // create or update user details
        $userDetail = UserDetail::updateOrCreate([
            'user_id'           =>  $user->id,
        ], [
            'description'       =>  $request->description,
            'point_of_contact'  =>  $request->point_of_contact,
            'interests'         =>  $request->interests,
            'email'             => $request->email,
            'phone_number'      => $request->phone_number,
            'whatsapp_number'   => $request->whatsapp_number,
            'linkedin_profile'  => $request->linkedin_profile,
            'facebook_profile'  => $request->facebook_profile,
            'other_profile'     => $request->other_profile,
            'extra_channels'    => $extra_channels,
            'expertises'        =>  $request->expertises,
        ]);

        //create new location
        if (isset($request['location']) && count($request['location']) > 0) {

            // delete old location
            $user->userLocations()->delete();

            foreach ($request['location'] as $location) {

                $user->userLocations()->create([
                    'address'       => $location['address'],
                    'country_code'  => $location['country_code'],
                    'city_id'       => $location['city_id'],
                ]);
            }
        }

        //create new languages
        if (isset($request['languages']) && count($request['languages']) > 0) {

            // delete old languages
            $user->userLanguages()->delete();

            foreach ($request['languages'] as $language) {

                $user->userLanguages()->create([
                    'language_id'           => $language['language_id'] ?? null,
                    'proficiency_level'     => $language['proficiency_level'] ?? null,
                ]);
            }
        }

        //create new certifications
        if (isset($request['certifications']) && count($request['certifications']) > 0) {

            // delete old certifications
            $user->userCertifications()->delete();

            foreach ($request['certifications'] as $certification) {

                $user->userCertifications()->create([
                    'name'                  => $certification['name'] ?? '',
                    'issuing_organization'  => $certification['issuing_organization'] ?? '',
                    'issue_date'            => $certification['issue_date'] ??  null,
                ]);
            }
        }
        //sent profile updated notification
        sentUserNotification();
        return ok(__('profile updated successfully!'), [
            'user'          =>  $user,
            'user_detail'   =>  $userDetail
        ]);
    }

    /**
     * add team members api
     *
     * @param  mixed $request
     * @return void
     */
    public function addTeam(Request $request)
    {
        /*Check unique email */
        $requestEmails = array_column($request->team, 'email');
        array_push($requestEmails, auth()->user()->email);
        if (count(array_unique($requestEmails)) < count($requestEmails)) {
            return error(__('Email address has already been taken'), [], 'validation');
        }
        //Validation
        $this->validate($request, [
            'team'                   => 'nullable|array',
            'is_team_record_updated' => 'nullable|boolean',
            'team.*.first_name'      => 'nullable|min:3|max:30|regex:/^[\pL\s\-]+$/u',
            'team.*.last_name'       => 'nullable|min:3|max:30|regex:/^[\pL\s\-]+$/u',
            'team.*.email'           => 'nullable|email',
            'team.*.role'            => 'nullable|exists:roles,id',
        ]);
        $userQuery = User::query();
        $exitsEmail = (clone $userQuery)->whereIn('email', $requestEmails)->where('company_id', '!=', auth()->user()->company_id)->pluck('email')->toArray();
        if (!empty($exitsEmail)) {
            $email = implode(",", $exitsEmail);
            return error(__($email . ' email has already been taken. '), [], 'validation');
        }
        $users = [];
        if (isset($request['team']) && count($request['team']) > 0) {

            foreach ($request['team'] as $member) {
                // create users
                $invitedUser = (clone $userQuery)->updateOrCreate(
                    [
                        'email'                 =>  $member['email'] ?? '',
                    ],
                    [
                        'first_name'            =>  $member['first_name'] ?? '',
                        'last_name'             =>  $member['last_name'] ?? '',
                        'email'                 =>  $member['email'] ?? '',
                        'role_id'               =>  $member['role'] ? $member['role'] : null,
                        'company_id'            =>  auth()->user()->company_id,
                        'is_email_verified'     =>  Carbon::now(),
                        'onboarding_status'     =>  "I", // I = Invited
                    ]
                );

                $token = strtolower(str()->random(16));

                if ($invitedUser->updated_by == NULL) {
                    //Update user verification token and token expiry time
                    $invitedUser->update(["verification_token" => $token, "verification_token_expiry" => date('Y-m-d H:i:s', strtotime("+48 hours"))]);
                    //send invitation mail to team member
                    Mail::to($invitedUser->email)->send(new InvitationMail(auth()->user(), $token, $invitedUser, auth()->user()->company->name));
                    sleep(1);
                }
                $users[] = $invitedUser;
            }

            (clone $userQuery)->where('email', '!=', auth()->user()->email)
                ->where('company_id', auth()->user()->company_id)
                ->whereNotIn('email', $requestEmails)
                ->delete();
        } else {
            (clone $userQuery)->where('email', '!=', auth()->user()->email)
                ->where('company_id', auth()->user()->company_id)
                ->delete();
        }

        // After completing secound step (Team), user status should be update to SC
        auth()->user()->update(['onboarding_status' => 'SC']);
        if (isset($request['team']) && count($request['team']) > 0) {
            if (!$request->is_team_record_updated) {
                return ok(__('Invitation sent to the added team members successfully!'), auth()->user());
            } else {
                return ok('', auth()->user());
            }
        } else {
            return ok('', auth()->user());
        }
    }

    /**
     * user invitation and create password api
     *
     * @param  mixed $request
     * @return void
     */
    public function createPassword(Request $request) // Todo : changes as requried front end
    {
        $this->validate($request, [
            'first_name'        => 'required|min:3|max:30|regex:/^[\pL\s\-]+$/u',
            'last_name'         => 'required|min:3|max:30|regex:/^[\pL\s\-]+$/u',
            'password'          => [
                'same:password', 'required',
                Password::min(8)->letters()->numbers()->mixedCase()->symbols(), 'max:32'
            ],
            'confirm_password'  => 'required|same:password',
        ]);

        $user = User::find($request->user_id);
        if (!$user) {
            return error(__('User is not exist'), [], 'validation');
        }
        // active user verification
        $user->update([
            'first_name'         => $request->first_name,
            'last_name'          => $request->last_name,
            'password'           => Hash::make($request->password),
            'is_email_verified'  => Carbon::now(),
            'verification_token' => null,
            'onboarding_status'  => "CO"
        ]);

        $message = $request->first_name . ' ' . $request->last_name . ' has recently joined the ' . $user->company->name . "'s network";
        $role    = Role::where('name', config('constants.super_admin'))->first();
        $admin   = User::where('company_id', $user->company_id)->where('role_id', $role->id)->first();
        $notification = [
            'sender_id'  => $user->id,
            'receiver_id' => $admin->id,
            'title'      => $message,
            'icon'       => 'profile.svg',
        ];
        SystemNotification::create($notification);
        return ok(__('Password created successfully!'), $user);
    }

    /**
     * update user sync Contact api
     *
     * @param  mixed $request
     * @return void
     */
    public function enterLeadIp(Request $request)
    {
        $user = User::where('id', auth()->user()->id)->first();
        if(!$user)
        {
            return error(__('User not found!'), [], 'validation');
        }
        // After completing third step (sync contact), user status should be update to CO
        $user->update([
            'is_onboarded'          => true,
            'onboarding_status'     => 'CO'
        ]);

        $user->load('role:id,name');
        /*Set Roles and permissions */
        $modules = Module::with('permissions')->get();
        foreach ($modules as $module) {
            $permissions = [];
            foreach ($module->permissions as $permission) {
                $permissions[$permission->permission_code] = $permission->has_access;
            }
            // remove old permissions object and add new permissions object
            unset($module->permissions);
            $module->permissions = (object) $permissions;
        }
        $user['module'] = $modules;
        return ok(__('Welcome to Leadip!'), $user);
    }

    /**
     * update Onboard Status api
     *
     * @param  mixed $request
     * @return void
     */
    public function updateOnboardStatus(Request $request) // TODO: "not use this api"
    {
        $request->validate([
            'onboarding_status'     => 'required|in:YC,AT,SC',
            'is_onboarded'          => 'required|boolean'
        ]);

        $user = auth()->user();
        $user->update(['is_onboarded' => $request->is_onboarded, 'onboarding_status' => $request->onboarding_status]);

        return ok(__('Onboarding status updated successfully!'));
    }

    /**
     * update user profile api
     *
     * @param  mixed $request
     * @return void
     */
    public function userProfile(Request $request) // TODO: "not use this api"
    {
        $this->validate($request, [
            'profile_picture'   =>  'nullable|mimes:png,jpg,jpeg|max:5120',
            'first_name'        =>  'required|min:3|max:30|regex:/^[\pL\s\-]+$/u',
            'last_name'         =>  'required|min:3|max:30|regex:/^[\pL\s\-]+$/u',
            'company'           =>  'required|max:64',
            'description'       =>  'nullable|string|max:250',
            'position'          =>  'nullable',
            'point_of_contact'  =>  'nullable|in:M,L,HR,F,BD', //('M:Management, L:Legal, HR, F:Finance, BD:Bisiness Development');
        ], [
            'profile_picture.max' => 'The profile picture must not be greater than 5 MB.'
        ]);

        $user = auth()->user();
        if ($request->profile_picture) {
            $file = $request->profile_picture;
            $directory = 'profile/' . auth()->user()->id;
            $profile_picture = uploadFile($file, $directory);
            // TODO: Temporary fixes
            $user->update([
                'profile_picture'  =>  $profile_picture,
            ]);
        }

        $user->update([
            'first_name'       =>  $request->first_name,
            'last_name'        =>  $request->last_name,
            'position'         =>  $request->position,
        ]);

        // add company information
        $user->company->update([
            'name'  =>  $request->company,
        ]);

        // create or update user details
        $userDetail = UserDetail::updateOrCreate([
            'user_id'           =>  $user->id,
        ], [
            'description'       =>  $request->description,
            'point_of_contact'  =>  $request->point_of_contact,
        ]);

        return ok(__('profile updated successfully!'), [
            'user'          =>  $user,
            'user_detail'   =>  $userDetail
        ]);
    }


    /**
     * update user contacts and channels api
     *
     * @param  mixed $request
     * @return void
     */
    public function contactsAndChannels(Request $request)
    {
        $this->validate($request, [
            'email'             =>  'nullable|email',
            'phone_number'      =>  'required|min:12|numeric',
            'whatsapp_number'   =>  'nullable',
            'linkedin_profile'  =>  'nullable|url',
            'facebook_profile'  =>  'nullable|url',
            'other_profile'     =>  'nullable|url',
            'extra_channels'    =>  'nullable|array',

        ]);

        $user = auth()->user();
        $extra_channels = serialize($request->input('extra_channels'));

        // create or update user details
        $userDetail = UserDetail::updateOrCreate([
            'user_id'           => $user->id,
        ], [
            'email'             => $request->email,
            'phone_number'      => $request->phone_number,
            'whatsapp_number'   => $request->whatsapp_number,
            'linkedin_profile'  => $request->linkedin_profile,
            'facebook_profile'  => $request->facebook_profile,
            'other_profile'     => $request->other_profile,
            'extra_channels'    => $extra_channels,
        ]);
        return ok(__('contacts and channels updated successfully!'), [
            'user'          =>   $user,
            'user_detail'   =>   $userDetail
        ]);
    }

    /**
     * update user locations api
     *
     * @param  mixed $request
     * @return void
     */
    public function userLocations(Request $request) // TODO: "not use this api"
    {
        $this->validate($request, [
            'location'                  =>  'nullable|array',
            'location.*.address'        =>  'required',
            'location.*.country_code'   =>  'required|exists:countries,code',
            'location.*.city_id'        =>  'required|exists:cities,id',
        ]);

        $user = auth()->user();
        // delete old location
        $user->userLocations()->delete();

        //create new location
        if (isset($request['location']) && count($request['location']) > 0) {

            foreach ($request['location'] as $location) {

                $user->userLocations()->create([
                    'address'       => $location['address'],
                    'country_code'  => $location['country_code'],
                    'city_id'       => $location['city_id'],
                ]);
            }
        }
        return ok(__('locations updated successfully!'), $user->load('userLocations'));
    }

    /**
     * update user languages api
     *
     * @param  mixed $request
     * @return void
     */
    public function userLanguages(Request $request) // TODO: "not use this api"
    {
        $this->validate($request, [
            "languages"                     =>  'nullable|array',
            "languages.*.language_id"       =>  'nullable|exists:languages,id',
            "languages.*.proficiency_level" =>  'nullable|in:NP,PP,FP', // NP,PP,FP
        ]);

        $user = auth()->user();
        // delete old languages
        $user->userLanguages()->delete();

        //create new languages
        if (isset($request['languages']) && count($request['languages']) > 0) {

            foreach ($request['languages'] as $language) {

                $user->userLanguages()->create([
                    'language_id'           => $language['language_id'],
                    'proficiency_level'     => $language['proficiency_level'],
                ]);
            }
        }
        return ok(__('languages updated successfully!'), $user->load('userLanguages'));
    }

    /**
     * update user areas of expertise api
     *
     * @param  mixed $request
     * @return void
     */
    public function userAreasOfExpertise(Request $request) // TODO: "not use this api"
    {
        $this->validate($request, [
            "expertises"    =>  'required|array',
        ]);

        $user = auth()->user();
        // create or update user details
        $userDetail = UserDetail::updateOrCreate([
            'user_id'         => $user->id,
        ], [
            'expertises'    =>  $request->expertises,
        ]);
        return ok(__(' areas of expertise updated successfully!'), [
            'user'          =>  $user,
            'user_detail'   =>  $userDetail
        ]);
    }

    /**
     * update user interested api
     *
     * @param  mixed $request
     * @return void
     */
    public function userInterested(Request $request) // TODO: "not use this api"
    {
        $this->validate($request, [
            "interests"     =>  'required|array',
        ]);

        $user = auth()->user();
        // create or update user details
        $userDetail = UserDetail::updateOrCreate([
            'user_id'         => $user->id
        ], [
            'interests'     =>  $request->interests,
        ]);

        return ok(__('interested updated successfully!'), [
            'user'          =>  $user,
            'user_detail'   =>  $userDetail
        ]);
    }

    /**
     * update user certifications api
     *
     * @param  mixed $request
     * @return void
     */
    public function userCertification(Request $request) // TODO: not "use this api"
    {
        $this->validate($request, [
            "certifications"                          =>  'nullable|array',
            "certifications.*.name"                   =>  'nullable|max:100',
            "certifications.*.issuing_organization"   =>  'nullable',
            "certifications.*.issue_date"             =>  'nullable|date_format:Y-m-d H:i:s',
        ]);

        $user = auth()->user();
        // delete old certifications
        $user->userCertifications()->delete();
        //create new certifications
        if (isset($request['certifications']) && count($request['certifications']) > 0) {

            foreach ($request['certifications'] as $certification) {

                $user->userCertifications()->create([
                    //'user_id'               => $request->user_id,
                    'name'                  => $certification['name'] ?? '',
                    'issuing_organization'  => $certification['issuing_organization'] ?? '',
                    'issue_date'            => $certification['issue_date'] ??  date('Y-m-d H:i:s'),
                ]);
            }
        }
        return ok(__('certification updated successfully!'), $user->load('userCertifications'));
    }

    /**
     * View user profile api
     *
     * @param  mixed $id
     * @return void
     */
    public function userViewProfile($id)
    {
        $user = User::with('role:id,name', 'userDetail', 'company', 'userCertifications', 'userLocations.country_details', 'userLocations.city_details', 'userLanguages', 'userLanguages.language:id,name', 'company.offices.city_details', 'company.offices.country_details', 'company.certifications')->find($id);

        if (!$user) {
            return error(__('This user does not exist!'), [], 'validation');
        }
        return ok(__('user view profile successfully!'), $user);
    }

    /**
     * get user details using email api
     *
     * @param  mixed $request
     * @return void
     */
    public function getDetails(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->with(['company:id,name', 'role:id,name'])->first();
        if (!$user) {
            return error(__("You can't join the organization's network as the Owner has revoked the invitation"), [], 'validation');
        }
        $success['token'] =  $request->token;
        $success['user']  =  $user;

        $roleId = $user->role_id;
        $modules = Module::with('permissions')->get();
        foreach ($modules as $module) {
            $permissions = [];
            foreach ($module->permissions->where('role_id', $roleId) as $permission) {
                $permissions[$permission->permission_code] = $permission->has_access;
            }
            unset($module->permissions);
            $module->permissions = (object) $permissions;
        }
        $success['module'] = $modules;

        return ok(__('Get user details successfully'), $success);
    }

    /**
     * Profile completion criteria api
     *
     * @return void
     */
    public function profileCompletionCriteria()
    {
        $percentage = profilePercentage();
        return ok(__('Profile completion criteria'), number_format($percentage, 2));
    }

    /**
     * Set first login time false api
     *
     *
     * @return void
     */
    public function setFirstLogin()
    {
        $user = User::findOrFail(auth()->user()->id);

        $user->update([
            'is_first_time_login' => false
        ]);
        return ok(__('Status updated successfully'));
    }

    /**
     * create user Preference
     *
     * @param  mixed $request
     * @return void
     */
    public function addPreference(Request $request)
    {
        //Validation
        $this->validate($request, [
            'quality_rating'        =>  'nullable',
            'industries'            =>  'nullable|array|exists:industries,id',
            'interested_countries'  =>  'nullable|array|exists:countries,code',
            'company_size'          =>  'nullable|array',
            'revenue'               =>  'nullable',
            'positions'             =>  'nullable',
            'cases'                 =>  'nullable|array',
            'use_of_contact_data'   =>  'nullable',
            'user_id'               =>  'required|exists:users,id',
        ]);

        $input = $request->only('quality_rating', 'revenue', 'positions', 'use_of_contact_data');

        $input['user_id'] =  $request->user_id;

        $input['company_size'] = $request->input('company_size') ? serialize($request->input('company_size')) : null;

        $userDetail = UserDetail::updateOrCreate(['user_id' => $request->user_id], $input);

        if ($request->industries) {
            $companyPeople = [];
            $query = PreferenceIndustry::query();
            foreach ($request->industries as $value) {
                $companyPeople[] = [
                    'industry_id' => $value,
                    'user_detail_id' => $userDetail->id
                ];
            }
            $query->where('user_detail_id', $userDetail->id)->delete();
            $query->insert($companyPeople);
        } else {
            $query = PreferenceIndustry::query();
            $query->where('user_detail_id', $userDetail->id)->delete();
        }

        if ($request->interested_countries) {
            $companyPeople = [];
            $query = PreferenceCountry::query();
            foreach ($request->interested_countries as $value) {
                $companyPeople[] = [
                    'country_id' => $value,
                    'user_detail_id' => $userDetail->id
                ];
            }
            $query->where('user_detail_id', $userDetail->id)->delete();
            $query->insert($companyPeople);
        } else {
            $query = PreferenceCountry::query();
            $query->where('user_detail_id', $userDetail->id)->delete();
        }


        if ($request->cases) {
            $companyPeople = [];
            $query = PreferenceAgent::query();
            foreach ($request->cases as $value) {
                $companyPeople[] = [
                    'agent_id' => $value,
                    'user_detail_id' => $userDetail->id
                ];
            }
            $query->where('user_detail_id', $userDetail->id)->delete();
            $query->insert($companyPeople);
        } else {
            $query = PreferenceAgent::query();
            $query->where('user_detail_id', $userDetail->id)->delete();
        }


        User::where('id', $request->user_id)->update(['onboarding_status' => 'YC']);

        return ok(__('Your preferences have been successfully saved.'), $userDetail);
    }

    /**
     * update user Preference
     *
     * @param  mixed $request
     * @return void
     */
    public function updatePreference(Request $request)
    {
        //Validation
        $this->validate($request, [
            'quality_rating'        =>  'nullable',
            'industries'            =>  'nullable|array|exists:industries,id',
            'interested_countries'  =>  'nullable|array|exists:countries,code',
            'company_size'          =>  'nullable|array',
            'revenue'               =>  'nullable',
            'positions'             =>  'nullable',
            'cases'                 =>  'nullable|array',
            'use_of_contact_data'   =>  'nullable',
            'user_id'               =>  'required|exists:users,id',
        ]);
        $userQuery     = UserDetail::query();
        $industryQuery = PreferenceIndustry::query();
        $countryQuery  = PreferenceCountry::query();
        $agentQuery    = PreferenceAgent::query();
        // Get existing user details
        $userDetail = (clone $userQuery)->where('user_id', $request->user_id)->first();

        $input = $request->only('quality_rating', 'revenue', 'positions', 'use_of_contact_data');

        $input['company_size'] = $request->input('company_size') ? serialize($request->input('company_size')) : null;
        // If user details not exist then create details otherwise update info
        if (!$userDetail) {
            $input['user_id'] = $request->user_id;
            $userDetail = (clone $userQuery)->create($input);
            //return error(__('User Preference data not found.'), [], 'validation');
        } else {
            $userDetail->update($input);
        }

        if ($request->industries) {
            $companyPeople = [];
            foreach ($request->industries as $value) {
                $companyPeople[] = [
                    'industry_id' => $value,
                    'user_detail_id' => $userDetail->id
                ];
            }
            (clone $industryQuery)->where('user_detail_id', $userDetail->id)->delete();
            (clone $industryQuery)->insert($companyPeople);
        } else {
            (clone $industryQuery)->where('user_detail_id', $userDetail->id)->delete();
        }

        if ($request->interested_countries) {
            $companyPeople = [];
            foreach ($request->interested_countries as $value) {
                $companyPeople[] = [
                    'country_id' => $value,
                    'user_detail_id' => $userDetail->id
                ];
            }
            (clone $countryQuery)->where('user_detail_id', $userDetail->id)->delete();
            (clone $countryQuery)->insert($companyPeople);
        } else {
            (clone $countryQuery)->where('user_detail_id', $userDetail->id)->delete();
        }


        if ($request->cases) {
            $companyPeople = [];
            foreach ($request->cases as $value) {
                $companyPeople[] = [
                    'agent_id' => $value,
                    'user_detail_id' => $userDetail->id
                ];
            }
            (clone $agentQuery)->where('user_detail_id', $userDetail->id)->delete();
            (clone $agentQuery)->insert($companyPeople);
        } else {
            (clone $agentQuery)->where('user_detail_id', $userDetail->id)->delete();
        }

        return ok(__('Your preferences have been successfully updated'), $userDetail);
    }

    /**
     * list user Preference
     *
     * @param  mixed $request
     * @return void
     */

    public function userPreference()
    {
        $user = UserDetail::with('preferenceIndustries.industry', 'preferenceCountries.country', 'preferenceAgents')->where('user_id', auth()->user()->id)->first();

        if (!$user) {
            return error(__('Preference data not found.'), [], 'validation');
        }

        return ok(__('User Preference data details'), $user);
    }

    /**
     * list Industry
     *
     * @param  mixed $request
     * @return void
     */

    public function listIndustry(Request $request)
    {
        $industries = Industry::get(['id', 'name']);
        return ok(__('Industry list'), $industries);
    }

    /**
     * update user sync Contact flag api
     *
     * @param  mixed $request
     * @return void
     */
    public function syncContactStatus(Request $request)
    {
        //Validation
        $this->validate($request, [
            'sync_with_gmail'     => 'nullable|boolean',
            'sync_with_outlook'   => 'nullable|boolean',
            'sync_with_linkedin'  => 'nullable|boolean',
        ]);

        $user = User::where('id', auth()->user()->id)->first();

        if (!$user) {
            return error(__('User not found.'), [], 'validation');
        }

        // After completing third step (sync contact), user status should be update to CO
        $user->update([
            'sync_with_gmail'       =>  $request->sync_with_gmail ?? false,
            'sync_with_outlook'     =>  $request->sync_with_outlook ?? false,
            'sync_with_linkedin'    =>  $request->sync_with_linkedin ?? false
        ]);

        return ok(__('Sync status updated successfully.'), $user);
    }

    /**
     * get company and user Info api
     *
     * @param  mixed $id
     * @return void
     */
    public function userCompanyProfilePicture($id)
    {
        // Get user with company info
        $user = User::with('company:id,profile_picture')->find($id);

        if (!$user) {
            return error(__('This user does not exist!'), [], 'validation');
        }

        $data = [
            'user_picture'    => $user->profile_picture,
            'company_picture' => $user->company ? $user->company->profile_picture : null,
        ];

        return ok(__('Get user and company profile picture successfully!'), $data);
    }
}
