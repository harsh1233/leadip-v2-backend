<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mail;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Auth, Hash, Redirect};
use App\Models\{User, PasswordReset, Company, ModulePermissionRole, Module, Role};
use App\Mail\{ForgotPasswordMail, VerificationMail};
use Laravel\Socialite\Facades\Socialite;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * user register(sign-up) api and send user verification mail api
     *
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $this->validate($request, [
            'first_name'         => 'required|min:3|max:30|regex:/^[\pL\s\-]+$/u',
            'last_name'          => 'required|min:3|max:30|regex:/^[\pL\s\-]+$/u',
            'email'              => 'required|unique:users|email',
            'organization_name'  => 'required|max:64|unique:companies,name',
            'password'           => [
                'required',
                Password::min(8)->letters()->numbers()->mixedCase()->symbols(), 'max
                                        :32'
            ],
        ]);

        // create company
        $company = Company::create(['name' => $request->organization_name]);

        $token = Str::random(20);

        $request['password']                    = Hash::make($request->password);
        $request['role_id']                     = Role::where('name', 'Super Admin')->first()->id;
        $request['company_id']                  = $company->id;
        $request['verification_token']          = $token;
        $request['verification_token_expiry']   = date('Y-m-d H:i:s', strtotime("+48 hours"));
        $request['is_email_verified']           = null;
        $request['onboarding_status']           = "I";

        // create user
        $user = User::create($request->only('first_name', 'last_name', 'email', 'password', 'role_id', 'company_id', 'verification_token', 'verification_token_expiry', 'is_email_verified', 'onboarding_status'));
        /*Store user_id in company table */
        $company->update(['user_id' => $user->id]);
        $user->load('company:id,name');
        $user->load('role:id,name');
        // send mail user verification token
        Mail::to($user->email)->send(new VerificationMail($user, $token));

        // check user Authenticate or not
        if (Auth::loginUsingId($user->id)) {
            $success['token'] =  $user->createToken('auth-token')->plainTextToken;
            $success['user']  =  $user;
            return ok(__('User registered successfully!'), $success);
        } else {
            return error(__('Error in registering user!!'));
        }
    }

    /**
     * user verification api
     *
     * @param  mixed $request
     * @return void
     */
    public function userVerification(Request $request)
    {
        $this->validate($request, [
            'email'  => 'required|email',
            'token'  => 'required'
        ]);
        $data = [];
        $user = User::where('email', $request->email)->where('verification_token', $request->token)->first();

        if (empty($user)) {
            return error(__('Email id or token is invalid!'), [], 'validation');
        }
        $user->load('role:id,name');
        if (!empty($user->is_email_verified)) {
            return error(__('Email is already verified!'), [], 'validation');
        } else {

            // user verification token expiry
            if ($user->verification_token_expiry < date('Y-m-d H:i:s')) {
                return error(__('The verification link has expired!'), [], 'validation');
            }
            // active user verification
            $user->update([
                'is_email_verified'  => Carbon::now(),
                'onboarding_status'  => 'YP',
                // 'verification_token' => ''
            ]);

            $data['token']           = $user->createToken('auth-token')->plainTextToken;
            $data['user']            = $user;
            $data['user_details']    = $user->userDetail;
            $data['Company']         = $user->load('company:id,name');

            return ok(__('Your email is verified!'), $data);
        }
    }

    /**
     * user login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $this->validate($request, [
            'email'        => 'required|email|exists:users',
            'password'     => 'required',
        ], [
            'email.exists' => 'Please enter registered email'
        ]);

        $user = User::where('email', $request->email)->first();
        if ($user->deleted_at != null) {
            return error(__('Your account has been deleted'), [], 'validation');
        }
        // check user verify or not
        if (!$user->is_email_verified) {
            if ($user->company->user_id == $user->id) {
                return error(__('Please verify your email in order to sign in to the platform!'), [], 'validation'); // company
            } else {
                return error(__('Please accept an invitation in order to login!'), [], 'validation'); // team
            }
        }

        // check user Authenticate or
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user()->load('company:id,name,email,phone,website,services,expertises,regions', 'role:id,name');
            $success['token'] =  $user->createToken('api-token')->plainTextToken;
            $success['user']  =  $user;

            //$moduleCode        =ModulePermissionRole::whereIn('role_id',[$user->role_id])->distinct('module_code')->pluck('module_code')->toArray();

            //$success['module'] = Module::with('permissions')->get()->pluck('permissions.*.module_code','permission_code');
            /*Get Module wise permission */
            $modules = Module::with('permissions')->get();
            foreach ($modules as $module) {
                $permissions = [];
                foreach ($module->permissions as $permission) {
                    //dd($permission->permission_code);
                    $permissions[$permission->permission_code] = $permission->has_access;
                }

                unset($module->permissions);
                $module->permissions = (object) $permissions;
            }
            $success['module'] = $modules;
            sentUserNotification();
            return ok(__('Signed in successfully!'), $success);
        } else if ($user->social_type != null) {
            /* Check user singed up using social media */
            $full_name = '';
            if ($user->social_type == 'L') {
                $full_name = 'Linked in';
            }
            if ($user->social_type == 'F') {
                $full_name = 'Facebook';
            }
            if ($user->social_type == 'G') {
                $full_name = 'Google';
            }
            return error(__("You have already signed up through $full_name with this email, please try signing up through $full_name"), [], 'validation');
        } else {
            return error(__('The credentials are invalid!'), [], 'validation');
        }
    }

    /**
     * user logout api
     *
     * @param  mixed $request
     * @return void
     */
    public function logout(Request $request)
    {
        // Get bearer token from the request
        $accessToken = $request->bearerToken();

        // Get access token from database
        $token = PersonalAccessToken::findToken($accessToken);
        if($token){
            $token->delete();
        }
        //$user = auth()->user();
        //$user->currentAccessToken()->delete();
        return ok(__('Logout Successful!'));
    }

    /**
     * user forgot password api
     *
     *
     * @param  mixed $request
     * @return void
     */
    public function forgotPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|exists:users',
        ], [
            'email.exists' => 'The entered email is unregistered!'
        ]);
        // check email is exist or not in user model
        $user = User::where('email', $request->email)->firstOrFail();
        $token = Str::random(20);
        if ($user && $user->deleted_at != null) {
            return error(__('Your account has been deleted'), [], 'validation');
        }
        if (isset($user) && $user->social_type != null) {
            $full_name = '';
            if ($user->social_type == 'L') {
                $full_name = 'Linked in';
            }
            if ($user->social_type == 'F') {
                $full_name = 'Facebook';
            }
            if ($user->social_type == 'G') {
                $full_name = 'Google';
            }
            return error(__("You can not reset your password as you have logged in through $full_name"), [], 'validation');
        }
        // delete old token for PasswordReset
        PasswordReset::where('email', $request->email)->delete();

        // create new token for PasswordReset
        PasswordReset::create([
            'email' => $user->email,
            'token' => $token
        ]);
        // Send Mail Notification
        Mail::to($user->email)->send(new ForgotPasswordMail($user, $token));

        return ok(__('Password reset link sent successfully to your registered email address!'), $user);
    }

    /**
     * user reset password api
     *
     * @param  mixed $request
     * @return void
     */
    public function resetPassword(Request $request)
    {
        $this->validate($request, [
            'email'                 => 'required|email',
            'token'                 => 'required',
            'password'              => [
                'same:password', 'required',
                Password::min(8)->letters()->numbers()->mixedCase()->symbols(), 'max:32'
            ],
            'confirm_password'      => 'required|same:password',
        ]);

        $user  = User::where('email', $request->email)->first();
        $token = PasswordReset::where('token', $request->token)->where('email', $request->email)->first();

        // check user and token exist or not
        if (!$user || !$token) {
            return error(__('User/Password reset token not found.'), [], 'validation');
        }
        $user->update([
            'password'   => Hash::make($request->password)
        ]);
        // delete old token for PasswordReset
        PasswordReset::where('email', $request->email)->delete();
        return ok(__('Password reset successfully!'));
    }

    /**
     * change Password api
     *
     * @param  mixed $request
     * @return void
     */
    public function changePassword(Request $request)
    {
        $this->validate($request, [
            'old_password'          => 'required',
            'new_password'          => [
                'required', 'min:8',
                Password::min(8)->letters()->numbers()->mixedCase()->symbols(), 'max:32'
            ],
            'confirm_new_password'  => 'same:new_password|required',
            'is_force_logout'       => 'nullable|boolean'
        ]);

        // check old password
        if (!Hash::check($request['old_password'], Auth::user()->password)) {
            return error(__('The old password does not match our records!'), [], 'validation');
        }

        // Updating user new password
        $user = User::where('id', auth()->user()->id)->first();

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Auth::logout();

        // $request->session()->invalidate();

        // $request->session()->regenerateToken();

        if($request->is_force_logout)
        {
            //Auth::guard('api')->logoutOtherDevices($request->new_password);
            //auth('sanctum')->user()->tokens()->delete();
            auth()->user()->tokens()->delete();
            //$user = $request->user();
            //PersonalAccessToken::where('tokenable_id', $user->id) ->where('tokenable_type', get_class($user))->delete();
        }

        //Auth::user()->tokens()->delete();
        // auth()->guard('api')->logout();
        // Auth::logoutOtherDevices($request->new_password);


        /* OLD MAHESH CODE : Logout from all devices */
        // if ($request->is_force_logout) {

        // $accessToken = $request->bearerToken();
        // Auth::user()->tokens->each(function ($token, $key) {
        //     $token->delete();
        // });
        // $token       = PersonalAccessToken::findToken($accessToken);
        //                PersonalAccessToken::whereIn('tokenable_id',[$token->tokenable_id])->delete();
        // }

        return ok(__('Password changed successfully!'), $user);
    }

    /**
     * get authentication users company detail API
     *
     * @param  mixed $request
     * @return void
     */
    public function getCompanyDetail(Request $request)
    {
        //$company = auth()->user()->company;
        $user = User::with('company.offices.country_details', 'company.offices.city_details')->select('id', 'first_name', 'last_name', 'company_id')->find(auth()->user()->id);
        return ok(__('Compnay detail of authentication user!'), $user);
    }

    /**
     * Checks uniqueness of organization name and email
     */
    public function checkUniqueFields(Request $request)
    {
        $request->validate([
            'email'              => 'nullable|email',
            'organization_name'  => 'nullable|max:64|unique:companies,name',
        ] + [
            'email.unique' => 'Email should be unique',
            'organization_name.unique' => 'Email should be unique',
        ]);
        $user = User::where('email', $request->email)->first();
        if (isset($user) && $user->social_type != null) {
            $full_name = '';
            if ($user->social_type == 'L') {
                $full_name = 'Linked in';
            }
            if ($user->social_type == 'F') {
                $full_name = 'Facebook';
            }
            if ($user->social_type == 'G') {
                $full_name = 'Google';
            }
            if ($user->deleted_at != null) {
                return error(__('The email has already been taken.'), [], 'validation');
            }
            return error(__("You have already signed up through $full_name with this email, please try signing up through $full_name"), [], 'validation');
        }
        if ($user) {
            return error(__('The email has already been taken.'), [], 'validation');
        }
        return ok(__('Unique field verified successfully!'));
    }

    /* Check token */
    public function checkToken(Request $request)
    {

        $this->validate($request, [
            'email'                 => 'required|email',
            'token'                 => 'required'
        ]);
        $user  = User::where('email', $request->email)->first();
        $token = PasswordReset::where('token', $request->token)->where('email', $request->email)->first();

        // check user and token exist or not
        if (!$user || !$token) {
            return error(__('User/Password reset token not found.'), [], 'validation');
        }
        return ok(__('ok'));
    }
}
