<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CompanyContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CompanyContactImport;
use App\Imports\PeopleContactImport;
use App\Models\AssignedContact;
use App\Models\Role;
use Exception;

class ReportController extends Controller
{
    /**
     *  Get contacts count api
     *
     * @param  mixed $request
     * @return void
     */
    public function contactCount(Request $request)
    {
        // Validation
        $this->validate($request,[
            'main_type' => 'required|in:G,P,CL',// G=contact, P=prospect, CL=client
            'sub_type'  => 'required|in:C,P',// C=company, P=people
        ]);
        // Get Assign contacts ids
        $assignedContactIds = AssignedContact::where('assigned_to_id', auth()->user()->id)->pluck('contact_id')->toArray();
        // Get auth user role
        $user = Role::where('name', config('constants.super_admin'))->where('id', '=', auth()->user()->role_id)->first();

        $contacts = CompanyContact::query();
        //If auth user role is super admin and get all contacts ids like(public,private,lost) otherwise get only own and assigned contacts ids.
        if($user)
        {
            $allcontactIds  = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            $mycontactIds   = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 1)->where('is_lost', 0)->pluck('id')->toArray();
            $lostcontactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_lost', 1)->pluck('id')->toArray();
        }
        else
        {
            $allcontactIds  = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
            $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
            $allcontactIds = array_merge($allcontactIds, $authContactIds);
            $mycontactIds   = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_private', 1)->where('is_lost', 0)->pluck('id')->toArray();
            $lostcontactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_lost', 1)->pluck('id')->toArray();
        }

        if(isset($assignedContactIds))
        {
            $allcontactIds  = array_merge($assignedContactIds, $allcontactIds);
            $mycontactIds   = array_merge($assignedContactIds, $mycontactIds);
            $lostcontactIds = array_merge($assignedContactIds, $lostcontactIds);
        }

        if($mycontactIds)
        {
            $lostContactIds = (clone $contacts)->whereIn('id', $mycontactIds)->where('is_lost', 1)->pluck('id')->toArray();
            $mycontactIds = array_diff($mycontactIds, $lostContactIds);
        }

        if($request->main_type == 'G')
        {
            // Get All contacts count
            $allConatct  = (clone $contacts)->whereIn('id', array_unique($allcontactIds))->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->count();
            // Get My contacts count
            $myContact   = (clone $contacts)->whereIn('id', array_unique($mycontactIds))->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->count();
            // Get Lost contacts count
            $lostContact = (clone $contacts)->whereIn('id', array_unique($lostcontactIds))->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->where('is_lost', 1)->count();
            // Get Duplicate phonenumber in array formate
            $duplicatePhoneNumber   = (clone $contacts)->whereIn('id', array_unique($allcontactIds))->where('company_id', auth()->user()->company_id)->where('sub_type',$request->sub_type)->groupBy('phone_number')->having(DB::raw('count(phone_number)'), '>', 1)->pluck('phone_number')->toArray();
            $companyContact         = (clone $contacts)->whereIn('id', array_unique($allcontactIds))->where('sub_type',$request->sub_type)->where('company_id', auth()->user()->company_id);
            $emailCount             = (clone $companyContact)->whereNotNull('email')->count();
            $phoneNumber            = (clone $companyContact)->whereNotNull('phone_number')->count();
            $country                = (clone $companyContact)->whereNotNull('country_code')->count();
            $city                   = (clone $companyContact)->whereNotNull('city_id')->count();
            $category               = (clone $companyContact)->whereNotNull('category')->count();
            $profilePicture         = (clone $companyContact)->whereNotNull('profile_picture')->count();
            $industry               = (clone $companyContact)->whereNotNull('industry')->count();
            $role                   = (clone $companyContact)->whereNotNull('role')->count();
            $pointOfContact         = (clone $companyContact)->whereNotNull('point_of_contact')->count();
            $nameCount              = (clone $companyContact)->whereNotNull('company_name')->count();
            if($request->sub_type=='C')
            {
                // Get unique row count
                $uniqueRows         = (clone $companyContact)->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();
                $phoneNumbers       = (clone $companyContact)->whereIn('phone_number',$duplicatePhoneNumber)->pluck('phone_number')->toArray();
                // Get duplicate phone numbers row count
                $duplicateRows      = collect( $phoneNumbers)->duplicates()->count();
                // Get total contact count
                $contactCount       = (clone $companyContact)->count();
                $completenessData   = (clone $companyContact)->whereNotNull(['company_name','email','phone_number','country_code','city_id','category'])->count();
                $completeData = 0;
                $incompleteData = 0;
                if($contactCount > 0 && $completenessData > 0)
                {
                    // calculate companyname,email,phonenumber,country,city,category, etc.. percentage
                    $namePercentage          = $nameCount   / $contactCount * config('constants.company_name_percentahge');

                    $emailPercentage         = $emailCount  / $contactCount * config('constants.company_email_percentage');

                    $phonePercentage         = $phoneNumber / $contactCount * config('constants.company_phone_percentage');

                    $countryPercentage       = $country     / $contactCount * config('constants.country_percentage');

                    $company_city_percentage = $city        / $contactCount * config('constants.company_city_percentage');
                    $categoryPercentage      = $category    / $contactCount * config('constants.category_percentage');
                    $profilePicturePercentage=$profilePicture/$contactCount * config('constants.profile_picture');
                    $industryPercentage      =$industry      /$contactCount * config('constants.industry');
                    $completeData            = $namePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $company_city_percentage + $categoryPercentage + $profilePicturePercentage + $industryPercentage;

                    // Incomplete row count
                    $incompleteData     = (clone $companyContact)
                                            ->where(function ($query) {
                                                $query->whereNull('company_name')
                                                ->orWhereNull('email')
                                                ->orWhereNull('phone_number')
                                                ->orWhereNull('country_code')
                                                ->orWhereNull('city_id')
                                                ->orWhereNull('category');
                                            })->count();
                }
            }
            else
            {
                // Get total contact count
                $contactCount                = (clone $companyContact)->count();
                // Get duplicate firstname contact ids in array format
                $duplicateName               = (clone $companyContact)->groupBy('first_name')->having(DB::raw('count(first_name)'), '>', 1)->pluck('first_name')->toArray();
                // Get unique row count
                $uniqueRows                  = (clone $companyContact)->whereNotIn('first_name',$duplicateName)
                                                ->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();
                $contacts                    = (clone $companyContact)->get()->map->only('first_name', 'phone_number');
                // Get duplicate count
                $duplicateRows               =  $contacts->duplicates('first_name','phone_number')->count();
                // Get firstName count
                $firstNameCount              = (clone $companyContact)->whereNotNull('first_name')->count();
                // Get lastName count
                $lastNameCount               = (clone $companyContact)->whereNotNull('last_name')->count();
                $completenessData            = (clone $companyContact)->whereNotNull(['first_name','last_name','email','phone_number','country_code','city_id'])->count();
                $completeData = 0;
                $incompleteData = 0;
                if($contactCount > 0 && $completenessData > 0)
                {
                    // calculate firstname,lastname,email,phonenumber,country,city,category, etc.. percentage
                    $firstNamePercentage     = $firstNameCount  / $contactCount * config('constants.first_name_percentage');
                    $lastNamePercentage      = $lastNameCount   / $contactCount * config('constants.last_name_pecentage');
                    $emailPercentage         = $emailCount      / $contactCount * config('constants.people_email_percentage');

                    $phonePercentage         = $phoneNumber / $contactCount * config('constants.people_phone_percentage');

                    $countryPercentage       = $country     / $contactCount * config('constants.people_country_percentage');

                    $cityPercentage          = $city        / $contactCount * config('constants.people_city_percentage');
                    $profilePicturePercentage=$profilePicture/$contactCount * config('constants.profile_picture');
                    $industryPercentage      =$industry      /$contactCount * config('constants.industry');
                    $rolePercentage          =$role          /$contactCount * config('constants.role');
                    $pointOfContactPercentage=$pointOfContact          /$contactCount * config('constants.point_of_contact');
                    $completeData            = $firstNamePercentage + $lastNamePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $cityPercentage + $profilePicturePercentage + $industryPercentage + $rolePercentage + $pointOfContactPercentage;

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
        }
        else
        {
            // Get All contacts count
            $allConatct  = (clone $contacts)->whereIn('id', array_unique($allcontactIds))->where('type', '=', $request->main_type)->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->count();
            // Get My contacts count
            $myContact   = (clone $contacts)->whereIn('id', array_unique($mycontactIds))->where('type', '=', $request->main_type)->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->count();
            // Get Lost contacts count
            $lostContact = (clone $contacts)->whereIn('id', array_unique($lostcontactIds))->where('type', '=', $request->main_type)->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->where('is_lost', 1)->count();

            $duplicatePhoneNumber   = (clone $contacts)->whereIn('id', array_unique($allcontactIds))->where('company_id', auth()->user()->company_id)->where('type', '=', $request->main_type)->where('sub_type', '=', $request->sub_type)->groupBy('phone_number')->having(DB::raw('count(phone_number)'), '>', 1)->pluck('phone_number')->toArray();

            $companyContact = (clone $contacts)->whereIn('id', array_unique($allcontactIds))->where('type', '=', $request->main_type)->where('sub_type',$request->sub_type)->where('company_id', auth()->user()->company_id);
            // Get contact required field count
            $emailCount             = (clone $companyContact)->whereNotNull('email')->count();
            $phoneNumber            = (clone $companyContact)->whereNotNull('phone_number')->count();
            $country                = (clone $companyContact)->whereNotNull('country_code')->count();
            $city                   = (clone $companyContact)->whereNotNull('city_id')->count();
            $category               = (clone $companyContact)->whereNotNull('category')->count();
            $profilePicture         = (clone $companyContact)->whereNotNull('profile_picture')->count();
            $industry               = (clone $companyContact)->whereNotNull('industry')->count();
            $role                   = (clone $companyContact)->whereNotNull('role')->count();
            $pointOfContact         = (clone $companyContact)->whereNotNull('point_of_contact')->count();
            $nameCount              = (clone $companyContact)->whereNotNull('company_name')->count();
            $completeData = 0;
            $incompleteData = 0;
            if($request->sub_type=='C')
            {
                $uniqueRows         = (clone $companyContact)->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();

                $phoneNumbers       = (clone $companyContact)->whereIn('phone_number',$duplicatePhoneNumber)->pluck('phone_number')->toArray();
                $duplicateRows      = collect( $phoneNumbers)->duplicates()->count();
                // Get total contact count
                $contactCount       = (clone $companyContact)->count();
                $completenessData   = (clone $companyContact)->whereNotNull(['company_name','email','phone_number','country_code','city_id','category'])->count();
                $completeData = 0;
                $incompleteData = 0;
                if($contactCount > 0 && $completenessData > 0)
                {
                    // calculate companyname,email,phonenumber,country,city,category, etc.. percentage
                    $namePercentage          = $nameCount   / $contactCount * config('constants.company_name_percentahge');

                    $emailPercentage         = $emailCount  / $contactCount * config('constants.company_email_percentage');

                    $phonePercentage         = $phoneNumber / $contactCount * config('constants.company_phone_percentage');

                    $countryPercentage       = $country     / $contactCount * config('constants.country_percentage');

                    $company_city_percentage = $city        / $contactCount * config('constants.company_city_percentage');
                    $categoryPercentage      = $category    / $contactCount * config('constants.category_percentage');
                    $profilePicturePercentage=$profilePicture/$contactCount * config('constants.profile_picture');
                    $industryPercentage      =$industry      /$contactCount * config('constants.industry');
                    $completeData            = $namePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $company_city_percentage + $categoryPercentage + $profilePicturePercentage + $industryPercentage;
                    // Incomplete row count
                    $incompleteData     = (clone $companyContact)
                                            ->where(function ($query) {
                                                $query->whereNull('company_name')
                                                ->orWhereNull('email')
                                                ->orWhereNull('phone_number')
                                                ->orWhereNull('country_code')
                                                ->orWhereNull('city_id')
                                                ->orWhereNull('category');
                                            })->count();
                    //$completeData       = $completenessData / $contactCount * $totalPercentage;
                    //$incompleteData          = config('constants.total_percentage') - $completeData  ;
                }
            }
            else
            {
                $duplicateName               = (clone $companyContact)->groupBy('first_name')->having(DB::raw('count(first_name)'), '>', 1)->pluck('first_name')->toArray();
                // Get unique rows count
                $uniqueRows                  = (clone $companyContact)->whereNotIn('first_name',$duplicateName)
                                                ->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();
                $contacts                    = (clone $companyContact)->get()->map->only('first_name', 'phone_number');
                // Get duplicate rows count
                $duplicateRows               =  $contacts->duplicates('first_name','phone_number')->count();
                // Get firstName count
                $firstNameCount              = (clone $companyContact)->whereNotNull('first_name')->count();
                // Get lastName count
                $lastNameCount               = (clone $companyContact)->whereNotNull('last_name')->count();
                // Get total contact count
                $contactCount                = (clone $companyContact)->count();
                $completenessData            = (clone $companyContact)->whereNotNull(['first_name','last_name','email','phone_number','country_code','city_id'])->count();
                $completeData = 0;
                $incompleteData = 0;
                if($contactCount > 0 && $completenessData > 0)
                {
                    // calculate firstname,lastname,email,phonenumber,country,city,category, etc.. percentage
                    $firstNamePercentage     = $firstNameCount  / $contactCount * config('constants.first_name_percentage');
                    $lastNamePercentage      = $lastNameCount   / $contactCount * config('constants.last_name_pecentage');
                    $emailPercentage         = $emailCount      / $contactCount * config('constants.people_email_percentage');

                    $phonePercentage         = $phoneNumber / $contactCount * config('constants.people_phone_percentage');

                    $countryPercentage       = $country     / $contactCount * config('constants.people_country_percentage');

                    $cityPercentage          = $city        / $contactCount * config('constants.people_city_percentage');
                    $profilePicturePercentage=$profilePicture/$contactCount * config('constants.profile_picture');
                    $industryPercentage      =$industry      /$contactCount * config('constants.industry');
                    $rolePercentage          =$role          /$contactCount * config('constants.role');
                    $pointOfContactPercentage=$pointOfContact          /$contactCount * config('constants.point_of_contact');
                    $completeData            = $firstNamePercentage + $lastNamePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $cityPercentage + $profilePicturePercentage + $industryPercentage + $rolePercentage + $pointOfContactPercentage;
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
        }

        return ok('Reports count',[
            'all_contact'     => $allConatct ?? 0,
            'my_contact'      => $myContact ?? 0,
            'lost_contact'    => $lostContact ?? 0,
            'no_of_columns'   => $allConatct ?? 0,
            'unique_rows'     => $uniqueRows ?? 0,
            'duplicate_rows'  => $duplicateRows ?? 0,
            'complete_data'   => $completeData ?? 0,
            'incomplete_data' => $incompleteData ?? 0
        ]);

    }

    /**
     *  Get contacts percentage api
     *
     * @param  mixed $request
     * @return void
     */
    public function contactPercentage(Request $request)
    {
        //Validation
        $this->validate($request,[
            'main_type' => 'required|in:G,P,CL',// G=contact, P=prospect, CL=client
            'sub_type'  => 'required|in:C,P',// C=company, P=people
        ]);
        //Get assigned contacts ids
        $assignedContactIds = AssignedContact::where('assigned_to_id', auth()->user()->id)->pluck('contact_id')->toArray();
        //Get Auth user role
        $user = Role::where('name', config('constants.super_admin'))->where('id', '=', auth()->user()->role_id)->first();

        $contacts = CompanyContact::query();

        if($user)
        {
            $allcontactIds  = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            $mycontactIds   = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 1)->where('is_lost', 0)->pluck('id')->toArray();
            $lostcontactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_lost', 1)->pluck('id')->toArray();
        }
        else
        {
            $allcontactIds  = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
            $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
            $allcontactIds = array_merge($allcontactIds, $authContactIds);
            $mycontactIds   = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_private', 1)->where('is_lost', 0)->pluck('id')->toArray();
            $lostcontactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_lost', 1)->pluck('id')->toArray();
        }

        if(isset($assignedContactIds))
        {
            $allcontactIds  = array_merge($assignedContactIds, $allcontactIds);
            $mycontactIds   = array_merge($assignedContactIds, $mycontactIds);
            $lostcontactIds = array_merge($assignedContactIds, $lostcontactIds);
        }

        if($mycontactIds)
        {
            $lostContactIds = (clone $contacts)->whereIn('id', $mycontactIds)->where('is_lost', 1)->pluck('id')->toArray();
            $mycontactIds = array_diff($mycontactIds, $lostContactIds);
        }

        if($request->main_type == 'G')
        {
            // Get All contact count
            $allConatcts  = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->count();
            // Get My contact count
            $myContacts   = (clone $contacts)->whereIn('id', array_unique($mycontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->count();
            // Get Lost contact count
            $lostContacts = (clone $contacts)->whereIn('id', array_unique($lostcontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->where('is_lost', 1)
                                            ->count();
            // Get Google contact count
            $googleContacts = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->where('social_type','G')
                                            ->count();
            // Get Linkdin contact count
            $linkdinContacts = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->where('social_type','L')
                                            ->count();
            // Get Outlook contact count
            $outlookContacts = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->where('social_type','O')
                                            ->count();
        }
        else
        {
            // Get All contact count
            $allConatcts  = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->count();
            // Get My contact count
            $myContacts   = (clone $contacts)->whereIn('id', array_unique($mycontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->count();
            // Get Lost contact count
            $lostContacts = (clone $contacts)->whereIn('id', array_unique($lostcontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->where('is_lost', 1)
                                            ->count();
            // Get Google contact count
            $googleContacts = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->where('social_type','G')
                                            ->count();
            // Get Linkdin contact count
            $linkdinContacts = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->where('social_type','L')
                                            ->count();
            // Get Outlook contact count
            $outlookContacts = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->where('social_type','O')
                                            ->count();
        }

        $allPercentage = $myPercentage = $lostPercentage = $linkedinPercentage = $googlePercentage = $outlookPercentage = 0;
        if($allConatcts >0)
        {
            $totalSocialCount = ($linkdinContacts + $googleContacts + $outlookContacts);
            if($totalSocialCount == 0)
            {
                $totalSocialCount = 1;
            }
            // Calculate All,my,lost,linkedin,google and outlook contacts percentage
            $allPercentage      = $allConatcts / $allConatcts * config('constants.total_percentage');
            $lostPercentage     = $lostContacts / $allConatcts * config('constants.total_percentage');
            $myPercentage       = $myContacts / $allConatcts * config('constants.total_percentage');
            $linkedinPercentage = $linkdinContacts / $totalSocialCount * config('constants.total_percentage');
            $googlePercentage   = $googleContacts / $totalSocialCount * config('constants.total_percentage');
            $outlookPercentage  = $outlookContacts / $totalSocialCount * config('constants.total_percentage');
        }

        return ok('Reports percentage',[
            'all_contact'         => $allPercentage - ($myPercentage + $lostPercentage),
            'my_contact'          => $myPercentage,
            'lost_contact'        => $lostPercentage,
            'linkedin_percentage' => $linkedinPercentage,
            'google_percentage'   => $googlePercentage,
            'outlook_percentage'  => $outlookPercentage,
        ]);
    }

    /**
     *  Get Record by month and Year api
     *
     * @param  mixed $request
     * @return void
     */
    public function contactBaseCount(Request $request)
    {
        //Validation
        $this->validate($request,[
            'main_type'  => 'required|in:G,P,CL',// G=contact, P=prospect, CL=client
            'sub_type'   => 'required|in:C,P',// C=company, P=people
            'year'       => 'nullable',
        ]);
        $year = $request->year;
        if(!$request->year){
            $year =  Carbon::now()->subYear();
        }
        //Get Assigned contacts ids
        $assignedContactIds = AssignedContact::where('assigned_to_id', auth()->user()->id)->pluck('contact_id')->toArray();

        $user = Role::where('name', config('constants.super_admin'))->where('id', '=', auth()->user()->role_id)->first();

        $contacts = CompanyContact::query();

        if($user)
        {
            $allcontactIds  = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            $mycontactIds   = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 1)->where('is_lost', 0)->pluck('id')->toArray();
            $lostcontactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_lost', 1)->pluck('id')->toArray();
        }
        else
        {
            $allcontactIds  = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
            $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
            $allcontactIds = array_merge($allcontactIds, $authContactIds);
            $mycontactIds   = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_private', 1)->where('is_lost', 0)->pluck('id')->toArray();
            $lostcontactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_lost', 1)->pluck('id')->toArray();
        }

        if(isset($assignedContactIds))
        {
            $allcontactIds  = array_merge($assignedContactIds, $allcontactIds);
            $mycontactIds   = array_merge($assignedContactIds, $mycontactIds);
            $lostcontactIds = array_merge($assignedContactIds, $lostcontactIds);
        }

        if($mycontactIds)
        {
            $lostContactIds = (clone $contacts)->whereIn('id', $mycontactIds)->where('is_lost', 1)->pluck('id')->toArray();
            $mycontactIds = array_diff($mycontactIds, $lostContactIds);
        }

        if($request->main_type == 'G')
        {
            // Get public,private,lost contacts
            $allConatcts  = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->whereYear('created_at', '=', $year)
                                            ->get()
                                            ->groupBy(function ($data) {
                                                    return Carbon::parse($data->created_at)->format('m');
                                            });
            // Get private contacts
            $myContacts   = (clone $contacts)->whereIn('id', array_unique($mycontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->whereYear('created_at', '=', $year)
                                            ->get()
                                            ->groupBy(function ($data) {
                                                    return Carbon::parse($data->created_at)->format('m');
                                            });
            // Get Lost contacts
            $lostContacts = (clone $contacts)->whereIn('id', array_unique($lostcontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->where('is_lost', 1)
                                            ->whereYear('created_at', '=', $year)
                                            ->get()
                                            ->groupBy(function ($data) {
                                                    return Carbon::parse($data->created_at)->format('m');
                                            });
        }
        else
        {
            // Get public,private,lost contacts
            $allConatcts  = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->whereYear('created_at', '=', $year)
                                            ->get()
                                            ->groupBy(function ($data) {
                                                    return Carbon::parse($data->created_at)->format('m');
                                            });
            // Get private contacts
            $myContacts   = (clone $contacts)->whereIn('id', array_unique($mycontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->whereYear('created_at', '=', $year)
                                            ->get()
                                            ->groupBy(function ($data) {
                                                    return Carbon::parse($data->created_at)->format('m');
                                            });
            // Get Lost contacts
            $lostContacts = (clone $contacts)->whereIn('id', array_unique($lostcontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->whereYear('created_at', '=', $year)
                                            ->get()
                                            ->groupBy(function ($data) {
                                                    return Carbon::parse($data->created_at)->format('m');
                                            });

        }


        $allcontactArr = $mycontactArr = $lostcontactArr = $allcount = $mycount = $lostcount =[];
        foreach ($allConatcts as $key => $value) {
            $allcount[(int)$key] = count($value);
        }
        foreach ($myContacts as $key => $value) {
            $mycount[(int)$key] = count($value);
        }
        foreach ($lostContacts as $key => $value) {
            $lostcount[(int)$key] = count($value);
        }
        $month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        //Set all contacts count array in month wise
        for ($i = 1; $i <= 12; $i++)
        {
            if (!empty($allcount[$i]))
            {
                $allcontactArr[$i]['count'] = $allcount[$i];
            }
            else
            {
                $allcontactArr[$i]['count'] = 0;
            }
            $allcontactArr[$i]['month'] = $month[$i - 1];
        }
        //Set my contacts count array in month wise
        for ($i = 1; $i <= 12; $i++)
        {
            if (!empty($mycount[$i]))
            {
                $mycontactArr[$i]['count'] = $mycount[$i];
            }
            else
            {
                $mycontactArr[$i]['count'] = 0;
            }
            $mycontactArr[$i]['month'] = $month[$i - 1];
        }
        //Set my contacts count array in month wise
        for ($i = 1; $i <= 12; $i++)
        {
            if (!empty($lostcount[$i]))
            {
                $lostcontactArr[$i]['count'] = $lostcount[$i];
            }
            else
            {
                $lostcontactArr[$i]['count'] = 0;
            }
            $lostcontactArr[$i]['month'] = $month[$i - 1];
        }
        // Create response array
        $data = [
            'all_contact'  => array_values($allcontactArr),
            'my_contact'   => array_values($mycontactArr),
            'lost_contact' => array_values($lostcontactArr)
        ];
        return ok(__('Contact Growth base'), $data);
    }

    /**
     * Set count base country api
     *
     * @param  mixed $request
     * @return void
     */
    public function contactcountryCount(Request $request)
    {
        //Validation
        $this->validate($request,[
            'main_type' => 'required|in:G,P,CL',// G=contact, P=prospect, CL=client
            'sub_type'  => 'required|in:C,P',// C=company, P=people
        ]);
        $contactQuery = CompanyContact::query();
        if($request->main_type == 'G')
        {
            //Get all contacts id and country code
            $contacts  = (clone $contactQuery)->with('country_details')
                                        ->where('is_lost', 0)
                                        ->where('sub_type',$request->sub_type)
                                        ->where('company_id',auth()->user()->company_id)
                                        ->select('id','country_code')
                                        ->get();
        }
        else
        {
            //Get all prospect/clients id and country code
            $contacts  = (clone $contactQuery)->with('country_details')
                                    ->where('type',$request->main_type)
                                    ->where('is_lost', 0)
                                    ->where('sub_type',$request->sub_type)
                                    ->where('company_id',auth()->user()->company_id)
                                    ->select('id','country_code')
                                    ->get();
        }
        $countries = $contacts->groupBy('country_details.code')->map(function($row){
                        return $row->count();
        });
        if($countries->count() > 0){
            $countries = $countries;
        }else{
            $countries = (object)[];
        }
        return ok(__('Contacts'), $countries);
    }

    /**
     * company and people import api
     *
     * @param  mixed $request
     * @return void
     */
    public function import(Request $request)
    {
        //Validation
        $this->validate($request, [
            'select_file'  => 'required',
            'type'         => 'required|in:G,P,CL',//G=General,P=Prospect,CL=Client
            'section'      => 'required|in:A,M,L',//A=All, M=My, L=Lost
            'sub_type'     => 'required|in:C,P',//C=Company, P=People
        ]);

        $file = request()->file('select_file');
        $mimeType = strtolower($file->getClientOriginalExtension());

        if (!in_array($mimeType, ['csv', 'xlsx', 'xls'])) {
            return error(__('The selected file must be a file of type: xls, xlsx, csv.'), [], 'validation');
        }

        ini_set('post_max_size', '2000M');
        ini_set('upload_max_filesize', '2000M');
        ini_set('max_execution_time', '300000');
        ini_set('client_max_body_size', '200M');
        ini_set('memory_limit', '5000M');
        ini_set('max_input_time', '30000');

        $is_private = 0;
        $is_lost    = 0;
        // Check if section type M (My Contacts) then set flag 1.
        if($request->section == 'M')
        {
            $is_private = 1;
        }
        // Check if section type L (Lost) then set flag 1.
        if($request->section == 'L')
        {
            $is_lost = 1;
        }

        $data = [
            'type'       => $request->type,
            'is_private' => $is_private,
            'is_lost'    => $is_lost,
            'sub_type'   => $request->sub_type,
        ];

        if ($request->sub_type=='C') {
            try {
                //Impror company contacts
                Excel::import(new CompanyContactImport($data), request()->file('select_file'));
            } catch (Exception $e) {
                return error($e->getMessage(),[],'validation');
            }

            // Get contacts colection from session
            $contacts = session()->get('contact');
            //Get contacts ids from contacts collection
            $contactIds = array_column($contacts,'id');
            //Remove contact session
            session()->forget('contact');
        } else {

            try {
                //Import People Contacts
                Excel::import(new PeopleContactImport($data), request()->file('select_file'));
            } catch (Exception $e) {
                return error($e->getMessage(),[],'validation');
            }

            // Get contacts colection from session
            $contacts = session()->get('peopleContact');
            //Get contacts ids from contacts collection
            $contactIds = array_column($contacts,'id');
            //Remove peopleImport session
            session()->forget('peopleImport');
        }
        // Get Reports
        $result = contactProfileReport($contactIds, $request->sub_type);
        // Get message prefix
        $prefix = prefix($request->type);
        return ok($prefix.' imported successfully',[
            'count'           => $result['count'] ?? 0,
            'unique_rows'     => $result['unique_rows'] ?? 0,
            'duplicate_rows'  => $result['duplicate_rows'] ?? 0,
            'complete_data'   => $result['complete_data'] ?? 0,
            'incomplete_data' => $result['incomplete_data'] ?? 0,
            'contact_ids'     => $result['contact_ids'] ?? []
        ]);
    }
}
