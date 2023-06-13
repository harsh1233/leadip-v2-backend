<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use App\Models\CompanyContact;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\CompanyPeople;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OutlookController extends Controller
{
    /**
     * Open microsoft login page
     *
     * @return void
     */
    public function outlookSignin(Request $request)
    {
        Session::put('isOnboarding', $request->is_onboarding_route);
        Session::put('AuthId', $request->id);
        if (isset($request->main_type) && $request->main_type) {
            if (in_array($request->main_type, ['G', 'P', 'CL'])) {
                Session::put('mainType', $request->main_type);
            } else {
                Session::put('mainType', 'G');
            }
        }

        // Initialize the OAuth client
        $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => config('azure.appId'),
            'clientSecret'            => config('azure.appSecret'),
            'redirectUri'             => config('azure.redirectUri'),
            'urlAuthorize'            => config('azure.authority') . config('azure.authorizeEndpoint'),
            'urlAccessToken'          => config('azure.authority') . config('azure.tokenEndpoint'),
            'urlResourceOwnerDetails' => '',
            'scopes'                  => config('azure.scopes')
        ]);

        $authUrl = $oauthClient->getAuthorizationUrl();

        Session::put('outlookOauthState', $oauthClient->getState());
        // Redirect to AAD signin page
        return redirect()->away($authUrl);
    }

    /**
     * microsoft login callback function
     *
     * @return void
     */
    public function outlookCallback(Request $request)
    {
        $isOnboarding = Session::pull('isOnboarding');
        $mainType     = Session::pull('mainType');
        $authId       = Session::pull('AuthId');
        $oauthState   = Session::pull('outlookOauthState');
        //Get Message Prefix
        $preFix = prefix($mainType);
        $successMessage = $preFix . " synced successfully";
        $errorMessage   = $preFix . " syncing failed, please try again!";
        if (!empty($isOnboarding) && $isOnboarding == 'true') {
            $tabId = '';
            if ($mainType == 'P') {
                $syncUrl = config('constants.SYNC_PROSPECT_URL');
            } elseif ($mainType == 'CL') {
                $syncUrl = config('constants.SYNC_CLIENT_URL');
            } else {
                $syncUrl = config('constants.ON_BOARDING_SYNC_WEB_URL');
            }
        } else {
            $tabId = 'tabId=5&';
            if ($mainType == 'P') {
                $syncUrl = config('constants.SYNC_PROSPECT_URL');
            } elseif ($mainType == 'CL') {
                $syncUrl = config('constants.SYNC_CLIENT_URL');
            } else {
                $syncUrl = config('constants.SYNC_WEB_URL');
            }
        }

        $providedState = $request->query('state');
        if (!isset($providedState) || $oauthState != $providedState)
        {
            return redirect()->away($syncUrl . '?' . $tabId . 'sync=false&message='.$errorMessage);
        }
        //header('Access-Control-Allow-Origin: *');
        // Authorization error should be in the "error" query param
        $error = $request->query('error');
        if (isset($error)) {
            return redirect()->away($syncUrl . '?' . $tabId . 'sync=false&message='.$errorMessage);
        }

        // Authorization code should be in the "code" query param
        $authCode = $request->query('code');
        if (isset($authCode)) {
            try {
                // Initialize the OAuth client
                $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
                    'clientId'                => config('azure.appId'),
                    'clientSecret'            => config('azure.appSecret'),
                    'redirectUri'             => config('azure.redirectUri'),
                    'urlAuthorize'            => config('azure.authority') . config('azure.authorizeEndpoint'),
                    'urlAccessToken'          => config('azure.authority') . config('azure.tokenEndpoint'),
                    'urlResourceOwnerDetails' => '',
                    'scopes'                  => config('azure.scopes')
                ]);
                // Make the token request
                $accessToken = $oauthClient->getAccessToken('authorization_code', [
                    'code' => $authCode
                ]);

                if($accessToken)
                {
                    $token = $accessToken->getToken();
                    $outlookController = new OutlookController();
                    // get my contacts from outlook using api
                    $response = $outlookController->outllokContacts($token, $mainType, $authId, 1);
                    if (isset($response['success']) && $response['success']) {
                        return redirect()->away($syncUrl . '?' . $tabId . 'sync=true&message=' . $successMessage);
                    } else {
                        return redirect()->away($syncUrl . '?' . $tabId . 'sync=false&message=' . $errorMessage);
                    }
                }
                else
                {
                    return redirect()->away($syncUrl . '?' . $tabId . 'sync=false&message=' . $errorMessage);
                }
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                Log::info(['outlook IdentityProviderException error' => $e->getMessage()]);
                return redirect()->away($syncUrl . '?' . $tabId . 'sync=false&message=' . $errorMessage);
            } catch (\Exception $e) {
                Log::info(['outlook Exception error' => $e->getMessage()]);
                return redirect()->away($syncUrl . '?' . $tabId . 'sync=false&message=' . $errorMessage);
            }
        }

        return redirect()->away($syncUrl . '?' . $tabId . 'sync=false&message='.$errorMessage);
    }

    /**
     * This function use for get outlook contacts list
     *
     * @return void
     */
    public function outllokContacts($accessToken, $mainType, $authId, $page = 1)
    {
        try {
            $perPage = 50;
            $skip = (($page - 1) * $perPage);
            $contacts = null;
            $graph = new Graph();
            $graph->setAccessToken($accessToken);
            // Get contacts from outlook
            $contacts = $graph->createRequest("GET", "/me/contacts?top=" . $perPage . "&skip=" . $skip)
                ->setReturnType(Model\User::class)
                ->execute();
            // store contacts in database
            if ($contacts) {
                $contacts = json_decode(json_encode($contacts));
                $outlookController = new OutlookController();
                $response = $outlookController->createOutlookContacts($accessToken, $contacts, $mainType, $authId, $page);
            } else {
                $response['success'] = true;
                $response['message'] = "Contacts synced successfully";
            }

            return $response;
        } catch (\Exception $e) {
            Log::info(['outlook_sync_error' => $e->getMessage()]);
            Log::info(['outlook_sync_error_line' => $e->getLine()]);
            $success['success'] = false;
            $success['message'] = $e->getMessage();
            return $success;
        }
    }

    /**
     * This function use for create outlook contacts list in our database.
     *
     * @return void
     */
    public function createOutlookContacts($accessToken, $contacts, $mainType, $authId, $page)
    {
        $user = User::where('id', $authId)->first();

        if ($user && $contacts) {
            foreach ($contacts as $contact) {
                $email = $profile_picture = "";
                // Get Primary Email Address
                if (isset($contact->emailAddresses)) {
                    foreach ($contact->emailAddresses as $emailAddress) {
                        if (isset($emailAddress->address)) {
                            if (empty($email)) {
                                $email = $emailAddress->address;
                            }
                        }
                    }
                }
                if ($email) {
                    $contactQuery = CompanyContact::query();
                    // get exist contact
                    $exist_contact = (clone $contactQuery)->withTrashed()->where('email', $email)->where('created_by', $user->id)->first();

                    if ($exist_contact) {
                        // Update Contact
                        DB::table('contacts')->where('id', $exist_contact->id)
                            ->update([
                                'profile_picture'          => $exist_contact->profile_picture ?? $profile_picture,
                                'areas_of_expertise'       => $exist_contact->areas_of_expertise ?? $contact->profession,
                                'company_name'             => $exist_contact->company_name ?? $contact->companyName,
                                'phone_number'             => $exist_contact->phone_number ?? str_replace(" ", "", $contact->mobilePhone),
                                'first_name'               => $exist_contact->first_name ?? $contact->givenName,
                                'last_name'                => $exist_contact->last_name ?? $contact->surname,
                                'company_id'               => $exist_contact->company_id ?? $user->company_id,
                                'sub_type'                 => 'P', // P=People
                                'updated_by'               => $user->id,
                                'updated_at'               => \Carbon\Carbon::now(),
                                'priority'                 => $exist_contact->priority ?? 'L', // L=Low
                            ]);
                        // If contact slag is empty then update
                        if(empty($exist_contact->slag))
                        {
                            $slug = Str::slug($exist_contact->id);
                            // Get Contact slag url
                            $fianlslug = contactSlugUrl($exist_contact->type, $slug);
                            $exist_contact->update([
                                'slag' => $fianlslug
                            ]);
                        }
                    } else {
                        $contactId = Str::uuid();
                        // Create Contact
                        (clone $contactQuery)->insert([
                            'id'                       => $contactId,
                            'email'                    => $email,
                            'profile_picture'          => $profile_picture ?? null,
                            'areas_of_expertise'       => $contact->profession ?? null,
                            'social_type'              => 'O',
                            'social_id'                => $contact->id ?? null,
                            'company_name'             => $contact->companyName ?? null,
                            'phone_number'             => str_replace(" ", "", $contact->mobilePhone) ?? null,
                            'first_name'               => $contact->givenName ?? null,
                            'last_name'                => $contact->surname,
                            'company_id'               => $user->company_id,
                            'sub_type'                 => 'P', // P=People
                            'type'                     => $mainType ?? 'G', // G=General
                            'created_by'               => $user->id,
                            'created_at'               => \Carbon\Carbon::now(),
                            'priority'                 => 'L', // L=Low
                        ]);
                        // Get contact
                        $contact = (clone $contactQuery)->where('id', $contactId)->first();
                        if($contact)
                        {
                            // Contact people mapping
                            contactCompanyPeopleMap($contact);
                            // Create contact slag and update
                            $slug = Str::slug($contact->id);
                            // Get Contact slag url
                            $fianlslug = contactSlugUrl($mainType, $slug);
                            //Update slag url
                            $contact->update([
                                'slag' => $fianlslug
                            ]);
                        }
                        // Update Outlook sync count
                        if ($mainType == 'P') {
                            $user->increment('prospect_outlook_sync_count');
                        } elseif ($mainType == 'CL') {
                            $user->increment('client_outlook_sync_count');
                        } else {
                            $user->increment('outlook_sync_count');
                        }
                    }
                }
            }

            // Next page token exist then redirect to next page and get contacts
            if (!empty($accessToken) && !empty($page)) {
                $this->outllokContacts($accessToken, $mainType, $authId, ($page + 1));
            }

            // Update sync flag
            $user->update(['sync_with_outlook' => true]);

            $success['success'] = true;
            $success['message'] = "Contacts synced successfully";
            return $success;
        }

        $success['success'] = false;
        $success['message'] = "You can't sync any external account contacts, you can sync only register account contacts.";
        return $success;
    }
}
