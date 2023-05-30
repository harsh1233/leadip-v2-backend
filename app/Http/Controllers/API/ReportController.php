<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CompanyContact;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Carbon;
use Excel;
use App\Imports\CompanyContactImport;
use App\Imports\CompanyContactImportNew;
use App\Imports\PeopleContactImport;
use App\Imports\PeopleContactImportNew;
use App\Models\AssignedContact;
use App\Models\Role;

class ReportController extends Controller
{
    /* Get contacts count */
    public function contactCount(Request $request){
        $this->validate($request,[
            'main_type'=>'required|in:G,P,CL',
            'sub_type' =>'required|in:C,P',
        ]);
        if($request->main_type=='G'){
            $companyContact         = CompanyContact::query()->where('company_id',auth()->user()->company_id)->where('sub_type',$request->sub_type);
            $allConatct             = (clone $companyContact)->count();
        }else{
            $companyContact         = CompanyContact::query()->where('company_id',auth()->user()->company_id)->where('type',$request->main_type)->where('sub_type',$request->sub_type);
            $allConatct             = (clone $companyContact)->count();
        }
        // $companyContact         = CompanyContact::query()->where('company_id',auth()->user()->company_id)->where('type',$request->main_type)->where('sub_type',$request->sub_type);
        /*Get count of all contact, my contact and lost contact */


        $myContact              = (clone $companyContact)->where('is_private',1)->count();

        $lostContact            = (clone $companyContact)->where('is_lost',1)->count();

        $noOfColumns            = (clone $companyContact)->where('is_import',1)->count();

        $duplicatePhoneNumber   = (clone $companyContact)->groupBy('phone_number')->having(DB::raw('count(phone_number)'), '>', 1)->pluck('phone_number')->toArray();
        $duplicateEmail         = (clone $companyContact)->groupBy('email')->having(DB::raw('count(email)'), '>', 1)->pluck('email')->count();
        //dd($duplicatePhoneNumber);
        /* Get percentage of incomplete and completed data */

        $contactCount           = (clone $companyContact)->count();



        $emailCount             = (clone $companyContact)->whereNotNull('email')->count();
        $phoneNumber            = (clone $companyContact)->whereNotNull('phone_number')->count();
        $country                = (clone $companyContact)->whereNotNull('country_code')->count();
        $city                   = (clone $companyContact)->whereNotNull('city_id')->count();
        $category               = (clone $companyContact)->whereNotNull('category')->count();
        $profilePicture         = (clone $companyContact)->whereNotNull('profile_picture')->count();
        $industry               = (clone $companyContact)->whereNotNull('industry')->count();
        $role                   = (clone $companyContact)->whereNotNull('role')->count();
        $pointOfContact         = (clone $companyContact)->whereNotNull('point_of_contact')->count();
        if($request->sub_type =='C'){
            $uniqueRows         = (clone $companyContact)->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();

            $phoneNumbers       = (clone $companyContact)->whereIn('phone_number',$duplicatePhoneNumber)->pluck('phone_number')->toArray();
            $duplicateRows      = collect( $phoneNumbers)->duplicates()->count();

            $nameCount          = (clone $companyContact)->whereNotNull('company_name')->count();
            $completenessData   = (clone $companyContact)->whereNotNull(['company_name','email','phone_number','country_code','city_id','category'])->count();
            if($contactCount > 0 && $completenessData > 0){
                $namePercentage          = $nameCount   / $contactCount * config('constants.company_name_percentahge');

                $emailPercentage         = $emailCount  / $contactCount * config('constants.company_email_percentage');

                $phonePercentage         = $phoneNumber / $contactCount * config('constants.company_phone_percentage');

                $countryPercentage       = $country     / $contactCount * config('constants.country_percentage');

                $company_city_percentage = $city        / $contactCount * config('constants.company_city_percentage');
                $categoryPercentage      = $category    / $contactCount * config('constants.category_percentage');
                $profilePicturePercentage=$profilePicture/$contactCount * config('constants.profile_picture');
                $industryPercentage      =$industry      /$contactCount * config('constants.industry');
                $completeData            = $namePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $company_city_percentage +$categoryPercentage+$profilePicturePercentage+$industryPercentage  ;
                //$completeData       = $completenessData / $contactCount * $totalPercentage;
                $incompleteData          = config('constants.total_percentage') - $completeData  ;
            }else{
                $incompleteData          = config('constants.total_percentage');
            }
        }else{
            $duplicateName               = (clone $companyContact)->groupBy('first_name')->having(DB::raw('count(first_name)'), '>', 1)->pluck('first_name')->toArray();

            $uniqueRows                  = (clone $companyContact)->whereNotIn('first_name',$duplicateName)->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();
            $contacts                    = (clone $companyContact)->get()->map->only('first_name', 'phone_number');
            $duplicateRows               =  $contacts->duplicates('first_name','phone_number')->count();

            $firstNameCount              = (clone $companyContact)->whereNotNull('first_name')->count();
            $lastNameCount               = (clone $companyContact)->whereNotNull('last_name')->count();

            $completenessData            = (clone $companyContact)->whereNotNull(['first_name','last_name','email','phone_number','country_code','city_id'])->count();
            if($contactCount > 0 && $completenessData > 0){
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
                //$categoryPercentage = $category    / $contactCount * config('constants.category_percentage');
                $completeData            = $firstNamePercentage+ $lastNamePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $cityPercentage+$profilePicturePercentage+$industryPercentage+$rolePercentage+$pointOfContactPercentage ;
                //$completeData       = $completenessData / $contactCount * $totalPercentage;
                $incompleteData          = config('constants.total_percentage') - $completeData  ;
            }else{
                $incompleteData          = config('constants.total_percentage');
            }
        }


        return ok('Reports count',[
            'all_contact'    => $allConatct,
            'my_contact'     => $myContact,
            'lost_contact'   => $lostContact,
            'no_of_columns'  => $noOfColumns,
            'unique_rows'    => $uniqueRows,
            'duplicate_rows' => $duplicateRows,
            'complete_data'  => $completeData ?? 0,
            'incomplete_data'=> $duplicateEmail ?? 0
        ]);
    }

    /* Get contacts count */
    public function contactCountNew(Request $request){
        $this->validate($request,[
            'main_type'=>'required|in:G,P,CL',
            'sub_type' =>'required|in:C,P',
        ]);

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
            $allConatct  = (clone $contacts)->whereIn('id', array_unique($allcontactIds))->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->count();
            $myContact   = (clone $contacts)->whereIn('id', array_unique($mycontactIds))->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->count();
            $lostContact = (clone $contacts)->whereIn('id', array_unique($lostcontactIds))->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->where('is_lost', 1)->count();
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
                $uniqueRows         = (clone $companyContact)->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();
                $phoneNumbers       = (clone $companyContact)->whereIn('phone_number',$duplicatePhoneNumber)->pluck('phone_number')->toArray();
                $duplicateRows      = collect( $phoneNumbers)->duplicates()->count();
                $contactCount       = (clone $companyContact)->count();
                $completenessData   = (clone $companyContact)->whereNotNull(['company_name','email','phone_number','country_code','city_id','category'])->count();
                $completeData = 0;
                $incompleteData = 0;
                if($contactCount > 0 && $completenessData > 0)
                {
                    $namePercentage          = $nameCount   / $contactCount * config('constants.company_name_percentahge');

                    $emailPercentage         = $emailCount  / $contactCount * config('constants.company_email_percentage');

                    $phonePercentage         = $phoneNumber / $contactCount * config('constants.company_phone_percentage');

                    $countryPercentage       = $country     / $contactCount * config('constants.country_percentage');

                    $company_city_percentage = $city        / $contactCount * config('constants.company_city_percentage');
                    $categoryPercentage      = $category    / $contactCount * config('constants.category_percentage');
                    $profilePicturePercentage=$profilePicture/$contactCount * config('constants.profile_picture');
                    $industryPercentage      =$industry      /$contactCount * config('constants.industry');
                    $completeData            = $namePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $company_city_percentage +$categoryPercentage+$profilePicturePercentage+$industryPercentage;

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
                $contactCount       = (clone $companyContact)->count();
                $duplicateName               = (clone $companyContact)->groupBy('first_name')->having(DB::raw('count(first_name)'), '>', 1)->pluck('first_name')->toArray();
                $uniqueRows                  = (clone $companyContact)->whereNotIn('first_name',$duplicateName)
                                                ->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();
                $contacts                    = (clone $companyContact)->get()->map->only('first_name', 'phone_number');
                $duplicateRows               =  $contacts->duplicates('first_name','phone_number')->count();

                $firstNameCount              = (clone $companyContact)->whereNotNull('first_name')->count();
                $lastNameCount               = (clone $companyContact)->whereNotNull('last_name')->count();
                $completenessData            = (clone $companyContact)->whereNotNull(['first_name','last_name','email','phone_number','country_code','city_id'])->count();
                $completeData = 0;
                $incompleteData = 0;
                if($contactCount > 0 && $completenessData > 0)
                {
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
                    //$categoryPercentage = $category    / $contactCount * config('constants.category_percentage');
                    $completeData            = $firstNamePercentage+ $lastNamePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $cityPercentage+$profilePicturePercentage+$industryPercentage+$rolePercentage+$pointOfContactPercentage ;

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
            $allConatct  = (clone $contacts)->whereIn('id', array_unique($allcontactIds))->where('type', '=', $request->main_type)->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->count();
            $myContact   = (clone $contacts)->whereIn('id', array_unique($mycontactIds))->where('type', '=', $request->main_type)->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->count();
            $lostContact = (clone $contacts)->whereIn('id', array_unique($lostcontactIds))->where('type', '=', $request->main_type)->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->where('is_lost', 1)->count();

            $duplicatePhoneNumber   = (clone $contacts)->whereIn('id', array_unique($allcontactIds))->where('company_id', auth()->user()->company_id)->where('type', '=', $request->main_type)->where('sub_type', '=', $request->sub_type)->groupBy('phone_number')->having(DB::raw('count(phone_number)'), '>', 1)->pluck('phone_number')->toArray();

            $companyContact = (clone $contacts)->whereIn('id', array_unique($allcontactIds))->where('type', '=', $request->main_type)->where('sub_type',$request->sub_type)->where('company_id', auth()->user()->company_id);
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
                $contactCount       = (clone $companyContact)->count();
                $completenessData   = (clone $companyContact)->whereNotNull(['company_name','email','phone_number','country_code','city_id','category'])->count();
                $completeData = 0;
                $incompleteData = 0;
                if($contactCount > 0 && $completenessData > 0)
                {
                    $namePercentage          = $nameCount   / $contactCount * config('constants.company_name_percentahge');

                    $emailPercentage         = $emailCount  / $contactCount * config('constants.company_email_percentage');

                    $phonePercentage         = $phoneNumber / $contactCount * config('constants.company_phone_percentage');

                    $countryPercentage       = $country     / $contactCount * config('constants.country_percentage');

                    $company_city_percentage = $city        / $contactCount * config('constants.company_city_percentage');
                    $categoryPercentage      = $category    / $contactCount * config('constants.category_percentage');
                    $profilePicturePercentage=$profilePicture/$contactCount * config('constants.profile_picture');
                    $industryPercentage      =$industry      /$contactCount * config('constants.industry');
                    $completeData            = $namePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $company_city_percentage +$categoryPercentage+$profilePicturePercentage+$industryPercentage  ;
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
                $uniqueRows                  = (clone $companyContact)->whereNotIn('first_name',$duplicateName)
                                                ->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();
                $contacts                    = (clone $companyContact)->get()->map->only('first_name', 'phone_number');
                $duplicateRows               =  $contacts->duplicates('first_name','phone_number')->count();

                $firstNameCount              = (clone $companyContact)->whereNotNull('first_name')->count();
                $lastNameCount               = (clone $companyContact)->whereNotNull('last_name')->count();
                $contactCount                = (clone $companyContact)->count();
                $completenessData            = (clone $companyContact)->whereNotNull(['first_name','last_name','email','phone_number','country_code','city_id'])->count();
                $completeData = 0;
                $incompleteData = 0;
                if($contactCount > 0 && $completenessData > 0)
                {
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
                    //$categoryPercentage = $category    / $contactCount * config('constants.category_percentage');
                    $completeData            = $firstNamePercentage+ $lastNamePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $cityPercentage+$profilePicturePercentage+$industryPercentage+$rolePercentage+$pointOfContactPercentage ;
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
                    //$completeData       = $completenessData / $contactCount * $totalPercentage;
                    //$incompleteData          = config('constants.total_percentage') - $completeData  ;
                }
            }
        }

        return ok('Reports count',[
            'all_contact'    => $allConatct ?? 0,
            'my_contact'     => $myContact ?? 0,
            'lost_contact'   => $lostContact ?? 0,
            'no_of_columns'  => $allConatct ?? 0,
            'unique_rows'    => $uniqueRows ?? 0,
            'duplicate_rows' => $duplicateRows ?? 0,
            'complete_data'  => $completeData ?? 0,
            'incomplete_data'=> $incompleteData ?? 0
        ]);

    }

    /* Get contacts percentage */
    public function contactPercentage(Request $request){
        $this->validate($request,[
            'main_type'=>'required|in:G,P,CL',
            'sub_type' =>'required|in:C,P'
        ]);
        $companyContact     = CompanyContact::query()->where('company_id',auth()->user()->company_id)->where('type',$request->main_type)->where('sub_type',$request->sub_type);

        $total              = (clone $companyContact)->count();
        $allConatct         = (clone $companyContact)->where('section','A')->count();

        $myContact          = (clone $companyContact)->where('section','M')->count();


        $lostContact        = (clone $companyContact)->where('section','L')->count();

        $linkedinCount      = (clone $companyContact)->where('social_type','L')->count();

        $googleCount        = (clone $companyContact)->where('social_type','G')->count();

        $outlookCount       = (clone $companyContact)->where('social_type','O')->count();

        if($total >0){
            $allPercentage      = $allConatct / $total * config('constants.total_percentage');
            $lostPercentage     = $lostContact / $total * config('constants.total_percentage');
            $myPercentage       = $myContact / $total * config('constants.total_percentage');
            $linkedinPercentage = $linkedinCount / $total * config('constants.total_percentage');
            $googlePercentage   = $googleCount / $total * config('constants.total_percentage');
            $outlookPercentage  = $outlookCount / $total * config('constants.total_percentage');
        }
        return ok('Reports percentage',[
            'all_contact'        =>$allPercentage ?? 0,
            'my_contact'         =>$myPercentage ?? 0,
            'lost_contact'       =>$lostPercentage ?? 0,
            'linkedin_percentage'=>$linkedinPercentage ?? 0,
            'google_percentage'  =>$googlePercentage ?? 0,
            'outlook_percentage' =>$outlookPercentage ?? 0,
        ]);
    }

    /* Get contacts percentage New*/
    public function contactPercentageNew(Request $request)
    {
        $this->validate($request,[
            'main_type'=>'required|in:G,P,CL',
            'sub_type' =>'required|in:C,P'
        ]);

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
            $allPercentage      = ($allConatcts / $allConatcts) * config('constants.total_percentage');
            $lostPercentage     = ($lostContacts / $allConatcts) * config('constants.total_percentage');
            $myPercentage       = ($myContacts / $allConatcts) * config('constants.total_percentage');
            $linkedinPercentage = ($linkdinContacts / $totalSocialCount ) * config('constants.total_percentage');
            $googlePercentage   = ($googleContacts / $totalSocialCount ) * config('constants.total_percentage');
            $outlookPercentage  = ($outlookContacts / $totalSocialCount ) * config('constants.total_percentage');
        }

        return ok('Reports percentage',[
            'all_contact'         => ($allPercentage - ($myPercentage + $lostPercentage)),
            'my_contact'          => $myPercentage,
            'lost_contact'        => $lostPercentage,
            'linkedin_percentage' => $linkedinPercentage,
            'google_percentage'   => $googlePercentage,
            'outlook_percentage'  => $outlookPercentage,
        ]);
    }

    /*Get Record by month and Year*/

    public function contactBaseCount(Request $request){
        $this->validate($request,[
            'main_type'=>'required|in:G,P,CL',
            'section'  =>'required|in:A,M,L',
            'sub_type' =>'required|in:C,P',
            'year'     =>'nullable'
        ]);
        $year = $request->year;
        if(!$request->year){
            $year =  Carbon::now()->subYear();
        }
        $contacts = CompanyContact::where('type',$request->main_type)->where('section',$request->section)->where('sub_type',$request->sub_type)->where('company_id',auth()->user()->company_id)->whereYear('created_at', '=', $year)->get()->groupBy(function ($data) {
                return Carbon::parse($data->created_at)->format('m');
        });
        $contactcount = [];
        $contactArr   = [];
        foreach ($contacts as $key => $value) {
            $contactcount[(int)$key] = count($value);
        }
        $month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        for ($i = 1; $i <= 12; $i++) {
            if (!empty($contactcount[$i])) {
                $contactArr[$i]['count'] = $contactcount[$i];
            } else {
                $contactArr[$i]['count'] = 0;
            }
                $contactArr[$i]['month'] = $month[$i - 1];
        }
                return ok(__('Contact Growth base'),array_values($contactArr));
    }

     /*Get Record by month and Year*/
    public function contactBaseCountNew(Request $request)
    {
        $this->validate($request,[
            'main_type'  => 'required|in:G,P,CL',
            'sub_type'   => 'required|in:C,P',
            'year'       => 'nullable',
        ]);
        $year = $request->year;
        if(!$request->year){
            $year =  Carbon::now()->subYear();
        }

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
            $allConatcts  = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->whereYear('created_at', '=', $year)
                                            ->get()
                                            ->groupBy(function ($data) {
                                                    return Carbon::parse($data->created_at)->format('m');
                                            });
            $myContacts   = (clone $contacts)->whereIn('id', array_unique($mycontactIds))
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->whereYear('created_at', '=', $year)
                                            ->get()
                                            ->groupBy(function ($data) {
                                                    return Carbon::parse($data->created_at)->format('m');
                                            });
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
            $allConatcts  = (clone $contacts)->whereIn('id', array_unique($allcontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->whereYear('created_at', '=', $year)
                                            ->get()
                                            ->groupBy(function ($data) {
                                                    return Carbon::parse($data->created_at)->format('m');
                                            });
            $myContacts   = (clone $contacts)->whereIn('id', array_unique($mycontactIds))
                                            ->where('type', '=', $request->main_type)
                                            ->where('sub_type', '=', $request->sub_type)
                                            ->where('company_id', auth()->user()->company_id)
                                            ->whereYear('created_at', '=', $year)
                                            ->get()
                                            ->groupBy(function ($data) {
                                                    return Carbon::parse($data->created_at)->format('m');
                                            });
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
        $data = [
            'all_contact' => array_values($allcontactArr),
            'my_contact' => array_values($mycontactArr),
            'lost_contact' => array_values($lostcontactArr)
        ];
        return ok(__('Contact Growth base'), $data);
    }

    /* Set count base country */
    public function contactcountryCount(Request $request){
        $this->validate($request,[
            'main_type'=>'required|in:G,P,CL',
            'sub_type' =>'required|in:C,P'
        ]);
        // $contacts  = CompanyContact::with('country_details')->where('type',$request->main_type)->whereIn('section',['A','M'])->where('sub_type',$request->sub_type)->where('company_id',auth()->user()->company_id)->select('id','country_code')->get();
        if($request->main_type == 'G')
        {
            $contacts  = CompanyContact::with('country_details')
                                        ->where('is_lost', 0)
                                        ->where('sub_type',$request->sub_type)
                                        ->where('company_id',auth()->user()->company_id)
                                        ->select('id','country_code')
                                        ->get();
        }
        else
        {
            $contacts  = CompanyContact::with('country_details')
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
        if($countries->count() >0){
            $countries = $countries;
        }else{
            $countries = (object)[];
        }
        return ok(__('Contacts'),$countries);
    }
    /* import functionality */
    public function import(Request $request){

        $v = $this->validate($request, [
            'select_file'  => 'required',
            'type'         =>  'required|in:G,P,CL',
            'section'      =>  'required|in:A,M,L',
            'sub_type'     =>  'required|in:C,P',
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
            //'section' => $request->section,
            'is_private' => $is_private,
            'is_lost'    => $is_lost,
            'sub_type'   => $request->sub_type,
        ];

        $url = env('APP_ENV') == 'local' ? config('constants.LOCAL_WEB_URL') : config('constants.TEST_SERVER_URL');

        if($request->sub_type=='C'){
            try {
                //Excel::import(new PeopleContactImport($data), request()->file('select_file'));
                Excel::import(new CompanyContactImportNew($data), request()->file('select_file'));
            } catch (Exception $e) {
                return redirect()->away($url . '?auth_error=' . $e->getMessage());
            }
            $ids=session()->get('contact');
            $contactIds             = array_column($ids,'id');
            $companyContact         = CompanyContact::where('type',$request->type)->where('sub_type',$request->sub_type)->whereIn('id',$contactIds);
            $duplicatePhoneNumber   = (clone $companyContact)->groupBy('phone_number')->having(DB::raw('count(phone_number)'), '>', 1)->pluck('phone_number')->toArray();
            $contactCount           = (clone $companyContact)->count();
            //dd($contactCount);
            $emailCount             = (clone $companyContact)->whereNotNull('email')->count();
            $phoneNumber            = (clone $companyContact)->whereNotNull('phone_number')->count();
            $country                = (clone $companyContact)->whereNotNull('country_code')->count();
            $city                   = (clone $companyContact)->whereNotNull('city_id')->count();
            $category               = (clone $companyContact)->whereNotNull('category')->count();
            $uniqueRows             = (clone $companyContact)->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();

            $phoneNumbers           = (clone $companyContact)->whereIn('phone_number',$duplicatePhoneNumber)->pluck('phone_number')->toArray();
            $duplicateRows          = collect( $phoneNumbers)->duplicates()->count();

            $nameCount              = (clone $companyContact)->whereNotNull('company_name')->count();
            $profilePicture         = (clone $companyContact)->whereNotNull('profile_picture')->count();
            $industry               = (clone $companyContact)->whereNotNull('industry')->count();
            $completenessData       = (clone $companyContact)->whereNotNull(['company_name','email','phone_number','country_code','city_id','category'])->count();
            if($contactCount > 0 && $completenessData >0){
                $namePercentage          = $nameCount   / $contactCount * config('constants.company_name_percentahge');

                $emailPercentage         = $emailCount  / $contactCount * config('constants.company_email_percentage');

                $phonePercentage         = $phoneNumber / $contactCount * config('constants.company_phone_percentage');

                $countryPercentage       = $country     / $contactCount * config('constants.country_percentage');

                $company_city_percentage = $city        / $contactCount * config('constants.company_city_percentage');
                $categoryPercentage      = $category    / $contactCount * config('constants.category_percentage');
                $profilePicturePercentage= $profilePicture/  $contactCount * config('constants.profile_picture');
                $industryPercentage      =$industry/  $contactCount * config('constants.industry');
                $completeData            = $namePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $company_city_percentage +$categoryPercentage+$profilePicturePercentage+ $industryPercentage ;

                //$completeData       = $completenessData / $contactCount * $totalPercentage;
                $incompleteData          = config('constants.total_percentage') - $completeData  ;
            }else{
                    $incompleteData      = config('constants.total_percentage');
                }
                session()->forget('contact');
        }else{

            try {
                //Excel::import(new CompanyContactImport($data), request()->file('select_file'));
                Excel::import(new PeopleContactImportNew($data), request()->file('select_file'));
            } catch (Exception $e) {
                return redirect()->away($url . '?auth_error=' . $e->getMessage());
            }
            $ids=session()->get('peopleContact');
            $contactIds             = array_column($ids,'id');
            $companyContact         = CompanyContact::where('type',$request->type)->where('sub_type',$request->sub_type)->whereIn('id',$contactIds);
            $duplicatePhoneNumber   = (clone $companyContact)->groupBy('phone_number')->having(DB::raw('count(phone_number)'), '>', 1)->pluck('phone_number')->toArray();
            $contactCount           = (clone $companyContact)->count();
            //dd($contactCount);
            $emailCount             = (clone $companyContact)->whereNotNull('email')->count();
            $phoneNumber            = (clone $companyContact)->whereNotNull('phone_number')->count();
            $country                = (clone $companyContact)->whereNotNull('country_code')->count();
            $city                   = (clone $companyContact)->whereNotNull('city_id')->count();
            $category               = (clone $companyContact)->whereNotNull('category')->count();

                $duplicateName      = (clone $companyContact)->groupBy('first_name')->having(DB::raw('count(first_name)'), '>', 1)->pluck('first_name')->toArray();

                $uniqueRows         = (clone $companyContact)->whereNotIn('first_name',$duplicateName)->WhereNotIn('phone_number',$duplicatePhoneNumber)->count();
                $contacts           = (clone $companyContact)->get()->map->only('first_name', 'phone_number');
                $duplicateRows      =  $contacts->duplicates('first_name','phone_number')->count();

                $firstNameCount     = (clone $companyContact)->whereNotNull('first_name')->count();
                $lastNameCount      = (clone $companyContact)->whereNotNull('last_name')->count();
                $profilePicture     = (clone $companyContact)->whereNotNull('profile_picture')->count();
                $industry           = (clone $companyContact)->whereNotNull('industry')->count();
                $role               = (clone $companyContact)->whereNotNull('role')->count();
                $pointOfContact     = (clone $companyContact)->whereNotNull('point_of_contact')->count();
                $completenessData   = (clone $companyContact)->whereNotNull(['first_name','last_name','email','phone_number','country_code','city_id'])->count();
                if($contactCount > 0 && $completenessData){
                    $firstNamePercentage     = $firstNameCount  / $contactCount * config('constants.first_name_percentage');
                    $lastNamePercentage      = $lastNameCount   / $contactCount * config('constants.last_name_pecentage');
                    $emailPercentage         = $emailCount      / $contactCount * config('constants.people_email_percentage');

                    $phonePercentage         = $phoneNumber / $contactCount * config('constants.people_phone_percentage');

                    $countryPercentage       = $country     / $contactCount * config('constants.people_country_percentage');

                    $cityPercentage          = $city        / $contactCount * config('constants.people_city_percentage');
                    $profilePicturePercentage= $profilePicture/  $contactCount * config('constants.profile_picture');
                    $industryPercentage      = $industry/  $contactCount * config('constants.industry');
                    $rolePercentage          = $role/  $contactCount * config('constants.role');
                    $pointOfContactPercentage= $pointOfContact/  $contactCount * config('constants.point_of_contact');
                    $completeData            = $firstNamePercentage+ $lastNamePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $cityPercentage+$profilePicturePercentage+$industryPercentage+$rolePercentage+$pointOfContactPercentage;
                    //$completeData       = $completenessData / $contactCount * $totalPercentage;
                    $incompleteData          = config('constants.total_percentage') - $completeData  ;
                }else{
                    $incompleteData          = config('constants.total_percentage');
                }
                session()->forget('peopleImport');
            }
            if($request->type=='G'){
                $type ='Contact(s)';
            }else if($request->type=='P'){
                $type ='Prospect(s)';
            }else{
                $type ='Client(s)';
            }
                return ok($type.' imported successfully',[
                    'count'          => $contactCount,
                    'unique_rows'    => $uniqueRows,
                    'duplicate_rows' => $duplicateRows,
                    'complete_data'  => $completeData ?? 0,
                    'incomplete_data'=> 0,
                    'contact_ids'    => $contactIds
                ]);
    }
}
