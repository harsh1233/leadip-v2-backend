<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ContactListAssignedUser;
use Illuminate\Support\Str;
use App\Models\CompanyContact;
use App\Models\ApolloHistory;
use App\Models\Country;
use App\Models\City;
use App\Models\TradeMark;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApolloController extends Controller
{
    /**
     * enrich contact using apollo api
     *
     * @param  mixed $request
     * @return \Illuminate\Http\Response
     */
    public function enrichContacts(Request $request)
    {
        Log::info(['Apollo API Function call']);
        try {
            $this->validate($request, [
                'sub_type'    => 'nullable|in:C,P',//C=Company, P=People
                'main_type'   => 'nullable|in:CL,P,G',//G=General,P=Prospect,CL=Client
                'contact_ids' => 'required|array'
            ]);

            $companyContact = CompanyContact::whereIn('id', array_unique($request->contact_ids));

            $contactCount = (clone $companyContact)->count();

            // Get message Prefix
            $preFix = prefix($request->main_type);
            $message = $preFix . " enrichment process started.";
            //send process start notification
            sentEnrichContactNotification($message);
            // Calculate Total pages bases of total record count and per page count
            $totalPage = ceil($contactCount / 10) ? ceil($contactCount / 10) : 1;
            // Get all countries
            $countries = Country::get();

            $enrichContact = [];
            $record = $incompleteData = 0;
            //Contact enrichment process function call
            $success = $this->getContactsInPagination(1, 10, $totalPage, $contactCount, $request, $countries, $enrichContact, $record, $incompleteData);
            if ($success['success']) {
                // Get duplicate phone_number row count
                // Get Reports
                $result = contactProfileReport($request->contact_ids, $request->sub_type);
                $message = $preFix . " enrichment process successfully completed.";
                //send process completed notification
                sentEnrichContactNotification($message);
                //$total_rows = count($request->contact_ids);
                //$unique_rows = count(array_unique($request->contact_ids));
                //$duplicate_rows = count(array_diff_assoc($request->contact_ids, array_unique($request->contact_ids)));
                if(isset($success['incomplete_data']) && ($success['incomplete_data'] >= 1) && (count($request->contact_ids) == $success['incomplete_data'])) {
                    $data = [
                        'count'              => $result['count'],
                        'unique_rows'        => $result['unique_rows'] ?? 0,
                        'duplicate_rows'     => $result['duplicate_rows'] ?? 0,
                        'complete_data'      => $result['complete_data'] ?? 0,
                        'incomplete_data'    => $result['incomplete_data'] ?? 0,
                        'enrichment_process' => false,
                    ];
                    return ok('No matching contact info found!', $data);
                } elseif (isset($success['incomplete_data']) && !empty($success['incomplete_data']) && (count($request->contact_ids) != $success['incomplete_data'])) {
                    $data = [
                        'count'              => $result['count'],
                        'unique_rows'        => $result['unique_rows'] ?? 0,
                        'duplicate_rows'     => $result['duplicate_rows'] ?? 0,
                        'complete_data'      => $result['complete_data'] ?? 0,
                        'incomplete_data'    => $result['incomplete_data'] ?? 0,
                        'enrichment_process' => true,
                    ];
                    return ok($preFix . ' enrichment partially successful', $data);
                } else {
                    $data = [
                        //'contacts'               => $success['enrichContact'],
                        'count'              => $result['count'],
                        'unique_rows'        => $result['unique_rows'] ?? 0,
                        'duplicate_rows'     => $result['duplicate_rows'] ?? 0,
                        'complete_data'      => $result['complete_data'] ?? 0,
                        'incomplete_data'    => $result['incomplete_data'] ?? 0,
                        'enrichment_process' => true,
                    ];
                    return ok($preFix . ' enriched successfully', $data);
                }

            } else {
                $message = $preFix . " enrichment process fail.";
                //send process fail notification
                sentEnrichContactNotification($message);
                return error($success['message']);
            }
        } catch (\Exception $e) {
            Log::info(['Error Apollo Exception' => $e->getMessage()]);
            Log::info(['Error Apollo Exception Line' => $e->getLine()]);
            return error($e->getMessage());
        }
    }

    /**
     * this function use for enrchi contact info
     *
     */
    public function getContactsInPagination($page, $perPage, $totalPage, $contactCount, $request, $countries, $enrichContact, $record, $incompleteData)
    {
        try {
            $contacts = CompanyContact::whereIn('id', array_unique($request->contact_ids))->limit($perPage)->offset((($page ?? 1) - 1) * $perPage)->get();
            $contactArr = [];
            $i = 0;
            $apolloQuery = ApolloHistory::query();
            foreach ($contacts as $contact) {
                // Get Contact From apollo history table
                //if ($request->sub_type == 'P') {
                    //$apolloRecord = (clone $apolloQuery)->where("email", $contact->email)
                        // ->where(function ($query) use ($contact) {
                        //     $query->where('first_name', 'like', "%{$contact->first_name}%")
                        //         ->orWhere("last_name", "like", "%{$contact->last_name}%");
                        // })->first();
                //} else {
                    $apolloRecord = (clone $apolloQuery)->where("email", $contact->email)->first();
                //}
                // If contact info exiest in apollo history then update otherwise get contact info using apollo api then update
                if (!empty($apolloRecord)) {
                    $updateContact = [];
                    $profile_picture = $contact->profile_picture;
                    $company_name = $contact->company_name;
                    if (empty($profile_picture)) {
                        $profile_picture = $apolloRecord->photo_url ?? null;
                    }
                    if (empty($company_name)) {
                        $company_name = $apolloRecord->organization ? $apolloRecord->organization->name : null;
                    }
                    $updateContact['is_enrich']           = 1;
                    $updateContact['linkedin_url']        = $apolloRecord->linkedin_url;
                    $updateContact['profile_picture']     = $profile_picture;
                    $updateContact['company_name']        = $company_name;
                    //$updateContact['industry']            = $contact->industry ? $contact->industry : ($apolloRecord->organization ? $apolloRecord->organization->industry : null);
                    $updateContact['country_code']        = $apolloRecord->country ? $this->getcountryCode($countries, $apolloRecord->country) : null;
                    $updateContact['city_id']             = $contact->city_id ? $contact->city_id : ($apolloRecord->city ? $this->getCityId($apolloRecord->city) : null);
                    //$updateContact['areas_of_expertise']  = $contact->areas_of_expertise ? $contact->areas_of_expertise : ($apolloRecord->organization ? serialize($apolloRecord->organization->keywords) : null);
                    // If contact slag is empty then update
                    if (empty($contact->slag)) {
                        $slug = Str::slug($contact->id);
                        // Get Contact slag url
                        $fianlslug = contactSlugUrl($contact->type, $slug);
                        $updateContact['slag']    = $fianlslug;
                    }
                    // Update Contact
                    $contact->update($updateContact);
                    $enrichContact[$record] = $contact;
                    $record++;
                } else {
                    $updateContact = [];
                    $contactArr[$i]['first_name'] = $contact->first_name;
                    $contactArr[$i]['last_name']  = $contact->last_name;
                    $contactArr[$i]['email']      = $contact->email;
                    if ($contact->company_name) {
                        $contactArr[$i]['organization_name'] = $contact->company_name ?? null;
                    }
                    $updateContact['is_enrich'] = 2;
                    // If contact slag is empty then update
                    if (empty($contact->slag)) {
                        $slug = Str::slug($contact->id);
                        // Get Contact slag url
                        $fianlslug = contactSlugUrl($contact->type, $slug);
                        $updateContact['slag']    = $fianlslug;
                    }
                    $contact->update($updateContact);
                    $i++;
                }
            }

            if ($contactArr) {
                // Get contact info using apollo api
                $success =  $this->apolloCurl($contactArr, $countries, $enrichContact, $record, $incompleteData);

                if ($contactCount > 10 && $page <= $totalPage) {
                    // Enrcich next page contact info
                    $this->getContactsInPagination($page + 1, 10, $totalPage, $contactCount, $request, $countries, $enrichContact, $record, $incompleteData);
                }
                return $success;
            } elseif ($contactCount > 10 && $page <= $totalPage) {
                // Enrcich next page contact info
                $this->getContactsInPagination($page + 1, 10, $totalPage, $contactCount, $request, $countries, $enrichContact, $record, $incompleteData);
            }

            $success['success'] = true;
            $success['enrichContact'] = $enrichContact;
            $success['incomplete_data'] = $incompleteData;
            $success['message'] = "Enrich Contacts Successfully.";
            return $success;
        } catch (\Exception $e) {
            Log::info(['apollo getContactsInPagination' => $e->getMessage()]);
            Log::info(['apollo getContactsInPagination Line' => $e->getLine()]);
            $success['success'] = false;
            $success['message'] = $e->getMessage();
            return $success;
        }
    }

    /**
     * this function use for get relative contact info from apollo
     *
     */
    public function apolloCurl($details, $countries, $enrichContact, $record, $incompleteData)
    {
        try {
            $json = array(
                "api_key" => env('APOLLO_API_KEY'),
                "reveal_personal_emails" => true,
                "details" => $details
            );
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, config('constants.APOLLO_API_URL'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));

            $headers = array();
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Cache-Control: no-cache';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                $success['success'] = true;
                $success['message'] = curl_error($ch);
                return $success;
            }

            curl_close($ch);
            $result = json_decode($result);
            Log::info(['result' => $result]);
            if (isset($result->matches) && $result->matches) {
                // create history
                return $this->createApoloHistoryAndEnrichContacts($result->matches, $countries, $enrichContact, $record, ($incompleteData + ($result->missing_records ?? 0)));
            } else {
                if (isset($result->error)) {
                    $success['success'] = false;
                    $success['message'] = $result->error;
                    return $success;
                } else {
                    $success['success'] = true;
                    $success['enrichContact'] = $enrichContact;
                    $success['incomplete_data'] = $incompleteData + ($result->missing_records ?? 0);
                    $success['message'] = "Process Stop.";
                    return $success;
                }
            }
        } catch (\Exception $e) {
            Log::info(['apollo_error' => $e->getMessage()]);
            Log::info(['apollo_error_line' => $e->getLine()]);
            $success['success'] = false;
            $success['message'] = $e->getMessage();
            return $success;
        }
    }

    /**
     * this function use for create apollo histrory and update missing contacts info.
     *
     */
    public function createApoloHistoryAndEnrichContacts($details, $countries, $enrichContact, $record, $incompleteData)
    {
        try {
            foreach ($details as $detail) {
                if ($detail) {
                    $functions = $subdepartments = $organization = $employment_history = $departments = $personal_emails = $phone_numbers = null;
                    if (isset($detail->phone_numbers)) {
                        $phone_numbers = json_encode($detail->phone_numbers);
                    }
                    if (isset($detail->personal_emails)) {
                        $personal_emails = json_encode($detail->personal_emails);
                    }
                    if (isset($detail->departments)) {
                        $departments = json_encode($detail->departments);
                    }
                    if (isset($detail->employment_history)) {
                        $employment_history = json_encode($detail->employment_history);
                    }
                    if (isset($detail->organization)) {
                        $organization = json_encode($detail->organization);
                    }
                    if (isset($detail->subdepartments)) {
                        $subdepartments = json_encode($detail->subdepartments);
                    }
                    if (isset($detail->functions)) {
                        $functions = json_encode($detail->functions);
                    }
                    //Create Apollo history
                    ApolloHistory::create([
                        'people_id'                     => $detail->id,
                        'first_name'                    => $detail->first_name,
                        'last_name'                     => $detail->last_name,
                        'headline'                      => $detail->headline,
                        'linkedin_url'                  => $detail->linkedin_url,
                        'title'                         => $detail->title,
                        'email_status'                  => $detail->email_status,
                        'photo_url'                     => $detail->photo_url,
                        'twitter_url'                   => $detail->twitter_url,
                        'github_url'                    => $detail->github_url,
                        'facebook_url'                  => $detail->facebook_url,
                        'extrapolated_email_confidence' => $detail->extrapolated_email_confidence,
                        'email'                         => $detail->email,
                        'state'                         => $detail->state,
                        'city'                          => $detail->city,
                        'country'                       => $detail->country,
                        'seniority'                     => $detail->seniority,
                        'intent_strength'               => $detail->intent_strength,
                        'show_intent'                   => $detail->show_intent,
                        'revealed_for_current_team'     => $detail->revealed_for_current_team,
                        'personal_emails'               => $personal_emails,
                        'departments'                   => $departments,
                        'employment_history'            => $employment_history,
                        'organization'                  => $organization,
                        'phone_numbers'                 => $phone_numbers,
                        'subdepartments'                => $subdepartments,
                        'functions'                     => $functions,
                    ]);

                    // Get Contact if exist then update info
                    $contact = CompanyContact::where('email', $detail->email)
                        ->where('company_id', auth()->user()->company_id)->first();
                    if ($contact) {
                        $profile_picture = $contact->profile_picture;
                        $company_name = $contact->company_name;
                        if (empty($profile_picture)) {
                            $profile_picture = $detail->photo_url ?? null;
                        }
                        if (empty($company_name)) {
                            $company_name = $detail->organization ? $detail->organization->name : null;
                        }
                        $updateContact['is_enrich']           = 1;
                        $updateContact['linkedin_url']        = $detail->linkedin_url;
                        $updateContact['profile_picture']     = $profile_picture;
                        $updateContact['company_name']        = $company_name;
                        $updateContact['country_code']        = $detail->country ? $this->getcountryCode($countries, $detail->country) : null;
                        $updateContact['city_id']             = $contact->city_id ? $contact->city_id : ($detail->city ? $this->getCityId($detail->city) : null);
                        if (empty($contact->slag)) {
                            $slug = Str::slug($contact->id);
                            // Get Contact slag url
                            $fianlslug = contactSlugUrl($contact->type, $slug);
                            $updateContact['slag']    = $fianlslug;
                        }
                        // Update Contact Info
                        $contact->update($updateContact);
                        $enrichContact[$record] = $contact;
                        $record++;
                    }
                }
            }

            $success['success'] = true;
            $success['enrichContact'] = $enrichContact;
            $success['incomplete_data'] = $incompleteData;
            $success['message'] = 'Get user details from apollo successfully.';
            return $success;
        } catch (\Exception $e) {
            Log::info(['apollo_error' => $e->getMessage()]);
            Log::info(['apollo_error_line' => $e->getLine()]);
            $success['success'] = false;
            $success['message'] = $e->getMessage();
            return $success;
        }
    }

    // Filter countries
    public function getcountryCode($countries, $countryName)
    {
        $itemCollection = collect($countries);
        // Search Country code in collection using country name
        $filtered = $itemCollection->filter(function ($item) use ($countryName) {
            return stripos($item['name'], $countryName) !== false;
        })->first();

        if ($filtered) {
            return $filtered->code;
        } else {
            return null;
        }
    }

    // Filter cities
    public function getCityId($cityName)
    {
        // Get city id using city name
        $city = City::where("name", "like", "%{$cityName}%")->first();

        if ($city) {
            return $city->id;
        } else {
            return null;
        }
    }
}
