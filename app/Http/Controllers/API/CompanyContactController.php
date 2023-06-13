<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanyContact;
use App\Models\AssignedContact;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ContactExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Exports\CompanyContactExport;
use App\Models\CompanyPeople;
use App\Models\SystemNotification;
use App\Models\Role;
use App\Models\Protocol;

class CompanyContactController extends Controller
{
    /**
     * create Contact api
     *
     * @param  mixed $request
     * @return void
     */
    public function create(Request $request)
    {
        //Validation
        $this->validate($request, [
            'company_id'            =>  'required|exists:companies,id',
            'profile_picture'       =>  'nullable|mimes:jpg,jpeg,png||max:5120',
            'type'                  =>  'required|in:G,P,CL',
            'sub_type'              =>  'required|in:C,P',
            'company_name'          =>  'nullable|required_if:sub_type,==,C',
            'priority'              =>  'nullable|in:H,M,L',
            'category'              =>  'nullable|required_if:sub_type,==,C|in:H,R',
            'role'                  =>  'nullable|required_if:sub_type,==,P',
            'point_of_contact'      =>  'nullable|required_if:sub_type,==,P',
            'email'                 =>  'required|email',
            'phone_number'          =>  'nullable',
            'client_since'          =>  'nullable',
            'first_name'            =>  'required_if:sub_type,==,P',
            'last_name'             =>  'required_if:sub_type,==,P',
            'country_code'          =>  'required|exists:countries,code',
            'city_id'               =>  'required|exists:cities,id',
            'recently_contacted_by' =>  'nullable|array',
            'covered_regions'       =>  'nullable|array|exists:countries,code',
            'areas_of_expertise'    =>  'nullable|array',
            'ongoing_work'          =>  'nullable|array',
            'potencial_for'         =>  'nullable|array',
            'industry'              =>  'nullable|array',
            'people_id'             =>  'nullable|array|exists:contacts,id',
            'existing_company_id'   =>  'nullable|exists:contacts,id',
            'marketing'             =>  'nullable|array|in:LC,LCPF,O,EN,CM',
            'slag'                  =>  'nullable',
            'assign_teams'          => 'nullable|array',
            'assign_teams.*' => 'nullable|exists:users,id',
            'is_private'            =>'nullable|boolean',
            'is_lost'               =>'nullable|boolean',
        ], [
            'profile_picture'     => 'The profile picture must not be greater than 5 MB.'
        ]);

        $query = CompanyContact::query();
        $existContact = (clone $query)->where('email', '=', $request->email)->where('company_id', auth()->user()->company_id)->first();

        if ($existContact) {
            return error(__('Email already exists!'), [], 'validation');
        }

        if ($request->sub_type == 'C') {
            $email  = $request->email;
            $domain = substr($email, strpos($email, '@') + 1);

            // Skip the duplicate check for gmail.com and yahoo.com domains
            if ($domain != 'hotmail.com' && $domain != 'yahoo.com' && $domain != 'gmail.com' && $domain != 'icloud.com' && $domain != 'outlook.com') {
                $compnyId = (clone $query)->where('email', 'like', "%@{$domain}")->where('sub_type', 'C')->where('company_id', auth()->user()->company_id)->exists();
                if ($compnyId) {
                    return error(__('Company already exists'), [], 'validation');
                }
            }
        }
        $url = null;

        // Image store in s3 bucket
        if (isset($request->profile_picture)) {
            $file = $request['profile_picture'];
            //store your file into database
            $url = "Contact" . "/" . $file->getClientOriginalName() . "_" . mt_rand(1000000000, time());
            Storage::disk('s3')->put($url, file_get_contents($file));
            $url = Storage::disk('s3')->url($url);
        }
        //Get existing company using company id.
        $companyName = (clone $query)->where('id', $request->existing_company_id)->first();
        if ($companyName) {
            $request['company_name'] = $companyName->company_name;
        }
        $input = $request->only('company_id', 'type','is_private','is_lost', 'sub_type', 'company_name', 'priority', 'category', 'point_of_contact', 'role', 'email', 'phone_number', 'client_since', 'first_name', 'last_name', 'country_code', 'city_id') + ['profile_picture' => $url];

        $input['recently_contacted_by'] = $request->input('recently_contacted_by') ? serialize($request->input('recently_contacted_by')) : null;
        $input['covered_regions']       = $request->input('covered_regions') ? serialize($request->input('covered_regions')) : null;
        $input['areas_of_expertise']    = $request->input('areas_of_expertise') ? serialize($request->input('areas_of_expertise')) : null;
        $input['ongoing_work']          = $request->input('ongoing_work') ? serialize($request->input('ongoing_work')) : null;
        $input['potencial_for']         = $request->input('potencial_for') ? serialize($request->input('potencial_for')) : null;
        $input['industry']              = $request->input('industry') ? serialize($request->input('industry')) : null;
        $input['marketing']             = $request->input('marketing') ? serialize($request->input('marketing')) : null;
        //Create contact
        $companyContact =  (clone $query)->create($input);

        $slug = Str::slug($companyContact->id);

        // Get Contact slag url
        $fianlslug = contactSlugUrl($request->type, $slug);

        // Update contact slag url
        $companyContact->update([
            'slag' => $fianlslug
        ]);

        /* Store people record if domain match */
        if (!$request->existing_company_id) {
            // Company People Mapping
            contactCompanyPeopleMap($companyContact);
        }
        /* Store record when add to team from team section*/
        if ($request->existing_company_id) {
            $queryData->create(['company_id' => $request->existing_company_id, 'people_id' => $companyContact->id]);
        }

        /* Assign contact to the team member */
        if ($request->assign_teams) {
            $assignedContact = [];
            foreach ($request->assign_teams as $team) {
                $assignedContact[] =  [
                    'id'             => Str::uuid(),
                    'contact_id'     => $companyContact->id,
                    'assigned_to_id' =>  $team,
                    'assigned_by_id' => auth()->user()->id,
                ];
            }
            AssignedContact::insert($assignedContact);
            /* Sent notifcation when assing contact */
            $this->sentAssignNotification($request->assign_teams, [$companyContact->id]);
        }

        // send contact create notification
        sentMultipleNotification($request->type, $request->sub_type);
        // Set response message sucess prefix.
        if($request->type == 'P')
        {
            $preFix = "Prospect";
        }
        elseif($request->type == 'CL')
        {
            $preFix = "Client";
        }
        else
        {
            $preFix = "Contact";
        }
        return ok($preFix.' created successfully.', $companyContact);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        //Validation
        $this->validate($request, [
            'contact_id'            =>  'required|exists:contacts,id',
            'company_id'            =>  'required|exists:companies,id',
            'profile_picture'       =>  'nullable|mimes:jpg,jpeg,png||max:5120',
            'type'                  =>  'required|in:G,P,CL',
            'sub_type'              =>  'required|in:C,P',
            'company_name'          =>  'nullable|required_if:sub_type,==,C',
            'priority'              =>  'nullable|in:H,M,L',
            'category'              =>  'nullable|required_if:sub_type,==,C|in:H,R',
            'role'                  =>  'nullable|required_if:sub_type,==,P',
            'point_of_contact'      =>  'nullable|required_if:sub_type,==,P',
            'email'                 =>  'required|email',
            'phone_number'          =>  'nullable',
            'client_since'          =>  'nullable',
            'first_name'            =>  'required_if:sub_type,==,P',
            'last_name'             =>  'required_if:sub_type,==,P',
            'country_code'          =>  'required|exists:countries,code',
            'city_id'               =>  'required|exists:cities,id',
            'recently_contacted_by' =>  'nullable|array',
            'covered_regions'       =>  'nullable|array|exists:countries,code',
            'areas_of_expertise'    =>  'nullable|array',
            'ongoing_work'          =>  'nullable|array',
            'potencial_for'         =>  'nullable|array',
            'people_id'             =>  'nullable|array|exists:contacts,id',
            'industry'              =>  'nullable|array',
            'marketing'             =>  'nullable|array|in:LC,LCPF,O,EN,CM',
            'slag'                  =>  'nullable',
            'is_private'            =>  'nullable|boolean',
            'is_lost'               =>  'nullable|boolean',
            'assign_teams'          => 'nullable|exists:users,id',
        ], [
            'profile_picture.max'   => 'The profile picture must not be greater than 5 MB.'
        ]);

        $query = CompanyContact::query();
        // Get contact
        $companyContact = (clone $query)->where('id', $request->contact_id)->first();

        // If contact not found then throw error
        if (!$companyContact) {
            return error(__('Contact not found.'), [], 'validation');
        }

        $emailExists = (clone $query)->where('id', '!=', $request->contact_id)->where('email', '=', $request->email)->where('company_id', auth()->user()->company_id)->first();

        if ($emailExists) {
            return error(__('Email already exists!'), [], 'validation');
        }

        $input = $request->only('company_id', 'type','is_private','is_lost', 'company_name', 'priority', 'category', 'point_of_contact', 'role', 'email', 'phone_number', 'client_since', 'first_name', 'last_name', 'country_code', 'city_id', 'slag');

        $changes = [];

        // Image store in s3 bucket
        if (isset($request->profile_picture)) {
            $file = $request['profile_picture'];
            //store your file into database
            $url = "Contact" . "/" . $file->getClientOriginalName() . "_" . mt_rand(1000000000, time());
            Storage::disk('s3')->put($url, file_get_contents($file));
            $url = Storage::disk('s3')->url($url);
            $input['profile_picture'] = $url;
            $changes[] = 'Profile picture was updated';
        }

        $input['recently_contacted_by'] = $request->input('recently_contacted_by') ? serialize($request->input('recently_contacted_by')) : null;
        $input['covered_regions']       =  $request->input('covered_regions') ? serialize($request->input('covered_regions')) : null;
        $input['areas_of_expertise']    =  $request->input('areas_of_expertise') ? serialize($request->input('areas_of_expertise')) : null;
        $input['ongoing_work']          =  $request->input('ongoing_work') ? serialize($request->input('ongoing_work')) : null;
        $input['potencial_for']         =  $request->input('potencial_for') ? serialize($request->input('potencial_for')) : null;
        $input['industry']              =  $request->input('industry') ? serialize($request->input('industry')) : null;
        $input['marketing']             =  $request->input('marketing') ? serialize($request->input('marketing')) : null;

        if ($companyContact->first_name != $request->first_name) {
            $changes[] = 'The first name was edited to ' . $request->first_name;
        }

        if ($companyContact->last_name != $request->last_name) {
            $changes[] = 'The last name was edited to ' . $request->last_name;
        }

        if ($companyContact->point_of_contact != $request->point_of_contact) {
            $changes[] = 'The point of contact was edited to ' . $request->point_of_contact;
        }

        $previousareas_of_expertise = is_array(unserialize($companyContact->areas_of_expertise)) ? unserialize($companyContact->areas_of_expertise) : [];
        $newareas_of_expertise      = $request->areas_of_expertise ?? [];
        $marketingAdded             = array_diff($newareas_of_expertise, $previousareas_of_expertise);
        $marketingDeleted           = array_diff($previousareas_of_expertise, $newareas_of_expertise);
        if (count($marketingAdded) > 0) {
            $changes[] = count($marketingAdded)  . ' new Service' .  (count($marketingAdded) > 1 ? 's' : '') . ' ' . (count($marketingAdded) > 1 ? 'were' : 'was') . ' added';
        }
        if (count($marketingDeleted) > 0) {
            $changes[] = count($marketingDeleted) . ' Service' . (count($marketingDeleted) > 1 ? 's' : '') . ' ' . (count($marketingDeleted) > 1 ? 'were' : 'was') . ' removed';
        }

        $previouscovered_regions = is_array(unserialize($companyContact->covered_regions)) ? unserialize($companyContact->covered_regions) : [];
        $newcovered_regions      = $request->covered_regions ?? [];
        $marketingAdded          = array_diff($newcovered_regions, $previouscovered_regions);
        $marketingDeleted        = array_diff($previouscovered_regions, $newcovered_regions);
        if (count($marketingAdded) > 0) {
            $changes[] = count($marketingAdded) . ' Country Covered ' . (count($marketingAdded) > 1 ? 'were' : 'was') . ' added';
        }
        if (count($marketingDeleted) > 0) {
            $changes[] = count($marketingDeleted) . ' Country Covered ' . (count($marketingDeleted) > 1 ? 'were' : 'was') . ' removed';
        }

        $previousrecently_contacted_by = is_array(unserialize($companyContact->recently_contacted_by)) ? unserialize($companyContact->recently_contacted_by) : [];
        $newrecently_contacted_by      = $request->recently_contacted_by ?? [];
        $marketingAdded                = array_diff($newrecently_contacted_by, $previousrecently_contacted_by);
        $marketingDeleted              = array_diff($previousrecently_contacted_by, $newrecently_contacted_by);
        if (count($marketingAdded) > 0) {
            $changes[] = count($marketingAdded) . ' new recently contacted by ' . (count($marketingAdded) > 1 ? 'were' : 'was') . ' added';
        }
        if (count($marketingDeleted) > 0) {
            $changes[] = count($marketingDeleted) . ' recently contacted by ' . (count($marketingDeleted) > 1 ? 'were' : 'was') . ' removed';
        }

        $previousongoing_work = is_array(unserialize($companyContact->ongoing_work)) ? unserialize($companyContact->ongoing_work) : [];
        $newongoing_work      = $request->ongoing_work ?? [];
        $marketingAdded       = array_diff($newongoing_work, $previousongoing_work);
        $marketingDeleted     = array_diff($previousongoing_work, $newongoing_work);
        if (count($marketingAdded) > 0) {
            $changes[] = count($marketingAdded) . ' new ongoing work ' . (count($marketingAdded) > 1 ? 'were' : 'was') . ' added';
        }
        if (count($marketingDeleted) > 0) {
            $changes[] = count($marketingDeleted) . ' ongoing work ' . (count($marketingDeleted) > 1 ? 'were' : 'was') . ' removed';
        }

        $previouspotencial_for = is_array(unserialize($companyContact->potencial_for)) ? unserialize($companyContact->potencial_for) : [];
        $newpotencial_for      = $request->potencial_for ?? [];
        $marketingAdded        = array_diff($newpotencial_for, $previouspotencial_for);
        $marketingDeleted      = array_diff($previouspotencial_for, $newpotencial_for);
        if (count($marketingAdded) > 0) {
            $changes[] = count($marketingAdded) . ' new potential' . (count($marketingAdded) > 1 ? 's' : '') . ' ' . (count($marketingAdded) > 1 ? 'were' : 'was') . ' added';
        }
        if (count($marketingDeleted) > 0) {
            $changes[] = count($marketingDeleted) . ' potential' . (count($marketingDeleted) > 1 ? 's' : '') . ' ' . (count($marketingDeleted) > 1 ? 'were' : 'was') . ' removed';
        }

        $previousindustry = is_array(unserialize($companyContact->industry)) ? unserialize($companyContact->industry) : [];
        $newindustry      = $request->industry ?? [];
        $industryAdded    = array_diff($newindustry, $previousindustry);
        $industryDeleted  = array_diff($previousindustry, $newindustry);
        if (count($industryAdded) > 0) {
            $count = count($industryAdded);
            $changes[] = ($count > 1 ? $count . ' new industries were added' : '1 new industry was added');
        }
        if (count($industryDeleted) > 0) {
            $count = count($industryDeleted);
            $changes[] = ($count > 1 ? $count . ' industries were removed' : '1 industry was removed');
        }

        foreach ($changes as $change) {
            // Create Protocol
            Protocol::create([
                'contact_id' => $request->contact_id,
                'category'   => 'profile',
                'message'    => $change,
                'icon'       => 'Contacts.svg',
            ]);
        }

        //Update contact
        $companyContact->update($input);
        $assignQuery = AssignedContact::query();
        // Remove all assigned contacts
        (clone $assignQuery)->where('contact_id', $companyContact->id)->delete();

        /*Assign contact to team member */
        if ($request->assign_teams) {
            $assignedContact = [];
            foreach ($request->assign_teams as $teamId) {
                $assignedContact[] =  [
                    'id'             => Str::uuid(),
                    'contact_id'     => $companyContact->id,
                    'assigned_to_id' => $teamId,
                    'assigned_by_id' => auth()->user()->id,
                ];
            }
            (clone $assignQuery)->insert($assignedContact);
            /*Sent notifcation when update record with assign contact */
            $this->sentAssignNotification($request->assign_teams, [$companyContact->id]);
        }
        // Create notification message
        if ($request->sub_type == "C") { // C = Company
            $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' updated the ' . $companyContact->company_name . "'" . 's details';
        } else {
            $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' updated the ' . $companyContact->first_name . ' ' . $companyContact->last_name . "'" . 's details';
        }
        // send notification fro contact updated
        sentNotification($message, $request->type);
        // Set response message sucess prefix.
        if($request->type == 'P')
        {
            $preFix = "Prospect";
        }
        elseif($request->type == 'CL')
        {
            $preFix = "Client";
        }
        else
        {
            $preFix = "Contact";
        }
        return ok($preFix.' updated successfully.', $companyContact);
    }

    /**
     * Update the Priority.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updatePriority(Request $request)
    {
        //Validation
        $this->validate($request, [
            'priority'              =>  'required|in:H,M,L',
            'contact_id'            =>  'required|exists:contacts,id'
        ]);
        $companyContact = CompanyContact::where('id', $request->contact_id)->first();
        if (!$companyContact) {
            return error(__('Contact not found.'), [], 'validation');
        }

        $input = $request->only('priority');
        // Update priority
        $companyContact->update($input);

        return ok('Priority updated successfully.', $companyContact);
    }

    /**
     * force Delete Contact api
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function softDelete(Request $request)
    {
        //Validation
        $this->validate($request, [
            'contact_id'   => 'required|array|exists:contacts,id',
            'main_type'    => 'nullable|in:CL,P,G',//CL=Client, P=Prospect, G= Contact
        ]);

        foreach ($request->contact_id  as $contact_id_detail) {
            $query = CompanyContact::query();
            $companyContact = (clone $query)->where('id', $contact_id_detail)->first();

            if (!$companyContact) {
                return error(__('Contact not found.'), [], 'validation');
            }
            // Delete contacts
            $companyContact->delete();
        }

        // Get message Prefix
        $preFix = prefix($request->main_type);
        return ok(__($preFix.' archived successfully'));
    }

    /**
     * force Delete Contact api
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function listArchive(Request $request)
    {
        //Validation
        $this->validate($request, [
            'page'           => 'required|integer|min:1',
            'perPage'        => 'required|integer|min:1',
            'type'           => 'required|in:G,P,CL', //CL=Client, P=Prospect, G= Contact
            'is_private'     => 'boolean',
            'is_lost'        => 'boolean',
            'sub_type'       => 'required|in:C,P', //C=Company, P=people
            'search'         => 'nullable',
            'is_import'      => 'nullable|boolean',
            'social_type'    => 'nullable|array|in:G,O,L',//G=google, L=linkdin, O= outlook
            'company_name'   => 'nullable',
            'assigned_to_id' => 'nullable|array|exists:users,id',
        ]);
        $assignQuery = AssignedContact::query();
        // Get assigned contacts ids
        $assignedContactIds = (clone $assignQuery)->where('assigned_to_id', auth()->user()->id)->pluck('contact_id')->toArray();
        // Get auth user role
        $user = Role::where('name', config('constants.super_admin'))->where('id', '=', auth()->user()->role_id)->first();

        $contacts = CompanyContact::onlyTrashed();
        // If is_private and is_lost key value 0 then get all contact like public, private and lost.
        if(empty($request->is_private) && empty($request->is_lost))
        {
            // If auth user role is super admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            }
            else
            {
                $contactIds     = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
                $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
                $contactIds = array_merge($contactIds, $authContactIds);
            }
        }
        elseif(!empty($request->is_private) && empty($request->is_lost))
        {
            // If auth user role is super admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', $request->is_private)->where('is_lost', 0)->pluck('id')->toArray();
            }
            else
            {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_private', $request->is_private)->where('is_lost', 0)->pluck('id')->toArray();
            }
        }
        elseif(empty($request->is_private) && !empty($request->is_lost))
        {
            // If auth user role is super admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_lost', $request->is_lost)->pluck('id')->toArray();
            }
            else
            {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_lost', $request->is_lost)->pluck('id')->toArray();
            }
        }
        else
        {
            // If auth user role is super admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            }
            else
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            }
        }

        if(isset($assignedContactIds))
        {
            $contactIds = array_merge($assignedContactIds, $contactIds);
        }

        if(!empty($request->is_private) && empty($request->is_lost))
        {
            $lostContactIds = (clone $contacts)->whereIn('id', $contactIds)->where('is_lost', 1)->pluck('id')->toArray();
            $contactIds = array_diff($contactIds, $lostContactIds);
        }
        elseif(empty($request->is_private) && !empty($request->is_lost))
        {
            $contactIds = (clone $contacts)->whereIn('id', $contactIds)->where('is_lost', 1)->pluck('id')->toArray();
        }
        // Assigned contacts filter
        if ($request->assigned_to_id) {
            $assignedtoContactsIds = (clone $assignQuery)->where('assigned_by_id', auth()->user()->id)->whereIn('assigned_to_id', $request->assigned_to_id)->pluck('contact_id')->toArray();

            $contactIds = array_intersect($contactIds, $assignedtoContactsIds);
        }

        //If type G (general) then get all contacts otherwise get prospact and client contact
        if($request->type == 'G')
        {
            $contacts->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id')
                    ->whereIn('id', array_unique($contactIds))
                    ->where('sub_type', $request->sub_type)
                    ->where('company_id', auth()->user()->company_id);
        }
        else
        {
            $contacts->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id')
                    ->whereIn('id', array_unique($contactIds))
                    ->where('type', $request->type)
                    ->where('sub_type', $request->sub_type)
                    ->where('company_id', auth()->user()->company_id);
        }

        /*Search in filter */
        if (isset($request->is_import))
        {
            $contacts->where('is_import', $request->is_import);
        }
        // Search associated company
        if (is_string($request->company_name) && ($request->company_name) && ($request->sub_type == 'P'))
        {
            $contacts->where('company_name', $request->company_name);
        }
        elseif(is_array($request->company_name) && ($request->company_name) && ($request->sub_type == 'P'))
        {
            $contacts->whereIn('company_name', $request->company_name);
        }
        // Search social type
        if ($request->social_type)
        {
            $contacts->whereIn('social_type', $request->social_type);
        }

        // Search contacts
        if ($request->search)
        {
            $search = $request->search;
            $contacts->where(function($query) use($search) {
                    $query->where('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhereHas('city_details', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('country_details', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                    });
        }

        /*For pagination */
        $result   = filterSortPagination($contacts);
        $contacts = $result['query']->get();
        // Contacts Count
        $count  = $result['count'];
        foreach ($contacts as $contact)
        {
            $contact['assigned_to_user_details'] = (clone $assignQuery)->with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $contact->id)->get();
        }

        // Get message Prefix
        $preFix = prefix($request->type);
        return ok($preFix.' List', [
            'contacts' => $contacts,
            'count'    => $count,
        ]);
    }

    /**
     * restore Archive contacts.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function restoreArchive(Request $request)
    {
        //Validation
        $this->validate($request, [
            'contact_id'   => 'required|array|exists:contacts,id',
            'main_type'    => 'nullable|in:CL,P,G',// Cl=client, P=prospect, G=contact
        ]);

        $query = CompanyContact::query();
        foreach ($request->contact_id  as $contact_id_detail) {
            $companyContact = (clone $query)->onlyTrashed()->where('id', $contact_id_detail)->first();

            if (!$companyContact) {
                return error(__('Contact not found.'), [], 'validation');
            }
            // Resore soft delete contacts
            $companyContact->restore();
        }

        // Get message Prefix
        $preFix = prefix($request->main_type);
        return ok(__($preFix.' restored successfully'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        //Validation
        $this->validate($request, [
            'contact_id'   => 'required|array|exists:contacts,id',
            'main_type'    => 'nullable|in:CL,P,G', // Cl=client, P=prospect, G=contact
        ]);

        $query = CompanyContact::query();
        foreach ($request->contact_id  as $contact_id_detail) {
            $companyContact = (clone $query)->onlyTrashed()->where('id', $contact_id_detail)->first();

            if (!$companyContact) {
                return error(__('Contact not found.'), [], 'validation');
            }

            $user = auth()->user();
            // Decrement Gmail sync count
            if (($companyContact->social_type && $companyContact->social_type == 'G')) {
                if ($companyContact->type == 'P' && ($user->prospect_google_sync_count > 0)) {
                    $user->decrement('prospect_google_sync_count');
                } elseif ($companyContact->type == 'CL' && ($user->client_google_sync_count > 0)) {
                    $user->decrement('client_google_sync_count');
                } else {
                    if ($user->google_sync_count > 0) {
                        $user->decrement('google_sync_count');
                    }
                }
            }
            // Decrement Outlook sync count
            if (($companyContact->social_type && $companyContact->social_type == 'O')) {
                if ($companyContact->type == 'P' && ($user->prospect_outlook_sync_count > 0)) {
                    $user->decrement('prospect_outlook_sync_count');
                } elseif ($companyContact->type == 'CL' && ($user->client_outlook_sync_count > 0)) {
                    $user->decrement('client_outlook_sync_count');
                } else {
                    if ($user->outlook_sync_count > 0) {
                        $user->decrement('outlook_sync_count');
                    }
                }
            }
            // Decrement Linkdin sync count
            if (($companyContact->social_type && $companyContact->social_type == 'L')) {
                if ($companyContact->type == 'P' && ($user->prospect_linkdin_sync_count > 0)) {
                    $user->decrement('prospect_linkdin_sync_count');
                } elseif ($companyContact->type == 'CL' && ($user->client_linkdin_sync_count > 0)) {
                    $user->decrement('client_linkdin_sync_count');
                } else {
                    if ($user->linkdin_sync_count > 0) {
                        $user->decrement('linkdin_sync_count');
                    }
                }
            }
            $companyContact->forceDelete();
        }

        // Get message Prefix
        $preFix = prefix($request->main_type);
        return ok(__($preFix.' permanently deleted successfully'));
    }

    /**
     * list contacts
     *
     * @param  mixed $request
     * @return void
     */
    public function list(Request $request)
    {
        //Validation
        $this->validate($request, [
            'page'           => 'required|integer|min:1',
            'perPage'        => 'required|integer|min:1',
            'type'           => 'required|in:G,P,CL',// Cl=client, P=prospect, G=contact
            'is_private'     => 'boolean',
            'is_lost'        => 'boolean',
            'sub_type'       => 'required|in:C,P',// C=company, P=people
            'search'         => 'nullable',
            'is_import'      => 'nullable|boolean',
            'social_type'    => 'nullable|array|in:G,O,L',// G=google, O=outlook, L=linkdin
            'company_name'   => 'nullable',
            'assigned_to_id' => 'nullable|array|exists:users,id',
        ]);
        $assignQuery = AssignedContact::query();
        //Get assign contacts id
        $assignedContactIds = (clone $assignQuery)->where('assigned_to_id', auth()->user()->id)->pluck('contact_id')->toArray();
        //Get auth user role
        $user = Role::where('name', config('constants.super_admin'))->where('id', '=', auth()->user()->role_id)->first();

        $contacts = CompanyContact::query();
        // If is_private and is_lost key value 0 then get all contact like public, private and lost.
        if(empty($request->is_private) && empty($request->is_lost))
        {
            // If auth user role is admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            }
            else
            {
                $contactIds     = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
                $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
                $contactIds = array_merge($contactIds, $authContactIds);
            }
        }
        elseif(!empty($request->is_private) && empty($request->is_lost))
        {
            // If auth user role is admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', $request->is_private)->where('is_lost', 0)->pluck('id')->toArray();
            }
            else
            {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_private', $request->is_private)->where('is_lost', 0)->pluck('id')->toArray();
            }
        }
        elseif(empty($request->is_private) && !empty($request->is_lost))
        {
            // If auth user role is admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_lost', $request->is_lost)->pluck('id')->toArray();
            }
            else
            {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_lost', $request->is_lost)->pluck('id')->toArray();
            }
        }
        else
        {
            // If auth user role is admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            }
            else
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            }
        }

        if(isset($assignedContactIds))
        {
            $contactIds = array_merge($assignedContactIds, $contactIds);
        }

        if(!empty($request->is_private) && empty($request->is_lost))
        {
            $lostContactIds = (clone $contacts)->whereIn('id', $contactIds)->where('is_lost', 1)->pluck('id')->toArray();
            $contactIds = array_diff($contactIds, $lostContactIds);
        }
        elseif(empty($request->is_private) && !empty($request->is_lost))
        {
            $contactIds = (clone $contacts)->whereIn('id', $contactIds)->where('is_lost', 1)->pluck('id')->toArray();
        }

        // Assigned contacts filter
        if ($request->assigned_to_id) {
            $assignedtoContactsIds = (clone $assignQuery)->where('assigned_by_id', auth()->user()->id)->whereIn('assigned_to_id', $request->assigned_to_id)->pluck('contact_id')->toArray();

            $contactIds = array_intersect($contactIds, $assignedtoContactsIds);
        }

        //If type G (general) then get all contacts otherwise get prospact and client contact
        if($request->type == 'G')
        {
            $contacts->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id')
                    ->whereIn('id', array_unique($contactIds))
                    ->where('sub_type', '=', $request->sub_type)
                    ->where('company_id', auth()->user()->company_id);
        }
        else
        {
            $contacts->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id')
                    ->whereIn('id', array_unique($contactIds))
                    ->where('type', '=', $request->type)
                    ->where('sub_type', '=', $request->sub_type)
                    ->where('company_id', auth()->user()->company_id);
        }

        // Filter upload contacts
        if (isset($request->is_import))
        {
            $contacts->where('is_import', $request->is_import);
        }

        // Filter associated company
        if (is_string($request->company_name) && ($request->company_name) && ($request->sub_type == 'P'))
        {
            $contacts->where('company_name', $request->company_name);
        }
        elseif(is_array($request->company_name) && ($request->company_name) && ($request->sub_type == 'P'))
        {
            $contacts->whereIn('company_name', $request->company_name);
        }

        // Filter social type
        if ($request->social_type)
        {
            $contacts->whereIn('social_type', $request->social_type);
        }

        // Filter search
        if ($request->search)
        {
            $search = $request->search;
            $contacts->where(function($query) use($search) {
                    $query->where('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhereHas('city_details', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('country_details', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                    });
        }

        /*For pagination */
        $result   = filterSortPagination($contacts);
        $contacts = $result['query']->get();
        // Contacts Count
        $count  = $result['count'];
        foreach ($contacts as $contact) {
            $contact['assigned_to_user_details'] = (clone $assignQuery)->with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $contact->id)->orderBy('created_at', 'desc')->get();
        }

        // Get message Prefix
        $preFix = prefix($request->type);
        return ok($preFix.' List', [
            'contacts' => $contacts,
            'count'    => $count,
        ]);
    }

    /**
     * assign Contact api
     *
     * @param  mixed $request
     * @return void
     */
    public function assign(Request $request)
    {
        //Validation
        $this->validate($request, [
            'assigned_by_id'  =>  'required|exists:users,id',
            'contact_id'      =>  'required|array|exists:contacts,id',
            'assigned_to_id'  =>  'required|array|exists:users,id',
            'main_type'       =>  'nullable|in:G,P,CL',// Cl=client, P=prospect, G=contact
            'is_big_card'     =>  'nullable',
        ]);

        $query = AssignedContact::query();
        $existsContactIds = (clone $query)->whereIn('contact_id', $request->contact_id)->whereIn('assigned_to_id', $request->assigned_to_id)->pluck('contact_id')->toArray();

        $companyContact = CompanyContact::query();
        $lostContactCount    =(clone $companyContact)->whereIn('id',$request->contact_id)->where('is_lost',1)->count();
        if($lostContactCount > 0){
            return error(__('you can not assing lost contacts'), [], 'validation');
        }

        /* Check contact already assigned or not */
        if (!empty($existsContactIds) && (!isset($request->is_big_card) || isset($request->is_big_card)) && empty($request->is_big_card)) {
            $data = [];
            $contactsName = (clone $companyContact)->select('id', 'first_name', 'last_name', 'sub_type', 'company_name')->whereIn('id', $existsContactIds)->get();
            foreach ($contactsName as $value) {
                if ($value->sub_type == "C") {
                    array_push($data, $value->company_name);
                } else {
                    array_push($data, $value->full_name);
                }
            }
            $contacts = implode(",", $data);
            return error(__($contacts . ' contacts already assigned to team member'), [], 'validation');
        }

        // Delete Assign Contact Ids if is_big_card flag set
        if(isset($request->is_big_card) && !empty($request->is_big_card))
        {
            (clone $query)->whereIn('contact_id', $request->contact_id)->delete();
        }

        foreach ($request->contact_id  as $contact_id) {
            foreach ($request->assigned_to_id as $assigned_to_user_id) {
                $assignedContact = [];
                $assignedContact['contact_id']     = $contact_id;
                $assignedContact['assigned_by_id'] = $request->assigned_by_id ?? null;
                $assignedContact['assigned_to_id'] =  $assigned_to_user_id;
                $assignedContactCreate = (clone $query)->create($assignedContact);
            }
        }
        //Get message Prefix
        $preFix = prefix($request->main_type);
        //send contact assign notification
        $this->sentAssignNotification($request->assigned_to_id, $request->contact_id);
        return ok($preFix.' assigned successfully.', $assignedContactCreate);
    }

    /**
     * export Contact api
     *
     * @param  mixed $request
     * @return void
     */
    public function export(Request $request)
    {
        //Validation
        $this->validate($request, [
            'contact_id'  => 'required|array|exists:contacts,id',
            'type'        =>  'required|in:G,P,CL',// Cl=client, P=prospect, G=contact
            'sub_type'    =>  'required|in:C,P',// C=company, P=people
        ]);
        //get contact
        $contact = CompanyContact::with('city_details')->whereIn('id',$request->contact_id)->where('sub_type', '=', $request->sub_type)
        ->where('company_id', auth()->user()->company_id)->get();

        if ($request->sub_type == 'P') {
            return Excel::download(new ContactExport($contact), 'ContactExport.csv');
        } else {
            return Excel::download(new CompanyContactExport($contact), 'ContactExport.csv');
        }
    }

    /**
     * demo excel file for company contact download
     *
     * @param  mixed $request
     * @return void
     */
    public function getcompanyimport()
    {
        $filepath = public_path('file_example_companycontact_XLS.csv');
        return response()->download($filepath);
    }

    /**
     * demo excel file download
     *
     * @param  mixed $request
     * @return void
     */
    public function downloadfile()
    {
        $filepath = public_path('file_example_XLS.csv');
        return response()->download($filepath);
    }

    /**
     * move Contact api.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function move(Request $request)
    {
        //Validation
        $this->validate($request, [
            'contact_id'  => 'required|exists:contacts,id',
            'is_private'  => 'nullable|boolean',
            'is_lost'     => 'nullable|boolean'
        ]);

        //get contact
        $companyContact = CompanyContact::where('id', $request->contact_id)->first();

        // If contact not found then throw error
        if (!$companyContact) {
            return error(__('Contact not found.'), [], 'validation');
        }

        if (isset($request->is_private) && $companyContact->is_private == $request->is_private) {

            return error(__('Contact can not move in same category.'), [], 'validation');
        }
        if (isset($request->is_lost) && $companyContact->is_lost == $request->is_lost) {

            return error(__('Contact can not move in same category.'), [], 'validation');
        }
        if (empty($companyContact->is_private) && !empty($request->is_private)) {
            return error(__('Contact can not move from All clients to my clients.'), [], 'validation');
        }

        if (!empty($companyContact->is_lost)) {
            return error(__('Contact can not move from Lost clients.'), [], 'validation');
        }

        $input = $request->only('is_private','is_lost');
        //Update contact
        $companyContact->update($input);

        return ok('Contact moved successfully.');
    }

    /**
     * list contacts
     *
     * @param  mixed $request
     * @return void
     */
    public function view(Request $request)
    {
        $contact = CompanyContact::where('id', $request->get('contact_id'))->with('city_details', 'country_details', 'company:id,name')->first();

        $contact->assigned_to_user_details =  AssignedContact::with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $contact->id)->get();

        return ok('Contact details.', $contact);
    }

    /**
     * share contact
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function share(Request $request)
    {
        //Validation
        $this->validate($request, [
            'assigned_by_id'  =>  'required|exists:users,id',
            'contact_id'      => 'required|exists:contacts,id',
            'assigned_to_id'  =>  'required|array|exists:users,id',
            'emailMessage'    => 'required',
        ]);
        //Get contact
        $companyContact = CompanyContact::where('id', $request->contact_id)->first();
        // If contact not found then throw error
        if (!$companyContact) {
            return error(__('Contact not found.'), [], 'validation');
        }

        if ($request->assigned_to_id) {
            //$email_array = array();

            foreach ($request->assigned_to_id as $user_id) {
                //check contact already share validation
                if ($request->assigned_by_id == $user_id) {
                    return error(__('You can not share contacts to same user.'), [], 'validation');
                }

                $assigned_to = User::where('id', $user_id)->first();
                $assigned_by = User::where('id', $request->assigned_by_id)->first();

                $email = $assigned_to->email;
                $data = array(
                    'assigned_to'    => $assigned_to,
                    'assigned_by'    => $assigned_by,
                    'CompanyContact' => $companyContact,
                    'emailMessage'   => $request->emailMessage,
                );

                $subject = 'Shared Contact';
                // Send mail
                Mail::send('emails.ShareContact', ['data' => $data], function ($message) use ($subject, $email) {
                    $message->to($email);
                    $message->subject($subject);
                });
            }
        }
        $share = 'shared';
        /*Sent notification to the admin */
        $this->sentAssignNotification($request->assigned_to_id, [$companyContact->id], $share);
        return ok('The contact has been sent to selected emails successfully.');
    }

    /**
     * list email
     *
     * @param  mixed $request
     * @return void
     */
    public function listemail(Request $request)// TODO: "Currntly not using this api"
    {
        //Validation
        $this->validate($request, [
            'user_id'               =>  'required|array|exists:users,id',
        ]);

        if ($request->user_id) {
            $email_array = array();

            foreach ($request->user_id as $user_detail) {
                $user = User::where('id', '=', $user_detail)->first();
                $email_array[] =  $user->email;
            }
        }

        return ok('Email List', $email_array);
    }

    /**
     * list contacts
     *
     * @param  mixed $request
     * @return void
     */
    public function allmylist(Request $request)
    {
        //Validation
        $this->validate($request, [
            'type'     =>  'required|in:G,P,CL', // G=contact, P=prospect, CL=client
            'sub_type' =>  'required|in:C,P', // C= company, P=people
        ]);

        // Get Login user assigned contact id
        $assignedContactIds = AssignedContact::where('assigned_to_id', auth()->user()->id)->pluck('contact_id')->toArray();
        // Get Login user role
        $user = Role::where('name', config('constants.super_admin'))->where('id', '=', auth()->user()->role_id)->first();

        $contacts = CompanyContact::query();
        //If auth user role is super admin then he/his can see public and private contacts.
        if ($user) {
            $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
        } else {
            $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
            $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
            $contactIds = array_merge($contactIds, $authContactIds);
        }

        $contactIds = array_merge($assignedContactIds, $contactIds);
        if($request->type == 'G') {
            $contacts->where('company_id', auth()->user()->company_id)
            ->whereIn('id', $contactIds)
            ->where('is_lost', 0)
            ->where('sub_type', $request->sub_type)
            ->select('id', 'first_name', 'last_name', 'company_name', 'company_id', 'is_private', 'is_lost', 'type', 'sub_type');
        } else {
            $contacts->where('company_id', auth()->user()->company_id)
            ->whereIn('id', $contactIds)
            ->where('type', $request->type)
            ->where('is_lost', 0)
            ->where('sub_type', $request->sub_type)
            ->select('id', 'first_name', 'last_name', 'company_name', 'company_id', 'is_private', 'is_lost', 'type', 'sub_type');
        }
        //Get contacts
        $contacts = $contacts->get();

        // Get message Prefix
        $preFix = prefix($request->type);
        return ok($preFix . ' List', $contacts);
    }

    /* Get Company contact */
    public function getpeople(Request $request) // TODO : "currently not use this api"
    {
        //Validation
        $this->validate($request, [
            'company_name' => 'required',
            'user_id'      =>  'required|exists:users,id',
            'type'         =>  'required|in:G,P,CL',
            'section'      =>  'required|in:A,M,L',
        ]);
        // Get assigned contact ids
        $assignedContacts = AssignedContact::where('assigned_to_id', $request->user_id)->pluck('contact_id')->toArray();
        // Get auth user contacts ids
        $currentContacts = CompanyContact::where('company_id', auth()->user()->company_id)->where('created_by', $request->user_id)->pluck('id')->toArray();

        $selected = array_merge($assignedContacts, $currentContacts);
        //Get contacts
        $contacts = CompanyContact::where('company_id', auth()->user()->company_id)->select('id', 'first_name', 'last_name', 'company_name')->where('company_name', '=', $request->company_name)->whereIn('id', $selected)->where('type', '=', $request->type)->where('section', '=', $request->section)->where('sub_type', 'P')->get();
        // Get message Prefix
        $preFix = prefix($request->type);
        return ok($preFix . ' People List', $contacts);
    }

    /* Get Company contact */ //TODO: "currently not use this api"
    public function getCompanyContact(Request $request)
    {
        //Validation
        $this->validate($request, [
            'type'      =>  'required|in:G,P,CL',
            'section'   =>  'required|in:A,M,L',
        ]);
        // Get assigned contact ids
        $assignedContactIds = AssignedContact::where('assigned_to_id', auth()->user()->id)->pluck('contact_id')->toArray();
        // Get user role
        $user = Role::where('name', config('constants.super_admin'))->where('id', '=', auth()->user()->role_id)->first();

        $contacts = CompanyContact::query();

        //If section A (All) then get all contacts otherwise get my and lost contact
        if($request->section == 'A')
        {
            // If auth user role is admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            }
            else
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
                $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
                $contactIds = array_merge($contactIds, $authContactIds);
            }
        }
        elseif($request->section == 'M')
        {
            // If auth user role is admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 1)->where('is_lost', 0)->pluck('id')->toArray();
            }
            else
            {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_private', 1)->where('is_lost', 0)->pluck('id')->toArray();
            }
        }
        elseif($request->section == 'L')
        {
            // If auth user role is admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_lost', 1)->pluck('id')->toArray();
            }
            else
            {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_lost', 1)->pluck('id')->toArray();
            }
        }
        else
        {
            // If auth user role is admin then see all contacts otherwise see only own and assigend themself contacts
            if($user)
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            }
            else
            {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
                $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
                $contactIds = array_merge($contactIds, $authContactIds);
            }
        }

        if(isset($assignedContactIds))
        {
            $contactIds = array_merge($assignedContactIds, $contactIds);
        }

        //If type G (general) then get all contacts otherwise get prospact and client contact
        if($request->type == 'G')
        {
            $contacts->select('id', 'first_name', 'last_name', 'company_name')
                    ->whereIn('id', array_unique($contactIds))
                    ->where('sub_type', '=', 'C')
                    ->where('company_id', auth()->user()->company_id);
        }
        else
        {
            $contacts->select('id', 'first_name', 'last_name', 'company_name')
                    ->whereIn('id', array_unique($contactIds))
                    ->where('type', '=', $request->type)
                    ->where('sub_type', '=', 'C')
                    ->where('company_id', auth()->user()->company_id);
        }

        $contacts =  $contacts->get();

        return ok('People', $contacts);
    }

    /**
     * Get Company Team
     *
     * @param  mixed $request
     * @return void
     */
    public function companyTeam(Request $request)
    {
        //Validation
        $this->validate($request, [
            'company_id' => 'nullable|exists:contacts,id',
            'people_id'  => 'nullable|exists:contacts,id',
            'search'     => 'nullable',
            'sort_by'    => 'nullable|in:asc,desc',
            'page'       => 'nullable',
            'perPage'    => 'nullable'
        ]);
        $queryData = CompanyPeople::query();
        $peopleId = [];
        if ($request->company_id) {
            $peopleId = (clone $queryData)->where('company_id', $request->company_id)->pluck('people_id')->toArray();
        }
        if ($request->people_id) {
            $getCompany    = (clone $queryData)->where('people_id', $request->people_id)->first();
            if ($getCompany) {
                $peopleId  = (clone $queryData)->where('company_id', $getCompany->company_id)->pluck('people_id')->toArray();
            }
        }

        $teams = CompanyContact::whereIn('id', array_unique($peopleId))->where('sub_type', 'P');

        /* Search functionality */
        if ($request->search) {
            $search = $request->search;
            $teams->where(function ($query) use ($search) {
                $query->where(DB::raw("CONCAT(`first_name`, ' ', `last_name`)"), 'LIKE', "%{$search}%")->orWhere(DB::raw("CONCAT(`last_name`, ' ', `first_name`)"), 'LIKE', "%{$search}%");
            });
        }

        $count = (clone $teams)->count();
        /* Sort by asc and desc order based on created date */
        if ($request->sort_by) {
            $teams->orderBy('created_at', $request->sort_by);
        }
        else
        {
            $teams->orderBy('created_at', 'DESC');
        }
        /*Pagination */

        if ($request->page && $request->perPage) {
            $page       = $request->page;
            $perPage    = $request->perPage;
            $teams->skip($perPage * ($page - 1))->take($perPage);
        }
        $teams = $teams->get();
        return ok(__('Teams List'), [
            'team' => $teams,
            'count' => $count
        ]);
    }

    /**
     * Sent notification create common function when create,edit,assing and share
     *
     *
     */
    public function sentAssignNotification($assingTo, $contacts, $share = null)
    {

        $role            = Role::where('name', config('constants.super_admin'))->first();

        $users           = User::whereIn('id', $assingTo)->whereNotIn('role_id', [$role->id])->where('company_id', auth()->user()->company_id)->selectRaw("id, concat(first_name, ' ', last_name) as username")->pluck('username', 'id')->toArray();

        $contactData = [];

        $contactsName = CompanyContact::select('id', 'first_name', 'last_name', 'sub_type', 'type', 'company_name')->whereIn('id', $contacts)->get();

        foreach ($contactsName as $value) {
            if ($value->sub_type == "C") {
                //$type = $value->type;
                array_push($contactData, $value->company_name);
            } else {
                array_push($contactData, $value->full_name);
            }
        }
        $userFullName    = implode(",", $users);
        $contactFullName = implode(",", $contactData);
        $shared = $share ?? 'assigned';
        if (!$users) {
            $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has ' . $shared . ' to you';
        } else if (count($assingTo) == count($users)) {
            $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has ' . $shared . ' the ' . $contactFullName . ' to ' . $userFullName;
        } else {
            $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has ' . $shared . ' the ' . $contactFullName . ' to ' . $userFullName . ' and you';
        }
        $contactType = $contactsName->first();
        //sent notification
        sentNotification($message, $contactType->type);
        /*Sent notification to the team member */
        if (count($contactData) > 1) {
            $teamMessage = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has ' . $shared . ' contacts to you';
        } else {
            $teamMessage = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has ' . $shared . ' contact to you';
        }

        /*sent notification to the team member */
        if ($contactType->type == 'G') {
            $icon = 'Contacts.svg';
        } elseif ($contactType->type == 'P') {
            $icon = 'Prospects.svg';
        } elseif ($contactType->type == 'CL') {
            $icon = 'Clients.svg';
        }
        if (count($users) > 0) {
            foreach ($users as $key => $value) {
                $notification[] = [
                    'id'          => Str::uuid(),
                    'sender_id'   => auth()->user()->id,
                    'receiver_id' => $key,
                    'title'       => $teamMessage,
                    'icon'        => $icon,
                    'created_at'  => now()
                ];
            }
            SystemNotification::insert($notification);
        }
    }
}
