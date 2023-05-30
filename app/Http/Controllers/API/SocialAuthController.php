<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Module;
use App\Models\CompanyContact;
use Illuminate\Http\Request;

class SocialAuthController extends Controller
{
    /**
     * Open google login page
     *
     * @return void
     */
    public function redirectToGoogle(Request $request,  String $provider)
    {
        if (isset($request->is_sync) && ($request->is_sync) && ($provider == 'google')) {
            Session::put('syncType', $provider);
            Session::put('isSync', true);
            Session::put('isOnboarding', $request->is_onboarding_route);
            Session::put('AuthId', $request->id);
            if (isset($request->main_type) && $request->main_type) {
                if (in_array($request->main_type, ['G', 'P', 'CL'])) {
                    Session::put('mainType', $request->main_type);
                } else {
                    Session::put('mainType', 'G');
                }
            }
            return Socialite::driver($provider)->setScopes([
                "openid",
                "https://www.googleapis.com/auth/userinfo.profile",
                "https://www.googleapis.com/auth/userinfo.email",
                "https://www.googleapis.com/auth/contacts",
                "https://www.googleapis.com/auth/directory.readonly"
            ])->redirect();
        } elseif (isset($request->is_sync) && ($request->is_sync) && ($provider == 'linkedin')) {
            Session::put('syncType', $provider);
            Session::put('isSync', true);
            Session::put('isOnboarding', $request->is_onboarding_route);
            Session::put('AuthId', $request->id);
            return Socialite::driver($provider)->setScopes([
                "r_ads_reporting", "rw_organization_admin",
                "r_organization_admin", "r_basicprofile",
                "r_emailaddress", "r_liteprofile",
                "w_member_social", "openid", "profile",
                "email", "r_1st_connections_size"
            ])->redirect();
        } else {
            return Socialite::driver($provider)->redirect();
        }
        // $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        //return Socialite::driver($provider)->redirect();
        // return ok(__('Link fetched successfully'), $url);
    }

    /**
     * Login Using Google
     *
     * @return void
     */
    public function handleGoogleCallback(Request $request, String $provider)
    {
        $syncType       = Session::pull('syncType');
        $isSync         = Session::pull('isSync');
        $isOnboarding   = Session::pull('isOnboarding');
        $mainType       = Session::pull('mainType');
        $authId         = Session::pull('AuthId');

        $url = env('WEBAPP_URL');
        header('Access-Control-Allow-Origin: *');
        // Check sync request url if request from on boarding then redirect to this page otherwise redirect to contact page
        if (!empty($isOnboarding) && $isOnboarding == 'true') {
            $tabId = '';
            if ($mainType == 'P') {
                $successMessage = "Prospect(s) synced successfully";
                $errorMessage = "Prospect(s) syncing failed, please try again!";
                $syncUrl = config('constants.SYNC_PROSPECT_URL');
            } elseif ($mainType == 'CL') {
                $successMessage = "Client(s) synced successfully";
                $errorMessage = "Client(s) syncing failed, please try again!";
                $syncUrl = config('constants.SYNC_CLIENT_URL');
            } else {
                $successMessage = "Contact(s) synced successfully";
                $errorMessage = "Contact(s) syncing failed, please try again!";
                $syncUrl = config('constants.ON_BOARDING_SYNC_WEB_URL');
            }
        } else {
            $tabId = 'tabId=5&';
            if ($mainType == 'P') {
                $successMessage = "Prospect(s) synced successfully";
                $errorMessage = "Prospect(s) syncing failed, please try again!";
                $syncUrl = config('constants.SYNC_PROSPECT_URL');
            } elseif ($mainType == 'CL') {
                $successMessage = "Client(s) synced successfully";
                $errorMessage = "Client(s) syncing failed, please try again!";
                $syncUrl = config('constants.SYNC_CLIENT_URL');
            } else {
                $successMessage = "Contact(s) synced successfully";
                $errorMessage = "Contact(s) syncing failed, please try again!";
                $syncUrl = config('constants.SYNC_WEB_URL');
            }
        }

        // Authorization error should be in the "error" query param
        $error = $request->query('error');
        if (isset($error)) {
            if ($isSync == 'true') {
                return redirect()->away($syncUrl . '?' . $tabId . 'sync=false&message='.$errorMessage);
            } else {
                return redirect()->away($url . 'signin?auth_error=Authentication Fail.');
            }
        }

        try {
            $socialite_user = Socialite::driver($provider)->stateless()->user();

            // check sync type is google then using socialuser token and get all directory contacts and store in database
            if ($syncType == 'google' && ($isSync == 'true')) {
                // Set redirect page type
                $response = $this->getGmailContacts($socialite_user, $mainType, $authId, null);

                if (isset($response['success']) && $response['success']) {
                    return redirect()->away($syncUrl . '?' . $tabId . 'sync=true&message=' . $successMessage);
                } else {
                    return redirect()->away($syncUrl . '?' . $tabId . 'sync=false&message=' . $errorMessage);
                }
            }

            // check user exists or not
            $user = User::where('social_id', $socialite_user->id)->where('is_active', true)->first();

            if ($user) {
                // log them in
                $data = $this->getLoggedIn($user->id, $socialite_user->id);
                Auth()->user()->load('company:id,name,email,phone,website,services,expertises,regions', 'role:id,name');
                sentUserNotification();
            } else {
                // check if email exists
                if (User::where('email', $socialite_user->email)->first()) {
                    $social_media = User::where('email', $socialite_user->email)->first();

                    if ($social_media->deleted_at != null) {
                        return redirect()->away($url . "signin?auth_error=Your account has been deleted");
                    }
                    /* Get social media type */
                    $social_type = $social_media->social_type;
                    $full_name = '';
                    if ($social_type == 'L') {
                        $full_name = 'Linked in';
                    }
                    if ($social_type == 'F') {
                        $full_name = 'Facebook';
                    }
                    if ($social_type == 'G') {
                        $full_name = 'Google';
                    }

                    if ($full_name == null) {
                        return redirect()->away($url . "signin?auth_error=The email has already been taken");
                    }
                    // if($social_media->deleted_at != null){
                    //     return redirect()->away($url."signin?auth_error=The email has already been taken");
                    // }
                    return redirect()->away($url . "signin?auth_error=You have already signed up through $full_name with this email, please try signing  up through $full_name ");
                }
                if ($provider == 'google') {
                    $social_type = 'G';
                    $first_name = $socialite_user->user['given_name'] ?? $socialite_user->name;
                    $last_name  = $socialite_user->user['family_name'] ?? $socialite_user->name;
                }
                if ($provider == 'facebook') {
                    $social_type = 'F';
                    $name        = explode(" ", $socialite_user->name);
                    $first_name  = $name[0] ?? $socialite_user->name;
                    $last_name   = $name[1] ?? '';
                }
                if ($provider == 'linkedin') {
                    $social_type = 'L';
                    $first_name = $socialite_user->first_name;
                    $last_name  = $socialite_user->last_name;
                }
                $user = User::create([
                    'first_name'        => $first_name ?? '',
                    'last_name'         => $last_name ?? '',
                    'social_id'         => $socialite_user->id,
                    'email'             => $socialite_user->email,
                    'role_id'           => Role::where('name', 'Super Admin')->first()->id,
                    'social_type'       => $social_type,
                    'onboarding_status' => 'YP',
                    'is_email_verified' => Carbon::now()
                ]);
                $data = $this->getLoggedIn($user->id, $socialite_user->id);
            }
            // log them in
            //return "success";
            return redirect()->away($url . 'signin?email=' . $data['user']->email . '&token=' . $data['token']);
        } catch (Exception $e) {
            // return error('Error', $e->getMessage());
            return redirect()->away($url . 'signin?auth_error=' . $e->getMessage());
        }
    }

    /**
     * Open facebook login page
     *
     * @return void
     */


    /**
     * Get Users basic data
     */
    private function getLoggedIn($user_id, $social_id)
    {
        if (Auth::loginUsingId(['id' => $user_id])) {
            $user = Auth::user()->load('company:id,name');
            $success['token'] =  $user->createToken('auth-token')->plainTextToken;
            $success['user']  =  $user;
            return $success;
        }
    }

    /**
     * Get Users basic data from google
     */
    private function getGmailContacts($socialite_user, $mainType, $authId, $nex_page = null)
    {
        try {
            $perPage = 50;
            $ch = curl_init();

            if (!empty($nex_page)) {
                curl_setopt($ch, CURLOPT_URL, 'https://people.googleapis.com/v1/people/me/connections?pageSize=' . $perPage . '&pageToken=' . $nex_page . '&personFields=emailAddresses,names,metadata,skills,relations,phoneNumbers,photos,organizations,occupations,addresses,ageRanges,biographies,birthdays,calendarUrls,clientData,coverPhotos,events,externalIds,genders,imClients,interests,locales,locations,memberships,miscKeywords,nicknames,sipAddresses,urls,userDefined&key=' . env('GOOGLE_API_KEY'));
            } else {
                curl_setopt($ch, CURLOPT_URL, 'https://people.googleapis.com/v1/people/me/connections?pageSize=' . $perPage . '&personFields=emailAddresses,names,metadata,skills,relations,phoneNumbers,photos,organizations,occupations,addresses,ageRanges,biographies,birthdays,calendarUrls,clientData,coverPhotos,events,externalIds,genders,imClients,interests,locales,locations,memberships,miscKeywords,nicknames,sipAddresses,urls,userDefined&key=' . env('GOOGLE_API_KEY'));
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

            $headers = array();
            $headers[] = 'Authorization: Bearer ' . $socialite_user->token;
            $headers[] = 'Accept: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                \Log::info(['google_sync_error' => $ch]);
                $success['success'] = false;
                $success['message'] = $ch;
                return $success;
            }
            curl_close($ch);
            $response = $this->createMycontacts($socialite_user, $mainType, $authId, json_decode($response));
            return $response;
        } catch (\Exception $e) {
            \Log::info(['google_sync_error' => $e->getMessage()]);
            \Log::info(['google_sync_error_line' => $e->getLine()]);
            $success['success'] = false;
            $success['message'] = $e->getMessage();
            return $success;
        }
    }

    /**
     * Create connections
     */
    public function createMycontacts($socialite_user, $mainType, $authId, $contacts)
    {
        $user = User::where('id', $authId)->first();

        if ($user && isset($contacts->connections)) {
            Auth::login($user);
            foreach ($contacts->connections as $contact) {
                $email = $social_id = $organization = $company_name = $profile_picture = $occupation = $first_name = $last_name = $phone_number = "";
                $country_code = null;
                // Get Primary Email Address
                if (isset($contact->emailAddresses)) {
                    foreach ($contact->emailAddresses as $emailAddress) {
                        if (isset($emailAddress->metadata->primary)) {
                            $email = $emailAddress->value ?? null;
                        } else {
                            $email = $emailAddress->value ?? null;
                        }
                    }
                }

                // Get Primary company name and post
                if (isset($contact->organizations)) {
                    foreach ($contact->organizations as $organization) {
                        if (isset($organization->metadata->primary)) {
                            if (isset($organization->type) && $organization->type == 'work') {
                                $company_name = $organization->name ?? null;
                            } else {
                                $company_name = $organization->name ?? null;
                            }
                            $organization = $organization->title ?? null;
                        } else {
                            if (isset($organization->type) && $organization->type == 'work') {
                                $company_name = $organization->name ?? null;
                            } else {
                                $company_name = $organization->name ?? null;
                            }
                            $organization = $organization->title ?? null;
                        }
                    }
                }

                // Get Primary occupations
                if (isset($contact->occupations)) {
                    foreach ($contact->occupations as $occupation) {
                        if (isset($occupation->metadata->primary)) {
                            $occupation = $occupation->value ?? null;
                        } else {
                            $occupation = $occupation->value ?? null;
                        }
                    }
                }

                // Get First name and Last name
                if (isset($contact->names)) {
                    foreach ($contact->names as $name) {
                        if (isset($name->metadata->primary)) {
                            $first_name = $name->givenName ?? null;
                            $last_name = $name->familyName ?? null;
                        } else {
                            $first_name = $name->givenName ?? null;
                            $last_name = $name->familyName ?? null;
                        }
                    }
                }

                // Get phone number
                if (isset($contact->phoneNumbers)) {
                    foreach ($contact->phoneNumbers as $phoneNumber) {
                        if (isset($phoneNumber->metadata->primary)) {
                            $phone_number = $phoneNumber->value ?? null;
                        } else {
                            $phone_number = $phoneNumber->value ?? null;
                        }
                    }
                }

                // Get profile picture
                if (isset($contact->photos)) {
                    foreach ($contact->photos as $photo) {
                        if (isset($photo->metadata->primary)) {
                            $profile_picture = $photo->url ?? null;
                        } else {
                            $profile_picture = $photo->url ?? null;
                        }
                    }
                }

                // Get contact Social Id
                if (isset($contact->metadata)) {
                    $metadata = $contact->metadata;
                    if (isset($metadata->sources)) {
                        foreach ($metadata->sources as $source) {
                            if (isset($source->type) && $source->type == 'PROFILE') {
                                $social_id = $source->id ?? null;
                            } else {
                                $social_id = $source->id ?? null;
                            }
                        }
                    }
                }

                // Get Address
                if (isset($contact->addresses)) {
                    foreach ($contact->addresses as $address) {
                        if (isset($address->metadata->primary)) {
                            $country_code = $address->countryCode ?? null;
                        } else {
                            $country_code = $address->countryCode ?? null;
                        }
                    }
                }

                if ($email) {
                    // get exist contact
                    $exist_contact = CompanyContact::withTrashed()->where('email', $email)->where('created_by', $user->id)->first();
                    if ($exist_contact) {
                        // Update Contact
                        $exist_contact->update([
                            'profile_picture'          => $exist_contact->profile_picture ?? $profile_picture,
                            'areas_of_expertise'       => $exist_contact->areas_of_expertise ?? $occupation,
                            'company_name'             => $exist_contact->company_name ?? $company_name,
                            'phone_number'             => $exist_contact->phone_number ?? str_replace(" ", "", $phone_number),
                            'first_name'               => $exist_contact->first_name ?? $first_name,
                            'last_name'                => $exist_contact->last_name ?? $last_name,
                            'company_id'               => $exist_contact->company_id ?? $user->company_id,
                            'sub_type'                 => 'P',
                            'country_code'             => $exist_contact->country_code ?? $country_code,
                            'updated_by'               => $user->id,
                            'priority'                 => $exist_contact->priority ?? 'L',
                        ]);
                    } else {
                        // Create contact
                        CompanyContact::create([
                            'email'                    => $email,
                            'profile_picture'          => $profile_picture,
                            'areas_of_expertise'       => $occupation,
                            'social_type'              => 'G',
                            'social_id'                => $social_id,
                            'company_name'             => $company_name,
                            'phone_number'             => str_replace(" ", "", $phone_number),
                            'first_name'               => $first_name,
                            'last_name'                => $last_name,
                            'company_id'               => $user->company_id,
                            'sub_type'                 => 'P',
                            'country_code'             => $country_code ?? null,
                            'type'                     => $mainType ?? 'G',
                            'created_by'               => $user->id,
                            'priority'                 => 'L',
                        ]);

                        // Update Google sync count
                        if ($mainType == 'P') {
                            $user->increment('prospect_google_sync_count');
                        } elseif ($mainType == 'CL') {
                            $user->increment('client_google_sync_count');
                        } else {
                            $user->increment('google_sync_count');
                        }
                    }
                }
            }

            // Next page token exist then redirect to next page and get connections
            if (isset($contacts->nextPageToken) && !empty($contacts->nextPageToken)) {
                $this->getGmailContacts($socialite_user, $mainType, $authId, $contacts->nextPageToken);
            }

            // Update sync flag
            $user->update(['sync_with_gmail' => true]);
            $success['success'] = true;
            $success['message'] = "Contacts synced successfully";
            return $success;
        }

        $success['success'] = false;
        $success['message'] = "You can't sync any external account contacts, you can sync only register account contacts.";
        return $success;
    }
}
