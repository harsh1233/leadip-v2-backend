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
use DB;

class ApolloController extends Controller
{
    /**
     * enrich contact using apollo api
     *
     * @return \Illuminate\Http\Response
     */
    public function enrichContacts(Request $request)
    {
        \Log::info(['Apollo API Function call']);
        try {
            $this->validate($request, [
                'sub_type'    => 'nullable|in:C,P',
                'main_type'   => 'nullable|in:CL,P,G',
                'contact_ids' => 'required|array'
            ]);

            //$companyContat = CompanyContact::whereIn('id', array_unique($request->contact_ids))->where('is_enrich', 0)->where('created_by', auth()->user()->id);
            $companyContact = CompanyContact::whereIn('id', array_unique($request->contact_ids));

            $contactCount = (clone $companyContact)->count();
            //$contactCount = CompanyContact::whereIn('id', $request->contact_ids)->where('is_enrich', false)->count();

            if ($request->main_type == 'P') {
                $preFix = "Prospect(s)";
            } elseif ($request->main_type == 'CL') {
                $preFix = "Client(s)";
            } else {
                $preFix = "Contact(s)";
            }
            $message = $preFix . " enrichment process started.";
            sentEnrichContactNotification($message);
            // Calculate Total pages bases of total record count and per page count
            $totalPage = ceil($contactCount / 10) ? ceil($contactCount / 10) : 1;
            \Log::info(['totalPage' => $totalPage]);
            // Get all countries
            $countries = Country::get();

            $enrichContact = [];
            $incompleteData = $record = $incomplete_data = 0;
            $success = $this->getContactsInPagination(1, 10, $totalPage, $contactCount, $request, $countries, $enrichContact, $record, $incomplete_data);
            \Log::info(['Apollo Response']);
            if ($success['success']) {
                $duplicatePhoneNumber   = (clone $companyContact)->groupBy('phone_number')->having(DB::raw('count(phone_number)'), '>', 1)->pluck('phone_number')->toArray();
                $emailCount     = (clone $companyContact)->whereNotNull('email')->count();
                $cnameCount     = (clone $companyContact)->whereNotNull('company_name')->count();
                $firstnameCount = (clone $companyContact)->whereNotNull('first_name')->count();
                $lastnameCount  = (clone $companyContact)->whereNotNull('last_name')->count();
                $phoneCount     = (clone $companyContact)->whereNotNull('phone_number')->count();
                $cityCount      = (clone $companyContact)->whereNotNull('city_id')->count();
                $countryCount   = (clone $companyContact)->whereNotNull('country_code')->count();
                $categoryCount  = (clone $companyContact)->whereNotNull('category')->count();
                $profileCount   = (clone $companyContact)->whereNotNull('profile_picture')->count();
                $linkdiCount    = (clone $companyContact)->whereNotNull('linkedin_url')->count();
                $industryCount  = (clone $companyContact)->whereNotNull('industry')->count();
                $roleCount      = (clone $companyContact)->whereNotNull('role')->count();
                $pointOfCount   = (clone $companyContact)->whereNotNull('point_of_contact')->count();

                $completeData = 100;
                if ($contactCount > 0) {
                    $industryPer  = (($industryCount / $contactCount) * config('constants.industry'));
                    if ($request->sub_type == 'C') {
                        $uniqueRows             = (clone $companyContact)->WhereNotIn('phone_number', $duplicatePhoneNumber)->count();

                        $allphoneNumbers        = (clone $companyContact)->whereIn('phone_number', $duplicatePhoneNumber)->pluck('phone_number')->toArray();
                        $duplicateRows          = collect($allphoneNumbers)->duplicates()->count();
                        $emailPer    = (($emailCount / $contactCount) * config('constants.company_email_percentage'));
                        $cnamePer    = (($cnameCount / $contactCount) * config('constants.company_name_percentahge'));
                        $phonePer    = (($phoneCount / $contactCount) * config('constants.company_phone_percentage'));
                        $cityPer     = (($cityCount / $contactCount) * config('constants.company_city_percentage'));
                        $countryPer  = (($countryCount / $contactCount) * config('constants.company_country_percentage'));
                        $categoryPer = (($categoryCount / $contactCount) * config('constants.category_percentage'));
                        $profilePer  = (($profileCount / $contactCount) * config('constants.profile_picture'));

                        $completeData = ($emailPer + $cnamePer + $phonePer + $cityPer + $countryPer + $categoryPer + $profilePer + $industryPer);

                        $incompleteData     = (clone $companyContact)
                                            ->where(function ($query) {
                                                $query->whereNull('company_name')
                                                ->orWhereNull('email')
                                                ->orWhereNull('phone_number')
                                                ->orWhereNull('country_code')
                                                ->orWhereNull('city_id')
                                                ->orWhereNull('category');
                                            })->count();
                    } else {
                        $duplicateName      = (clone $companyContact)->groupBy('first_name')->having(DB::raw('count(first_name)'), '>', 1)->pluck('first_name')->toArray();
                        $uniqueRows         = (clone $companyContact)->whereNotIn('first_name', $duplicateName)->WhereNotIn('phone_number', $duplicatePhoneNumber)->count();
                        $all_contacts       = (clone $companyContact)->get()->map->only('first_name', 'phone_number');
                        $duplicateRows      =  $all_contacts->duplicates('first_name', 'phone_number')->count();
                        $emailPer    = (($emailCount / $contactCount) * config('constants.people_email_percentage'));
                        $fnamePer    = (($firstnameCount / $contactCount) * config('constants.first_name_percentage'));
                        $lnamePer    = (($lastnameCount / $contactCount) * config('constants.last_name_pecentage'));
                        $phonePer    = (($phoneCount / $contactCount) * config('constants.people_phone_percentage'));
                        $cityPer     = (($cityCount / $contactCount) * config('constants.people_city_percentage'));
                        $countryPer  = (($countryCount / $contactCount) * config('constants.people_country_percentage'));
                        $profilePer  = (($profileCount / $contactCount) * config('constants.people_profile_picture'));
                        $linkdinPer  = (($linkdiCount / $contactCount) * config('constants.linkdin_url'));
                        $rolePer     = (($roleCount / $contactCount) * config('constants.role'));
                        $pointOfPer  = (($pointOfCount / $contactCount) * config('constants.point_of_contact'));

                        $completeData = ($emailPer + $fnamePer + $lnamePer + $phonePer + $cityPer + $countryPer + $profilePer + $linkdinPer + $industryPer + $rolePer + $pointOfPer);

                        // Incomplete row count
                        $incompleteData     = (clone $companyContact)
                                            ->where(function ($query) {
                                                $query->whereNull('first_name')
                                                ->orWhereNull('last_name')
                                                ->orWhereNull('email')
                                                ->orWhereNull('phone_number')
                                                ->orWhereNull('country_code')
                                                ->orWhereNull('city_id')
                                                ->orWhereNull('role')
                                                ->orWhereNull('point_of_contact');
                                            })->count();
                    }
                }
                $message = $preFix . " enrichment process successfully completed.";
                sentEnrichContactNotification($message);
                $total_rows = count($request->contact_ids);
                //$unique_rows = count(array_unique($request->contact_ids));
                //$duplicate_rows = count(array_diff_assoc($request->contact_ids, array_unique($request->contact_ids)));
                if((count($request->contact_ids) == 1) && isset($success['incomplete_data']) && ($success['incomplete_data'] == 1))
                {
                    return error('No matching contact info found!');
                }
                else
                {
                    $data = [
                        //'contacts'               => $success['enrichContact'],
                        'count'                  => count($request->contact_ids),
                        'unique_rows'            => $uniqueRows ?? 0,
                        'duplicate_rows'         => $duplicateRows ?? 0,
                        'incomplete_data'        => $incompleteData,
                        'complete_data'          => $completeData,
                    ];
                    return ok($preFix . ' enriched successfully', $data);
                }

            } else {
                $message = $preFix . " enrichment process fail.";
                sentEnrichContactNotification($message);
                \Log::info(['Error Apollo Response']);
                return error($success['message']);
            }
        } catch (\Exception $e) {
            \Log::info(['Error Apollo Exception' => $e->getMessage()]);
            \Log::info(['Error Apollo Exception Line' => $e->getLine()]);
            return error($e->getMessage());
        }
    }

    public function getContactsInPagination($page, $perPage, $totalPage, $contactCount, $request, $countries, $enrichContact, $record, $incomplete_data)
    {
        try {
            \Log::info(['page' => $page, 'perPage' => $perPage, 'totalPage' => $totalPage, 'contactCount' => $contactCount]);
            //$contacts = CompanyContact::whereIn('id', array_unique($request->contact_ids))->where('is_enrich', 0)->where('created_by', auth()->user()->id)->limit($perPage)->offset((($page ?? 1) - 1) * $perPage)->get();
            $contacts = CompanyContact::whereIn('id', array_unique($request->contact_ids))->limit($perPage)->offset((($page ?? 1) - 1) * $perPage)->get();
            //$contacts = CompanyContact::whereIn('id', $request->contact_ids)->where('is_enrich', false)->limit($perPage)->offset((($page??1)-1)*$perPage)->get();
            $contactArr = [];
            $i = 0;
            foreach ($contacts as $contact) {
                // Get Contact From apollo history table
                if ($request->sub_type == 'P') {
                    $apollo_record = ApolloHistory::where("email", $contact->email)
                        ->where(function ($query) use ($contact) {
                            $query->where('first_name', 'like', "%$contact->first_name%")
                                ->orWhere("last_name", "like", "%$contact->last_name%");
                        })->first();
                } else {
                    $apollo_record = ApolloHistory::where("email", $contact->email)->first();
                }
                // If contact info exiest in apollo history then update otherwise get contact info using apollo api then update
                if ($apollo_record) {
                    $updateContact['is_enrich']           = 1;
                    $updateContact['linkedin_url']        = $apollo_record->linkedin_url;
                    $updateContact['profile_picture']     = $contact->profile_picture ? $contact->profile_picture : ($apollo_record->photo_url ?? null);
                    $updateContact['company_name']        = $contact->company_name ? $contact->company_name : ($apollo_record->organization ? $apollo_record->organization->name : null);
                    //$updateContact['industry']            = $contact->industry ? $contact->industry : ($apollo_record->organization ? $apollo_record->organization->industry : null);
                    $updateContact['country_code']        = $apollo_record->country ? $this->getcountryCode($countries, $apollo_record->country) : null;
                    $updateContact['city_id']             = $contact->city_id ? $contact->city_id : ($apollo_record->city ? $this->getCityId($apollo_record->city) : null);
                    //$updateContact['areas_of_expertise']  = $contact->areas_of_expertise ? $contact->areas_of_expertise : ($apollo_record->organization ? serialize($apollo_record->organization->keywords) : null);
                    if (empty($contact->slag)) {
                        $contactEmail             = explode('@', $contact->email);
                        $updateContact['slag']    = ($contactEmail['0'] ?? ' ') . '-' . strtolower(Str::random(9));
                    }
                    // Update Contact
                    $contact->update($updateContact);
                    $enrichContact[$record] = $contact;
                    $record++;
                } else {
                    $contactArr[$i]['first_name'] = $contact->first_name;
                    $contactArr[$i]['last_name']  = $contact->last_name;
                    $contactArr[$i]['email']      = $contact->email;
                    if ($contact->company_name) {
                        $contactArr[$i]['organization_name'] = $contact->company_name ?? null;
                    }
                    $contact->update(['is_enrich' => 2]);
                    $i++;
                }
            }

            if ($contactArr) {
                $success =  $this->apolloCurl($contactArr, $countries, $enrichContact, $record, $incomplete_data);

                if ($contactCount > 10 && $page <= $totalPage) {
                    $this->getContactsInPagination($page + 1, 10, $totalPage, $contactCount, $request, $countries, $enrichContact, $record, $incomplete_data);
                }
                return $success;
            } elseif ($contactCount > 10 && $page <= $totalPage) {
                $this->getContactsInPagination($page + 1, 10, $totalPage, $contactCount, $request, $countries, $enrichContact, $record, $incomplete_data);
            }

            $success['success'] = true;
            $success['enrichContact'] = $enrichContact;
            $success['incomplete_data'] = $incomplete_data;
            $success['message'] = "Enrich Contacts Successfully.";
            return $success;
        } catch (\Exception $e) {
            \Log::info(['apollo getContactsInPagination' => $e->getMessage()]);
            \Log::info(['apollo getContactsInPagination Line' => $e->getLine()]);
            $success['success'] = false;
            $success['message'] = $e->getMessage();
            return $success;
        }
    }

    public function apolloCurl($details, $countries, $enrichContact, $record, $incomplete_data)
    {
        try {
            $json = array(
                "api_key" => env('APOLLO_API_KEY'),
                "reveal_personal_emails" => true,
                "details" => $details
            );
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://api.apollo.io/api/v1/people/bulk_match');
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
            \Log::info(['result' => $result]);
            if (isset($result->matches) && $result->matches) {
                // create history
                $success = $this->createApoloHistoryAndEnrichContacts($result->matches, $countries, $enrichContact, $record, ($incomplete_data + ($result->missing_records ?? 0)));

                return $success;
            } else {
                if (isset($result->error)) {
                    $success['success'] = false;
                    $success['message'] = $result->error;
                    return $success;
                } else {
                    $success['success'] = true;
                    $success['enrichContact'] = $enrichContact;
                    $success['incomplete_data'] = $incomplete_data + ($result->missing_records ?? 0);
                    $success['message'] = "Process Stop.";
                    return $success;
                }
            }
        } catch (\Exception $e) {
            \Log::info(['apollo_error' => $e->getMessage()]);
            \Log::info(['apollo_error_line' => $e->getLine()]);
            $success['success'] = false;
            $success['message'] = $e->getMessage();
            return $success;
        }
    }

    public function createApoloHistoryAndEnrichContacts($details, $countries, $enrichContact, $record, $incomplete_data)
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
                        ->where('created_by', auth()->user()->id)->first();
                    if ($contact) {
                        $updateContact['is_enrich']           = 1;
                        $updateContact['linkedin_url']        = $detail->linkedin_url;
                        $updateContact['profile_picture']     = $contact->profile_picture ? $contact->profile_picture : ($detail->photo_url ?? null);
                        $updateContact['company_name']        = $contact->company_name ? $contact->company_name : ($detail->organization ? $detail->organization->name : null);
                        // $updateContact['industry']            = $contact->industry ? $contact->industry : ($detail->organization ? $detail->organization->industry : null);
                        $updateContact['country_code']        = $detail->country ? $this->getcountryCode($countries, $detail->country) : null;
                        $updateContact['city_id']             = $contact->city_id ? $contact->city_id : ($detail->city ? $this->getCityId($detail->city) : null);
                        // $updateContact['areas_of_expertise']  = $contact->areas_of_expertise ? $contact->areas_of_expertise : ($detail->organization ? serialize($detail->organization->keywords) : null);
                        if (empty($contact->slag)) {
                            $contactEmail             = explode('@', $contact->email);
                            $updateContact['slag']    = ($contactEmail['0'] ?? ' ') . '-' . strtolower(Str::random(9));
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
            $success['incomplete_data'] = $incomplete_data;
            $success['message'] = 'Get user details from apollo successfully.';
            return $success;
        } catch (\Exception $e) {
            \Log::info(['apollo_error' => $e->getMessage()]);
            \Log::info(['apollo_error_line' => $e->getLine()]);
            $success['success'] = false;
            $success['message'] = $e->getMessage();
            return $success;
        }
    }

    // Filter countries
    public function getcountryCode($countries, $countryName)
    {
        $itemCollection = collect($countries);

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
        $city = City::where("name", "like", "%$cityName%")->first();

        if ($city) {
            return $city->id;
        } else {
            return null;
        }
    }
}
