<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Functions;
use App\Models\CompanyContact;
use App\Models\AssignedContact;
use Illuminate\Support\Facades\Storage;
use Excel;
use App\Exports\ContactExport;
use Illuminate\Support\Facades\DB;
use App\Imports\PeopleContactImport;
use App\Imports\CompanyContactImport;
use Str;
use Response;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Exports\CompanyContactExport;
use App\Models\CompanyPeople;
use App\Models\SystemNotification;
use App\Models\Role;
use App\Models\Protocol;

class CompanyContactController extends Controller
{
    use Functions;

    /**
     * create Contact api
     *
     * @param  mixed $request
     * @return void
     */

    public function create(Request $request)
    {

        $this->validate($request, [

            'company_id'            =>  'required|exists:companies,id',
            'profile_picture'       =>  'nullable|mimes:jpg,jpeg,png||max:5120',
            'type'                  =>  'required|in:G,P,CL',
            //'section'               =>  'required|in:A,M,L',
            'sub_type'              =>  'required|in:C,P',
            'company_name'          =>  'nullable|required_if:sub_type,==,C',
            'priority'              =>  'nullable|in:H,M,L',
            'category'              =>  'nullable|required_if:sub_type,==,C|in:H,R',
            'role'                  =>  'nullable|required_if:sub_type,==,P',
            'point_of_contact'      =>  'nullable|required_if:sub_type,==,P',
            'email'                 =>  'required|email',
            //'phone_number'          =>  'nullable|regex:/^(?:\+)?\d{12}$/',
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
            'is_private'            => 'nullable|boolean',
            'is_lost'               => 'nullable|boolean'

        ], [
            'profile_picture'     => 'The profile picture must not be greater than 5 MB.'
        ]);

        $pest = CompanyContact::where('email', '=', $request->email)->where('company_id', auth()->user()->company_id)->first();

        if ($pest) {
            return error(__('Email already exists!'), [], 'validation');
        }

        $query = CompanyContact::query();
        if ($request->sub_type == 'C') {
            $email  = $request->email;
            $domain = substr($email, strpos($email, '@') + 1);

            // Skip the duplicate check for gmail.com and yahoo.com domains
            if ($domain != 'hotmail.com' && $domain != 'yahoo.com' && $domain != 'gmail.com' && $domain != 'icloud.com' && $domain != 'outlook.com') {
                $compnyId = (clone $query)->where('email', 'like', "%@$domain")->where('sub_type', 'C')->where('company_id', auth()->user()->company_id)->exists();
                if ($compnyId) {
                    return error(__('Company already exists'), [], 'validation');
                }
            }
        }
        $url = null;

        if (isset($request->profile_picture)) {
            $file = $request['profile_picture'];

            //store your file into database
            $url = "Contact" . "/" . $file->getClientOriginalName() . "_" . mt_rand(1000000000, time());
            Storage::disk('s3')->put($url, file_get_contents($file));
            $url = Storage::disk('s3')->url($url);
        }
        $companyName = (clone $query)->where('id', $request->existing_company_id)->first();
        if ($companyName) {
            $request['company_name'] = $companyName->company_name;
        }
        $input = $request->only('company_id', 'type', 'is_private', 'is_lost', 'sub_type', 'company_name', 'priority', 'category', 'point_of_contact', 'role', 'email', 'phone_number', 'client_since', 'first_name', 'last_name', 'country_code', 'city_id') + ['profile_picture' => $url];

        $input['recently_contacted_by'] = $request->input('recently_contacted_by') ? serialize($request->input('recently_contacted_by')) : null;
        $input['covered_regions'] =  $request->input('covered_regions') ? serialize($request->input('covered_regions')) : null;
        $input['areas_of_expertise'] =  $request->input('areas_of_expertise') ? serialize($request->input('areas_of_expertise')) : null;
        $input['ongoing_work'] =  $request->input('ongoing_work') ? serialize($request->input('ongoing_work')) : null;
        $input['potencial_for'] =  $request->input('potencial_for') ? serialize($request->input('potencial_for')) : null;
        $input['industry'] =  $request->input('industry') ? serialize($request->input('industry')) : null;
        $input['marketing'] =  $request->input('marketing') ? serialize($request->input('marketing')) : null;
        $CompanyContact =  (clone ($query))->create($input);

        $slug = Str::slug($CompanyContact->id);

        if ($request->type == 'G') {
            $fianlslug =   url(config('constants.CONTACT_SLAG_SERVER_URL')) . $slug;
        } elseif ($request->type == 'P') {
            $fianlslug =  url(config('constants.PROSPECT_SLAG_SERVER_URL')) . $slug;
        } else {
            $fianlslug =  url(config('constants.CLIENT_SLAG_SERVER_URL')) . $slug;
        }


        CompanyContact::whereId($CompanyContact->id)->update([
            'slag' => $fianlslug
        ]);

        $queryData = CompanyPeople::query();
        if ($request->people_id) {
            $companyPeople = [];
            foreach ($request->people_id as $key => $value) {
                $companyPeople[] = [
                    'people_id' => $value,
                    'company_id' => $CompanyContact->id
                ];
            }
            $queryData->insert($companyPeople);
        }

        /* Store people record if domain match */
        if (!$request->existing_company_id) {
            $email  = $request->email;
            $domain = substr($email, strpos($email, '@') + 1);
            if ($request->sub_type == 'P') {
                if ($domain != 'hotmail.com' && $domain != 'yahoo.com' && $domain != 'gmail.com' && $domain != 'icloud.com' && $domain != 'outlook.com') {
                    $compnyId = (clone $query)->where('email', 'like', "%@$domain")->where('sub_type', 'C')->where('company_id', auth()->user()->company_id)->first();
                    if ($compnyId) {
                        $queryData->create(['company_id' => $compnyId->id, 'people_id' => $CompanyContact->id]);
                        $query->where('id', $CompanyContact->id)->update(['company_name' => $compnyId->company_name]);
                    }
                }
            }
            if ($request->sub_type == 'C') {
                if ($domain != 'hotmail.com' && $domain != 'yahoo.com' && $domain != 'gmail.com' && $domain != 'icloud.com' && $domain != 'outlook.com') {
                    $compnyId = (clone $query)->where('email', 'like', "%@$domain")->where('sub_type', 'P')->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
                    if ($compnyId) {
                        foreach ($compnyId as $key => $value) {
                            $data[] = [
                                'people_id' => $value,
                                'company_id' => $CompanyContact->id
                            ];
                        }
                        $queryData->insert($data);
                        $query->whereIn('id', $compnyId)->update(['company_name' => $CompanyContact->company_name]);
                    }
                }
            }
        }
        /* Store record when add to team from team section*/
        if ($request->existing_company_id) {
            $queryData->create(['company_id' => $request->existing_company_id, 'people_id' => $CompanyContact->id]);
        }

        /* Assign contact to the team member */
        if ($request->assign_teams) {
            $assigned_contact = [];
            foreach ($request->assign_teams as $key => $team) {
                $assigned_contact[] =  [
                    'id'             => Str::uuid(),
                    'contact_id'     => $CompanyContact->id,
                    'assigned_to_id' =>  $team,
                    'assigned_by_id' => auth()->user()->id,
                ];
            }
            AssignedContact::insert($assigned_contact);
            /* Sent notifcation when assing contact */
            $this->sentAssignNotification($request->assign_teams, [$CompanyContact->id]);
        }
        $type     = $request->type;
        $sub_type = $request->sub_type;
        sentMultipleNotification($type, $sub_type);
        // Set response message sucess prefix.
        if ($request->type == 'P') {
            $preFix = "Prospect";
        } elseif ($request->type == 'CL') {
            $preFix = "Client";
        } else {
            $preFix = "Contact";
        }
        return $this->sendResponse(true, $preFix . ' created successfully.', $CompanyContact);
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
        $this->validate($request, [
            'contact_id'            =>  'required|exists:contacts,id',
            'company_id'            =>  'required|exists:companies,id',
            'profile_picture'       =>  'nullable|mimes:jpg,jpeg,png||max:5120',
            'type'                  =>  'required|in:G,P,CL',
            //'section'               =>  'required|in:A,M,L',
            'sub_type'              =>  'required|in:C,P',
            'company_name'          =>  'nullable|required_if:sub_type,==,C',
            'priority'              =>  'nullable|in:H,M,L',
            'category'              =>  'nullable|required_if:sub_type,==,C|in:H,R',
            'role'                  =>  'nullable|required_if:sub_type,==,P',
            'point_of_contact'      =>  'nullable|required_if:sub_type,==,P',
            'email'                 =>  'required|email',
            //'phone_number'          =>  'nullable|regex:/^(?:\+)?\d{12}$/',
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
            'assign_teams'          => 'nullable|array',
            'is_private'            =>  'nullable|boolean',
            'is_lost'               =>  'nullable|boolean',
            'assign_teams'          => 'nullable|exists:users,id',
        ], [
            'profile_picture.max'   => 'The profile picture must not be greater than 5 MB.'
        ]);

        $CompanyContact = CompanyContact::where('id', $request->contact_id)->first();

        if (!$CompanyContact) {
            return error(__('Contact not found.'), [], 'validation');
        }

        $pest = CompanyContact::where('id', '!=', $request->contact_id)->where('email', '=', $request->email)->where('company_id', auth()->user()->company_id)->first();

        if ($pest) {
            return error(__('Email already exists!'), [], 'validation');
        }

        $input = $request->only('company_id', 'type', 'is_private', 'is_lost', 'company_name', 'priority', 'category', 'point_of_contact', 'role', 'email', 'phone_number', 'client_since', 'first_name', 'last_name', 'country_code', 'city_id', 'slag');

        $changes = [];

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
        $input['covered_regions'] =  $request->input('covered_regions') ? serialize($request->input('covered_regions')) : null;
        $input['areas_of_expertise'] =  $request->input('areas_of_expertise') ? serialize($request->input('areas_of_expertise')) : null;
        $input['ongoing_work'] =  $request->input('ongoing_work') ? serialize($request->input('ongoing_work')) : null;
        $input['potencial_for'] =  $request->input('potencial_for') ? serialize($request->input('potencial_for')) : null;
        $input['industry'] =  $request->input('industry') ? serialize($request->input('industry')) : null;
        $input['marketing'] =  $request->input('marketing') ? serialize($request->input('marketing')) : null;

        if ($CompanyContact->first_name != $request->first_name) {
            $changes[] = 'The first name was edited to ' . $request->first_name;
        }

        if ($CompanyContact->last_name != $request->last_name) {
            $changes[] = 'The last name was edited to ' . $request->last_name;
        }

        if ($CompanyContact->point_of_contact != $request->point_of_contact) {
            $changes[] = 'The point of contact was edited to ' . $request->point_of_contact;
        }

        $previousareas_of_expertise =  is_array(unserialize($CompanyContact->areas_of_expertise)) ? unserialize($CompanyContact->areas_of_expertise) : [];
        $newareas_of_expertise = $request->areas_of_expertise ?? [];
        $marketingAdded = array_diff($newareas_of_expertise, $previousareas_of_expertise);
        $marketingDeleted = array_diff($previousareas_of_expertise, $newareas_of_expertise);
        if (count($marketingAdded) > 0) {
            $changes[] = count($marketingAdded) . ' new Service' . (count($marketingAdded) > 1 ? 's' : '') . ' ' . (count($marketingAdded) > 1 ? 'were' : 'was') . ' added';
        }
        if (count($marketingDeleted) > 0) {
            $changes[] = count($marketingDeleted) . ' Service' . (count($marketingDeleted) > 1 ? 's' : '') . ' ' . (count($marketingDeleted) > 1 ? 'were' : 'was') . ' removed';
        }

        $previouscovered_regions = is_array(unserialize($CompanyContact->covered_regions)) ? unserialize($CompanyContact->covered_regions) : [];
        $newcovered_regions = $request->covered_regions ?? [];
        $marketingAdded = array_diff($newcovered_regions, $previouscovered_regions);
        $marketingDeleted = array_diff($previouscovered_regions, $newcovered_regions);
        if (count($marketingAdded) > 0) {
            $changes[] = count($marketingAdded) . ' Country Covered ' . (count($marketingAdded) > 1 ? 'were' : 'was') . ' added';
        }
        if (count($marketingDeleted) > 0) {
            $changes[] = count($marketingDeleted) . ' Country Covered ' . (count($marketingDeleted) > 1 ? 'were' : 'was') . ' removed';
        }

        $previousrecently_contacted_by =  is_array(unserialize($CompanyContact->recently_contacted_by)) ? unserialize($CompanyContact->recently_contacted_by) : [];
        $newrecently_contacted_by = $request->recently_contacted_by ?? [];
        $marketingAdded = array_diff($newrecently_contacted_by, $previousrecently_contacted_by);
        $marketingDeleted = array_diff($previousrecently_contacted_by, $newrecently_contacted_by);
        if (count($marketingAdded) > 0) {
            $changes[] = count($marketingAdded) . ' new recently contacted by ' . (count($marketingAdded) > 1 ? 'were' : 'was') . ' added';
        }
        if (count($marketingDeleted) > 0) {
            $changes[] = count($marketingDeleted) . ' recently contacted by ' . (count($marketingDeleted) > 1 ? 'were' : 'was') . ' removed';
        }

        $previousongoing_work =  is_array(unserialize($CompanyContact->ongoing_work)) ? unserialize($CompanyContact->ongoing_work) : [];
        $newongoing_work = $request->ongoing_work ?? [];
        $marketingAdded = array_diff($newongoing_work, $previousongoing_work);
        $marketingDeleted = array_diff($previousongoing_work, $newongoing_work);
        if (count($marketingAdded) > 0) {
            $changes[] = count($marketingAdded) . ' new ongoing work ' . (count($marketingAdded) > 1 ? 'were' : 'was') . ' added';
        }
        if (count($marketingDeleted) > 0) {
            $changes[] = count($marketingDeleted) . ' ongoing work ' . (count($marketingDeleted) > 1 ? 'were' : 'was') . ' removed';
        }

        $previouspotencial_for =  is_array(unserialize($CompanyContact->potencial_for)) ? unserialize($CompanyContact->potencial_for) : [];
        $newpotencial_for = $request->potencial_for ?? [];
        $marketingAdded = array_diff($newpotencial_for, $previouspotencial_for);
        $marketingDeleted = array_diff($previouspotencial_for, $newpotencial_for);
        if (count($marketingAdded) > 0) {
            $changes[] = count($marketingAdded) . ' new potential' . (count($marketingAdded) > 1 ? 's' : '') . ' ' . (count($marketingAdded) > 1 ? 'were' : 'was') . ' added';
        }
        if (count($marketingDeleted) > 0) {
            $changes[] = count($marketingDeleted) . ' potential' . (count($marketingDeleted) > 1 ? 's' : '') . ' ' . (count($marketingDeleted) > 1 ? 'were' : 'was') . ' removed';
        }

        $previousindustry =   is_array(unserialize($CompanyContact->industry)) ? unserialize($CompanyContact->industry) : [];
        $newindustry = $request->industry ?? [];
        $industryAdded = array_diff($newindustry, $previousindustry);
        $industryDeleted = array_diff($previousindustry, $newindustry);
        if (count($industryAdded) > 0) {
            $count = count($industryAdded);
            $changes[] = ($count > 1 ? $count . ' new industries were added' : '1 new industry was added');
        }
        if (count($industryDeleted) > 0) {
            $count = count($industryDeleted);
            $changes[] = ($count > 1 ? $count . ' industries were removed' : '1 industry was removed');
        }

        foreach ($changes as $change) {
            Protocol::create([
                'contact_id' => $request->contact_id,
                'category' => 'profile',
                'message' => $change,
                'icon' => 'Contacts.svg',
            ]);
        }

        $CompanyContact->update($input);

        if ($request->people_id) {
            $companyPeople = [];
            $query = CompanyPeople::query();
            foreach ($request->people_id as $key => $value) {
                $companyPeople[] = [
                    'people_id' => $value,
                    'company_id' => $CompanyContact->id
                ];
            }
            $query->where('company_id', $CompanyContact->id)->delete();
            $query->insert($companyPeople);
        }

        $AssignedContact = AssignedContact::where('contact_id', $CompanyContact->id)->where('assigned_by_id', auth()->user()->id)->delete();

        /*Assign contact to team member */
        if ($request->assign_teams) {
            $assigned_contact = [];
            foreach ($request->assign_teams as $key => $team) {
                $assigned_contact[] =  [
                    'id'             => Str::uuid(),
                    'contact_id'     => $CompanyContact->id,
                    'assigned_to_id' =>  $team,
                    'assigned_by_id' => auth()->user()->id,
                ];
            }
            AssignedContact::insert($assigned_contact);
            /*Sent notifcation when update record with assign contact */
            $this->sentAssignNotification($request->assign_teams, [$CompanyContact->id]);
        }
        if ($request->sub_type == "C") {
            $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' updated the ' . $CompanyContact->company_name . "'" . 's details';
        } else {
            $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' updated the ' . $CompanyContact->first_name . ' ' . $CompanyContact->last_name . "'" . 's details';
        }
        sentNotification($message, $request->type);
        // Set response message sucess prefix.
        if ($request->type == 'P') {
            $preFix = "Prospect";
        } elseif ($request->type == 'CL') {
            $preFix = "Client";
        } else {
            $preFix = "Contact";
        }
        return $this->sendResponse(true, $preFix . ' updated successfully.', $CompanyContact);
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
        $this->validate($request, [
            'priority'              =>  'required|in:H,M,L',
            'contact_id'            =>  'required|exists:contacts,id'
        ]);
        $CompanyContact = CompanyContact::where('id', $request->contact_id)->first();
        if (!$CompanyContact) {
            return error(__('Contact not found.'), [], 'validation');
        }

        $input = $request->only('priority');

        $CompanyContact->update($input);

        return $this->sendResponse(true, 'Priority updated successfully.', $CompanyContact);
    }

    /**
     * force Delete Contact api
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function softDelete(Request $request)
    {
        $this->validate($request, [
            'contact_id'   => 'required|array|exists:contacts,id',
            'main_type'    => 'nullable|in:CL,P,G',
        ]);


        foreach ($request->contact_id  as $contact_id_detail) {
            $query = CompanyContact::query();
            $CompanyContact = (clone $query)->where('id', $contact_id_detail)->first();

            if (!$CompanyContact) {
                return error(__('Contact not found.'), [], 'validation');
            }

            // $companyPeople = CompanyPeople::where('company_id', $contact_id_detail)->pluck('people_id')->toArray();
            // (clone $query)->whereIn('id', $companyPeople)->update(['company_name' => null]);

            $CompanyContact->delete();
        }

        if ($request->main_type == 'P') {
            $preFix = "Prospect(s)";
        } elseif ($request->main_type == 'CL') {
            $preFix = "Client(s)";
        } else {
            $preFix = "Contact(s)";
        }
        return ok(__($preFix . ' archived successfully'));
    }

    /**
     * force Delete Contact api
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */


    public function listArchive(Request $request)
    {
        $this->validate($request, [
            'page'      => 'required|integer|min:1',
            'perPage'   => 'required|integer|min:1',
            'user_id'   =>  'required|exists:users,id',
            'type'      =>  'required|in:G,P,CL',
            'section'   =>  'required|in:A,M,L',
            'sub_type'  =>  'required|in:C,P',
            'search'    => 'nullable'
        ]);

        $assignedContacts = AssignedContact::where('assigned_to_id', $request->user_id)->pluck('contact_id')->toArray();

        $currentContacts = CompanyContact::onlyTrashed()->where('created_by', $request->user_id)->pluck('id')->toArray();

        $selected = array_merge($assignedContacts, $currentContacts);

        $user = Role::where('name', 'Super Admin')->where('id', '=', auth()->user()->role_id)->first();

        if (($user) || ($request->section == 'A')) {
            $contact = CompanyContact::onlyTrashed()->where('type', '=', $request->type)
                ->where('section', '=', $request->section)
                ->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id',);
        } else {
            $contact = CompanyContact::onlyTrashed()->whereIn('id', $selected)->where('type', '=', $request->type)
                ->where('section', '=', $request->section)
                ->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id',);
        }

        if ($request->search) {
            $search = addslashes($request->search);

            $sql = "SELECT
        contacts.id
        FROM contacts
        LEFT JOIN cities ON contacts.city_id = cities.id
        LEFT JOIN countries ON contacts.country_code = countries.code
        where (contacts.company_name LIKE '%$search%' OR contacts.email LIKE '%$search%' OR contacts.phone_number LIKE '%$search%' OR cities.name LIKE '%$search%' OR countries.name LIKE '%$search%') ";
            $contactTeampIds = DB::select(DB::raw($sql));

            $contactIds = array_map(function ($value) {
                return $value->id;
            }, $contactTeampIds);
            $contact->whereIn('id', $contactIds);
        }

        /**For pagination */
        $paginateDetails = $this->getPaginationDetails($request->page, $request->perPage);
        $page    = $paginateDetails['page'];
        $perPage = $paginateDetails['perPage'];
        $count = $contact->count();
        $contact->skip($perPage * ($page - 1))->take($perPage);

        $contact = $contact->orderBy('created_at', 'desc')->get();

        foreach ($contact as $ca) {
            $ca['assigned_to_user_details'] = AssignedContact::with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $ca->id)->where('assigned_by_id', $request->user_id)->get();
        }
        return $this->sendResponse(true, 'List', [
            'Contact' => $contact,
            'count' => $count,
        ]);
    }

    /**
     * force Delete Contact api
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function listArchiveNew(Request $request)
    {
        $this->validate($request, [
            'page'           => 'required|integer|min:1',
            'perPage'        => 'required|integer|min:1',
            'type'           =>  'required|in:G,P,CL',
            'is_private'     =>  'boolean',
            'is_lost'        =>  'boolean',
            'sub_type'       =>  'required|in:C,P',
            'search'         => 'nullable',
            'is_import'      =>  'nullable|boolean',
            'social_type'    =>  'nullable|array|in:G,O,L',
            'company_name'   =>  'nullable',
            'assigned_to_id' =>  'nullable|array|exists:users,id',
        ]);

        // if(!empty($request->is_private) || !empty($request->is_lost))
        // {
        $assignedContactIds = AssignedContact::where('assigned_to_id', auth()->user()->id)->pluck('contact_id')->toArray();
        //}

        $user = Role::where('name', config('constants.super_admin'))->where('id', '=', auth()->user()->role_id)->first();

        $contacts = CompanyContact::onlyTrashed();

        if (empty($request->is_private) && empty($request->is_lost)) {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
                $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
                $contactIds = array_merge($contactIds, $authContactIds);
            }
        } elseif (!empty($request->is_private) && empty($request->is_lost)) {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', $request->is_private)->where('is_lost', 0)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_private', $request->is_private)->where('is_lost', 0)->pluck('id')->toArray();
            }
        } elseif (empty($request->is_private) && !empty($request->is_lost)) {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_lost', $request->is_lost)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_lost', $request->is_lost)->pluck('id')->toArray();
            }
        } else {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            }
        }

        if (isset($assignedContactIds)) {
            $contactIds = array_merge($assignedContactIds, $contactIds);
        }

        if (!empty($request->is_private) && empty($request->is_lost)) {
            $lostContactIds = (clone $contacts)->whereIn('id', $contactIds)->where('is_lost', 1)->pluck('id')->toArray();
            $contactIds = array_diff($contactIds, $lostContactIds);
        } elseif (empty($request->is_private) && !empty($request->is_lost)) {
            $contactIds = (clone $contacts)->whereIn('id', $contactIds)->where('is_lost', 1)->pluck('id')->toArray();
        }
        // Assigned contacts filter
        if ($request->assigned_to_id) {
            $assignedtoContactsIds = AssignedContact::where('assigned_by_id', auth()->user()->id)->whereIn('assigned_to_id', $request->assigned_to_id)->pluck('contact_id')->toArray();

            $contactIds = array_intersect($contactIds, $assignedtoContactsIds);
        }

        if ($request->type == 'G') {
            $contacts->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id')
                ->whereIn('id', array_unique($contactIds))
                ->where('sub_type', '=', $request->sub_type)
                ->where('company_id', auth()->user()->company_id);
        } else {
            $contacts->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id')
                ->whereIn('id', array_unique($contactIds))
                ->where('type', '=', $request->type)
                ->where('sub_type', '=', $request->sub_type)
                ->where('company_id', auth()->user()->company_id);
        }

        /**Search in filter */
        if (isset($request->is_import)) {
            $contacts->where('is_import', $request->is_import);
        }

        if (is_string($request->company_name) && ($request->company_name) && ($request->sub_type == 'P')) {
            $contacts->where('company_name', $request->company_name);
        } elseif (is_array($request->company_name) && ($request->company_name) && ($request->sub_type == 'P')) {
            $contacts->whereIn('company_name', $request->company_name);
        }

        if ($request->social_type) {
            $contacts->whereIn('social_type', $request->social_type);
        }

        // Search contacts
        if ($request->search) {
            $search = $request->search;
            $contacts->where(function ($query) use ($search) {
                $query->where('company_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone_number', 'like', '%' . $search . '%')
                    ->orWhereHas('city_details', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('country_details', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        // Get Contacts Count
        $count   = $contacts->count();
        /**For pagination */
        $page    = $request->page;
        $perPage = $request->perPage;
        $orderBy = $request->sortBy;
        $desc    = $request->descending ? 'desc' : 'asc';

        $contacts->skip($perPage * ($page - 1))->take($perPage);

        // Global sorting
        if (!empty($orderBy) && !empty($desc)) {
            $contacts->orderBy($orderBy, $desc);
        } else {
            $contacts->orderBy('created_at', 'desc');
        }

        $contacts = $contacts->get();
        foreach ($contacts as $contact) {
            $contact['assigned_to_user_details'] = AssignedContact::with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $contact->id)->get();
        }
        return $this->sendResponse(true, 'List', [
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
        $this->validate($request, [
            'contact_id'   => 'required|array|exists:contacts,id',
            'main_type'    => 'nullable|in:CL,P,G',
        ]);


        foreach ($request->contact_id  as $contact_id_detail) {
            $query = CompanyContact::query();
            $CompanyContact = (clone $query)->onlyTrashed()->where('id', $contact_id_detail)->first();

            if (!$CompanyContact) {
                return error(__('Contact not found.'), [], 'validation');
            }

            // $companyPeople = CompanyPeople::where('company_id', $contact_id_detail)->pluck('people_id')->toArray();
            // (clone $query)->whereIn('id', $companyPeople)->update(['company_name' => null]);

            $CompanyContact->restore();
        }

        if ($request->main_type == 'P') {
            $preFix = "Prospect(s)";
        } elseif ($request->main_type == 'CL') {
            $preFix = "Client(s)";
        } else {
            $preFix = "Contact(s)";
        }
        return ok(__($preFix . ' restored successfully'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function destroy(Request $request)
    {
        $this->validate($request, [
            'contact_id'   => 'required|array|exists:contacts,id',
            'main_type'    => 'nullable|in:CL,P,G',
        ]);


        foreach ($request->contact_id  as $contact_id_detail) {
            $query = CompanyContact::query();
            $CompanyContact = (clone $query)->onlyTrashed()->where('id', $contact_id_detail)->first();

            if (!$CompanyContact) {
                return error(__('Contact not found.'), [], 'validation');
            }

            // $companyPeople = CompanyPeople::where('company_id', $contact_id_detail)->pluck('people_id')->toArray();
            // (clone $query)->whereIn('id', $companyPeople)->update(['company_name' => null]);

            $user = auth()->user();
            // Decrement Gmail sync count
            if (($CompanyContact->social_type && $CompanyContact->social_type == 'G')) {
                if ($CompanyContact->type == 'P' && ($user->prospect_google_sync_count > 0)) {
                    $user->decrement('prospect_google_sync_count');
                } elseif ($CompanyContact->type == 'CL' && ($user->client_google_sync_count > 0)) {
                    $user->decrement('client_google_sync_count');
                } else {
                    if ($user->google_sync_count > 0) {
                        $user->decrement('google_sync_count');
                    }
                }
            }
            // Decrement Outlook sync count
            if (($CompanyContact->social_type && $CompanyContact->social_type == 'O')) {
                if ($CompanyContact->type == 'P' && ($user->prospect_outlook_sync_count > 0)) {
                    $user->decrement('prospect_outlook_sync_count');
                } elseif ($CompanyContact->type == 'CL' && ($user->client_outlook_sync_count > 0)) {
                    $user->decrement('client_outlook_sync_count');
                } else {
                    if ($user->outlook_sync_count > 0) {
                        $user->decrement('outlook_sync_count');
                    }
                }
            }
            // Decrement Linkdin sync count
            if (($CompanyContact->social_type && $CompanyContact->social_type == 'L')) {
                if ($CompanyContact->type == 'P' && ($user->prospect_linkdin_sync_count > 0)) {
                    $user->decrement('prospect_linkdin_sync_count');
                } elseif ($CompanyContact->type == 'CL' && ($user->client_linkdin_sync_count > 0)) {
                    $user->decrement('client_linkdin_sync_count');
                } else {
                    if ($user->linkdin_sync_count > 0) {
                        $user->decrement('linkdin_sync_count');
                    }
                }
            }
            $CompanyContact->forceDelete();
        }

        if ($request->main_type == 'P') {
            $preFix = "Prospect(s)";
        } elseif ($request->main_type == 'CL') {
            $preFix = "Client(s)";
        } else {
            $preFix = "Contact(s)";
        }
        return ok(__($preFix . ' permanently deleted successfully'));
    }


    /**
     * list contacts
     *
     * @param  mixed $request
     * @return void
     */

    public function list(Request $request)
    {
        $this->validate($request, [
            'page'      => 'required|integer|min:1',
            'perPage'   => 'required|integer|min:1',
            'user_id'   =>  'required|exists:users,id',
            'type'      =>  'required|in:G,P,CL',
            'section'   =>  'required|in:A,M,L',
            'sub_type'  =>  'required|in:C,P',
            'search'    => 'nullable'
        ]);

        $assignedContacts = AssignedContact::where('assigned_to_id', $request->user_id)->pluck('contact_id')->toArray();

        $currentContacts = CompanyContact::where('created_by', $request->user_id)->pluck('id')->toArray();

        $selected = array_merge($assignedContacts, $currentContacts);

        $user = Role::where('name', 'Super Admin')->where('id', '=', auth()->user()->role_id)->first();

        if (($user) || ($request->section == 'A')) {
            $contact = CompanyContact::where('type', '=', $request->type)
                ->where('section', '=', $request->section)
                ->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id',);
        } else {
            $contact = CompanyContact::whereIn('id', $selected)->where('type', '=', $request->type)
                ->where('section', '=', $request->section)
                ->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id',);
        }

        if ($request->search) {
            $search = addslashes($request->search);

            $sql = "SELECT
         contacts.id
         FROM contacts
         LEFT JOIN cities ON contacts.city_id = cities.id
         LEFT JOIN countries ON contacts.country_code = countries.code
         where (contacts.company_name LIKE '%$search%' OR contacts.email LIKE '%$search%' OR contacts.phone_number LIKE '%$search%' OR cities.name LIKE '%$search%' OR countries.name LIKE '%$search%') ";
            $contactTeampIds = DB::select(DB::raw($sql));

            $contactIds = array_map(function ($value) {
                return $value->id;
            }, $contactTeampIds);
            $contact->whereIn('id', $contactIds);
        }

        /**For pagination */
        $paginateDetails = $this->getPaginationDetails($request->page, $request->perPage);
        $page    = $paginateDetails['page'];
        $perPage = $paginateDetails['perPage'];
        $count = $contact->count();
        $contact->skip($perPage * ($page - 1))->take($perPage);

        $contact = $contact->orderBy('created_at', 'desc')->get();

        foreach ($contact as $ca) {
            $ca['assigned_to_user_details'] = AssignedContact::with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $ca->id)->where('assigned_by_id', $request->user_id)->get();
        }
        return $this->sendResponse(true, 'List', [
            'Contact' => $contact,
            'count' => $count,
        ]);
    }

    /**
     * list contacts
     *
     * @param  mixed $request
     * @return void
     */
    public function listNew(Request $request)
    {
        $this->validate($request, [
            'page'           => 'required|integer|min:1',
            'perPage'        => 'required|integer|min:1',
            //'user_id'    =>  'required|exists:users,id',
            'type'           =>  'required|in:G,P,CL',
            'is_private'     =>  'boolean',
            'is_lost'        =>  'boolean',
            'sub_type'       =>  'required|in:C,P',
            'search'         => 'nullable',
            'is_import'      =>  'nullable|boolean',
            'social_type'    =>  'nullable|array|in:G,O,L',
            'company_name'   =>  'nullable',
            'assigned_to_id' =>  'nullable|array|exists:users,id',
        ]);

        // if(!empty($request->is_private) || !empty($request->is_lost))
        // {
        $assignedContactIds = AssignedContact::where('assigned_to_id', auth()->user()->id)->pluck('contact_id')->toArray();
        //}

        $user = Role::where('name', config('constants.super_admin'))->where('id', '=', auth()->user()->role_id)->first();

        $contacts = CompanyContact::query();

        if (empty($request->is_private) && empty($request->is_lost)) {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
                $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
                $contactIds = array_merge($contactIds, $authContactIds);
            }
        } elseif (!empty($request->is_private) && empty($request->is_lost)) {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', $request->is_private)->where('is_lost', 0)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_private', $request->is_private)->where('is_lost', 0)->pluck('id')->toArray();
            }
        } elseif (empty($request->is_private) && !empty($request->is_lost)) {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_lost', $request->is_lost)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_lost', $request->is_lost)->pluck('id')->toArray();
            }
        } else {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            }
        }

        if (isset($assignedContactIds)) {
            $contactIds = array_merge($assignedContactIds, $contactIds);
        }

        if (!empty($request->is_private) && empty($request->is_lost)) {
            $lostContactIds = (clone $contacts)->whereIn('id', $contactIds)->where('is_lost', 1)->pluck('id')->toArray();
            $contactIds = array_diff($contactIds, $lostContactIds);
        } elseif (empty($request->is_private) && !empty($request->is_lost)) {
            $contactIds = (clone $contacts)->whereIn('id', $contactIds)->where('is_lost', 1)->pluck('id')->toArray();
        }

        // Assigned contacts filter
        if ($request->assigned_to_id) {
            $assignedtoContactsIds = AssignedContact::where('assigned_by_id', auth()->user()->id)->whereIn('assigned_to_id', $request->assigned_to_id)->pluck('contact_id')->toArray();

            $contactIds = array_intersect($contactIds, $assignedtoContactsIds);
        }

        if ($request->type == 'G') {
            $contacts->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id')
                ->whereIn('id', array_unique($contactIds))
                ->where('sub_type', '=', $request->sub_type)
                ->where('company_id', auth()->user()->company_id);
        } else {
            $contacts->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id')
                ->whereIn('id', array_unique($contactIds))
                ->where('type', '=', $request->type)
                ->where('sub_type', '=', $request->sub_type)
                ->where('company_id', auth()->user()->company_id);
        }

        /**Search in filter */
        if (isset($request->is_import)) {
            $contacts->where('is_import', $request->is_import);
        }

        if (is_string($request->company_name) && ($request->company_name) && ($request->sub_type == 'P')) {
            $contacts->where('company_name', $request->company_name);
        } elseif (is_array($request->company_name) && ($request->company_name) && ($request->sub_type == 'P')) {
            $contacts->whereIn('company_name', $request->company_name);
        }

        if ($request->social_type) {
            $contacts->whereIn('social_type', $request->social_type);
        }

        if ($request->search) {
            $search = $request->search;
            $contacts->where(function ($query) use ($search) {
                $query->where('company_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone_number', 'like', '%' . $search . '%')
                    ->orWhereHas('city_details', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('country_details', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        // Get Contacts Count
        $count   = $contacts->count();
        /**For pagination */
        $page    = $request->page;
        $perPage = $request->perPage;
        $orderBy = $request->sortBy;
        $desc    = $request->descending ? 'desc' : 'asc';

        $contacts->skip($perPage * ($page - 1))->take($perPage);

        // Global sorting
        if (!empty($orderBy) && !empty($desc)) {
            $contacts->orderBy($orderBy, $desc);
        } else {
            $contacts->orderBy('created_at', 'desc');
        }

        $contacts = $contacts->get();
        foreach ($contacts as $contact) {
            $contact['assigned_to_user_details'] = AssignedContact::with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $contact->id)->get();
        }
        return $this->sendResponse(true, 'List', [
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
        $this->validate($request, [
            'assigned_by_id'               =>  'required|exists:users,id',
            'contact_id'                   =>  'required|array|exists:contacts,id',
            'assigned_to_id'               =>  'required|array|exists:users,id',
            'main_type'                    =>  'nullable|in:G,P,CL',
            'is_big_card'                  =>  'nullable',
        ]);


        $query = AssignedContact::query();
        $existsContact = (clone $query)->whereIn('contact_id', $request->contact_id)->whereIn('assigned_to_id', $request->assigned_to_id)->where('assigned_by_id', $request->assigned_by_id)->pluck('contact_id')->toArray();

        $CompanyContact = CompanyContact::query();
        $lostContact    = (clone $CompanyContact)->whereIn('id', $request->contact_id)->where('is_lost', 1)->count();
        if ($lostContact > 0) {
            return error(__('you can not assing lost contacts'), [], 'validation');
        }
        //$self_contact = (clone $CompanyContact)->where('created_by', $request->assigned_to_id)->where('id', $request->contact_id)->update(['section' => 'M']);
        //$id = (clone $CompanyContact)->where('created_by', $request->assigned_to_id)->where('id', $request->contact_id)->pluck('id')->toArray();
        $id = [];

        $existsId = array_merge($existsContact, $id);

        /* Check contact already assigned or not */

        if (!empty($existsId) && (!isset($request->is_big_card) || isset($request->is_big_card)) && empty($request->is_big_card)) {
            $data = [];
            $contactsName = (clone $CompanyContact)->select('id', 'first_name', 'last_name', 'sub_type', 'company_name')->whereIn('id', $existsId)->get();
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
        if (isset($request->is_big_card) && !empty($request->is_big_card)) {
            AssignedContact::whereIn('contact_id', $request->contact_id)->where('assigned_by_id', $request->assigned_by_id)->delete();
        }

        foreach ($request->contact_id  as $contact_id_detail) {
            foreach ($request->assigned_to_id as $assigned_to_user_detail) {
                $assigned_contact = [];
                $assigned_contact['contact_id'] = $contact_id_detail;
                $assigned_contact['assigned_by_id'] = $request->assigned_by_id ?? null;
                $assigned_contact['assigned_to_id'] =  $assigned_to_user_detail;
                $assigned_contact_create = (clone $query)->create($assigned_contact);
            }
        }

        if ($request->main_type == 'P') {
            $preFix = "Prospect(s)";
        } elseif ($request->main_type == 'CL') {
            $preFix = "Client(s)";
        } else {
            $preFix = "Contact(s)";
        }
        // $CompanyContact->update($input_details);
        $this->sentAssignNotification($request->assigned_to_id, $request->contact_id);
        return $this->sendResponse(true, $preFix . ' assigned successfully.', $assigned_contact_create);
    }

    /**
     * export Contact api
     *
     * @param  mixed $request
     * @return void
     */

    public function export(Request $request)
    {
        $this->validate($request, [
            'contact_id'            => 'required|array|exists:contacts,id',
            'type'                  =>  'required|in:G,P,CL',
            'sub_type'              =>  'required|in:C,P',
        ]);

        $contact = CompanyContact::with('city_details')->whereIn('id', $request->contact_id)->where('sub_type', '=', $request->sub_type)
            ->where('company_id', auth()->user()->company_id)->get();

        if ($request->sub_type == 'P') {
            return Excel::download(new ContactExport($contact), 'ContactExport.csv');
        } else {
            return Excel::download(new CompanyContactExport($contact), 'ContactExport.csv');
        }
    }

    /**
     * import company Contact api
     *
     * @param  mixed $request
     * @return void
     */

    public function importcompany(Request $request)
    {
        $v = $this->validate($request, [
            'select_file'  => 'required',
            'type'         =>  'required|in:G,P,CL',
            'section'      =>  'required|in:A,M,L',
            'sub_type'     =>  'required|in:C',
        ]);

        $file = request()->file('select_file');
        $mimeType = strtolower($file->getClientOriginalExtension());

        if (!in_array($mimeType, ['csv', 'xlsx', 'xls'])) {
            return error(__('The  selected file must be a file of type: xls, xlsx, csv.'), [], 'validation');
        }

        ini_set('post_max_size', '2000M');
        ini_set('upload_max_filesize', '2000M');
        ini_set('max_execution_time', '300000');
        ini_set('client_max_body_size', '200M');
        ini_set('memory_limit', '5000M');
        ini_set('max_input_time', '30000');

        $data = [
            'type' => $request->type,
            'section' => $request->section,
            'sub_type' => $request->sub_type,
        ];
        $url = env('WEBAPP_URL');


        try {
            Excel::import(new PeopleContactImport($data), request()->file('select_file'));
        } catch (Exception $e) {
            return redirect()->away($url . '?auth_error=' . $e->getMessage());
        }
        return ok(__('Contact data Imported successfully'));
    }


    /**
     * import Contact api
     *
     * @param  mixed $request
     * @return void
     */

    public function import(Request $request)
    {
        $v = $this->validate($request, [
            'select_file'  => 'required',
            'type'         =>  'required|in:G,P,CL',
            'section'      =>  'required|in:A,M,L',
            'sub_type'     =>  'required|in:P',
        ]);

        $file = request()->file('select_file');
        $mimeType = strtolower($file->getClientOriginalExtension());

        if (!in_array($mimeType, ['csv', 'xlsx', 'xls'])) {
            return error(__('The  selected file must be a file of type: xls, xlsx, csv.'), [], 'validation');
        }

        ini_set('post_max_size', '2000M');
        ini_set('upload_max_filesize', '2000M');
        ini_set('max_execution_time', '300000');
        ini_set('client_max_body_size', '200M');
        ini_set('memory_limit', '5000M');
        ini_set('max_input_time', '30000');

        $data = [
            'type' => $request->type,
            'section' => $request->section,
            'sub_type' => $request->sub_type,
        ];

        $url = env('WEBAPP_URL');

        try {
            Excel::import(new CompanyContactImport($data), request()->file('select_file'));
        } catch (Exception $e) {
            return redirect()->away($url . '?auth_error=' . $e->getMessage());
        }
        return ok(__('Contact data Imported successfully'));
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
        return Response::download($filepath);
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
        return Response::download($filepath);
    }

    /**
     * move Contact api.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function move(Request $request)
    {
        $this->validate($request, [
            'contact_id'       => 'required|exists:contacts,id',
            'is_private'       =>  'nullable|boolean|',
            'is_lost'          =>  'nullable|boolean'
            //'section'          =>  'required|in:A,M,L',
        ]);


        $CompanyContact = CompanyContact::where('id', $request->contact_id)->first();

        if (!$CompanyContact) {
            return error(__('Contact not found.'), [], 'validation');
        }

        if (isset($request->is_private) && $CompanyContact->is_private == $request->is_private) {

            return error(__('Contact can not move in same category.'), [], 'validation');
        }
        if (isset($request->is_lost) && $CompanyContact->is_lost == $request->is_lost) {

            return error(__('Contact can not move in same category.'), [], 'validation');
        }
        if (empty($CompanyContact->is_private) && !empty($request->is_private)) {
            return error(__('Contact can not move from All clients to my clients.'), [], 'validation');
        }

        if (!empty($CompanyContact->is_lost)) {
            return error(__('Contact can not move from Lost clients.'), [], 'validation');
        }

        $input = $request->only('is_private', 'is_lost');
        $CompanyContact->update($input);

        return $this->sendResponse(true, 'Contact moved successfully.');
    }

    /**
     * list contacts
     *
     * @param  mixed $request
     * @return void
     */

    public function view()
    {
        $CompanyContact = CompanyContact::where('id', $_GET['contact_id'])->first();

        if (!$CompanyContact) {
            return error(__('Contacts not found.'), [], 'validation');
        }

        $contact = CompanyContact::where('id', $_GET['contact_id'])->with('city_details', 'country_details', 'company:id,name')->get();

        foreach ($contact as $ca) {
            $ca['assigned_to_user_details'] =  AssignedContact::with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $ca->id)->where('assigned_by_id', $CompanyContact->created_by)->get();
        }

        return $this->sendResponse(true, 'Contact details.', $contact);
    }

    /**
     * share contact
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function share(Request $request)
    {
        $this->validate($request, [
            'assigned_by_id'               =>  'required|exists:users,id',
            'contact_id'                   => 'required|exists:contacts,id',
            'assigned_to_id'               =>  'required|array|exists:users,id',
            'emailMessage'                 => 'required',
        ]);



        $CompanyContact = CompanyContact::where('id', $request->contact_id)->first();

        if (!$CompanyContact) {
            return error(__('Contact not found.'), [], 'validation');
        }

        if ($request->assigned_to_id) {
            $email_array = array();

            foreach ($request->assigned_to_id as $user_detail) {

                if ($request->assigned_by_id == $user_detail) {
                    return error(__('You can not share contacts to same user.'), [], 'validation');
                }

                $assigned_to = User::where('id', '=', $user_detail)->first();
                $assigned_by = User::where('id', '=', $request->assigned_by_id)->first();

                $email = $assigned_to->email;
                //$from = $assigned_by->email;

                $data = array(
                    'assigned_to' => $assigned_to,
                    'assigned_by' => $assigned_by,
                    'CompanyContact' => $CompanyContact,
                    'emailMessage' => $request->emailMessage,
                );

                $subject = 'Shared Contact';

                //Mail::send('emails.ShareContact', ['data' => $data], function ($message) use ($subject, $email, $from) {
                Mail::send('emails.ShareContact', ['data' => $data], function ($message) use ($subject, $email) {
                    //$message->from($from);
                    $message->to($email);
                    $message->subject($subject);
                });
            }
        }
        $share = 'shared';
        /*Sent notification to the admin */
        $this->sentAssignNotification($request->assigned_to_id, [$CompanyContact->id], $share);
        return $this->sendResponse(true, 'The contact has been sent to selected emails successfully.');
    }

    /**
     * list email
     *
     * @param  mixed $request
     * @return void
     */

    public function listemail(Request $request)
    {
        $this->validate($request, [
            'user_id'               =>  'required|array|exists:users,id',
        ]);



        if ($request->user_id) {
            $email_array = array();

            foreach ($request->user_id as $user_detail) {
                $pest = User::where('id', '=', $user_detail)->first();
                $email_array[] =  $pest->email;
            }
        }

        return $this->sendResponse(true, 'emails', $email_array);
    }

    /**
     * list contacts
     *
     * @param  mixed $request
     * @return void
     */

    public function allmylist(Request $request)
    {
        $this->validate($request, [
            'user_id'               =>  'required|exists:users,id',
            'type'                  =>  'required|in:G,P,CL',
            'sub_type'              =>  'required|in:C,P',
        ]);

        $assignedContacts = AssignedContact::where('assigned_to_id', $request->user_id)->pluck('contact_id')->toArray();

        $currentContacts = CompanyContact::where('company_id', auth()->user()->company_id)->where('created_by', $request->user_id)->pluck('id')->toArray();

        $selected = array_merge($assignedContacts, $currentContacts);

        $contact = CompanyContact::where('company_id', auth()->user()->company_id)->whereIn('id', $selected)->where('type', '=', $request->type)->where('section', '!=', 'L')->where('sub_type', '=', $request->sub_type)->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name');

        $contact = $contact->get();

        return $this->sendResponse(true, 'Contact', $contact);
    }

    /* Get Company contact */
    public function getpeople(Request $request)
    {
        $this->validate($request, [
            'company_name' => 'required',
            'user_id'   =>  'required|exists:users,id',
            'type'      =>  'required|in:G,P,CL',
            'section'   =>  'required|in:A,M,L',
        ]);

        $assignedContacts = AssignedContact::where('assigned_to_id', $request->user_id)->pluck('contact_id')->toArray();

        $currentContacts = CompanyContact::where('company_id', auth()->user()->company_id)->where('created_by', $request->user_id)->pluck('id')->toArray();

        $selected = array_merge($assignedContacts, $currentContacts);

        $contact = CompanyContact::where('company_id', auth()->user()->company_id)->select('id', 'first_name', 'last_name', 'company_name')->where('company_name', '=', $request->company_name)->whereIn('id', $selected)->where('type', '=', $request->type)->where('section', '=', $request->section)->where('sub_type', 'P')->get();

        return $this->sendResponse(true, 'People', $contact);
    }

    /* Get Company contact */
    public function getCompanyContact(Request $request)
    {
        $this->validate($request, [
            //'user_id'   =>  'required|exists:users,id',
            'type'      =>  'required|in:G,P,CL',
            'section'   =>  'required|in:A,M,L',
        ]);

        // $assignedContacts = AssignedContact::where('assigned_to_id', $request->user_id)->pluck('contact_id')->toArray();

        // $currentContacts = CompanyContact::where('created_by', $request->user_id)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
        $assignedContactIds = AssignedContact::where('assigned_to_id', auth()->user()->id)->pluck('contact_id')->toArray();

        //$currentContacts = CompanyContact::where('created_by', auth()->user()->id)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();

        $user = Role::where('name', config('constants.super_admin'))->where('id', '=', auth()->user()->role_id)->first();

        // $selected = array_merge($assignedContacts, $currentContacts);
        // $is_private = 0;
        // $is_lost    = 0;
        $contacts = CompanyContact::query();

        if ($request->section == 'A') {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
                $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
                $contactIds = array_merge($contactIds, $authContactIds);
            }
        } elseif ($request->section == 'M') {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 1)->where('is_lost', 0)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_private', 1)->where('is_lost', 0)->pluck('id')->toArray();
            }
        } elseif ($request->section == 'L') {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_lost', 1)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('created_by', auth()->user()->id)->where('is_lost', 1)->pluck('id')->toArray();
            }
        } else {
            if ($user) {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
            } else {
                $contactIds = (clone $contacts)->where('company_id', auth()->user()->company_id)->where('is_private', 0)->pluck('id')->toArray();
                $authContactIds = (clone $contacts)->where('created_by', auth()->user()->id)->pluck('id')->toArray();
                $contactIds = array_merge($contactIds, $authContactIds);
            }
        }

        if (isset($assignedContactIds)) {
            $contactIds = array_merge($assignedContactIds, $contactIds);
        }

        if ($request->type == 'G') {
            $contacts->select('id', 'first_name', 'last_name', 'company_name')
                ->whereIn('id', array_unique($contactIds))
                ->where('sub_type', '=', 'C')
                ->where('company_id', auth()->user()->company_id);
        } else {
            $contacts->select('id', 'first_name', 'last_name', 'company_name')
                ->whereIn('id', array_unique($contactIds))
                ->where('type', '=', $request->type)
                ->where('sub_type', '=', 'C')
                ->where('company_id', auth()->user()->company_id);
        }

        $contacts =  $contacts->get();
        // if($request->section == 'M' ||$request->section == 'A')
        // {
        //     $contact = CompanyContact::select('id', 'first_name', 'last_name', 'company_name')->whereIn('id', $selected)->where('type', '=', $request->type)->where('company_id', auth()->user()->company_id)->where('is_private', $is_private)->where('sub_type', 'C')->get();
        // }else{
        //     $contact = CompanyContact::select('id', 'first_name', 'last_name', 'company_name')->whereIn('id', $selected)->where('type', '=', $request->type)->where('company_id', auth()->user()->company_id)->where('is_lost', $is_lost)->where('sub_type', 'C')->get();
        // }
        return $this->sendResponse(true, 'People', $contacts);
    }

    /**
     * filter contacts
     *
     * @param  mixed $request
     * @return void
     */

    public function filter(Request $request)
    {
        $this->validate($request, [
            'page'              => 'required|integer|min:1',
            'perPage'           => 'required|integer|min:1',
            'user_id'           =>  'required|exists:users,id',
            'type'              =>  'required|in:G,P,CL',
            'section'           =>  'required|in:A,M,L',
            'sub_type'          =>  'required|in:C,P',
            'assigned_to_id'    =>  'nullable|array|exists:users,id',
            'is_import'         =>  'nullable|boolean',
            'social_type'       =>  'nullable|array|in:G,O,L',
            'company_name'      =>  'nullable',
        ]);

        $assignedContacts = AssignedContact::where('assigned_to_id', $request->user_id)->pluck('contact_id')->toArray();

        $currentContacts = CompanyContact::where('created_by', $request->user_id)->pluck('id')->toArray();

        $selected = array_merge($assignedContacts, $currentContacts);

        $user = Role::where('name', 'Super Admin')->where('id', '=', auth()->user()->role_id)->first();

        if (($user) || ($request->section == 'A')) {
            $contact = CompanyContact::where('type', '=', $request->type)
                ->where('section', '=', $request->section)
                ->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id',);
        } else {
            $contact = CompanyContact::whereIn('id', $selected)->where('type', '=', $request->type)
                ->where('section', '=', $request->section)
                ->where('sub_type', '=', $request->sub_type)->where('company_id', auth()->user()->company_id)->with('city_details', 'country_details', 'users:id,first_name,last_name', 'company:id,name', 'assinged_contact:id,contact_id,assigned_to_id',);
        }

        /**Search in filter */
        if (isset($request->is_import)) {
            $contact = $contact->where('is_import', $request->is_import);
        }

        if (($request->company_name) && ($request->sub_type == 'P')) {
            $contact = $contact->where('company_name', $request->company_name);
        }

        if ($request->social_type) {
            $contact = $contact->whereIn('social_type', $request->social_type);
        }

        if ($request->assigned_to_id) {
            $assignedtoContacts = AssignedContact::where('assigned_by_id', $request->user_id)->whereIn('assigned_to_id', $request->assigned_to_id)->pluck('contact_id')->toArray();

            $selected = array_intersect($selected, $assignedtoContacts);
        }

        $contact = $contact->whereIn('id', $selected);

        /**For pagination */
        $paginateDetails = $this->getPaginationDetails($request->page, $request->perPage);
        $page    = $paginateDetails['page'];
        $perPage = $paginateDetails['perPage'];
        $count = $contact->count();
        $contact->skip($perPage * ($page - 1))->take($perPage);

        $contact = $contact->get();

        foreach ($contact as $ca) {
            $ca['assigned_to_user_details'] = AssignedContact::with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $ca->id)->where('assigned_by_id', $request->user_id)->get();
        }

        return $this->sendResponse(true, 'List', [
            'Contact' => $contact,
            'count' => $count,
        ]);
    }

    /* Get Company Team */
    public function companyTeam(Request $request)
    {
        $this->validate($request, [
            'company_id' => 'nullable|exists:contacts,id',
            'people_id'  => 'nullable|exists:contacts,id',
            'search'     => 'nullable',
            'sort_by'    => 'nullable|in:asc,desc',
            'page'       => 'nullable',
            'perPage'    => 'nullable',
            'contact_id' => 'nullable|array'
        ]);
        $queryData = CompanyPeople::query();
        $peopleId = [];
        if (!$request->contact_id) {
            $contact_id = [];
        } else {
            $contact_id = $request->contact_id;
        }
        if ($request->company_id) {
            $peopleId = (clone $queryData)->where('company_id', $request->company_id)->pluck('people_id')->toArray();
        }
        if ($request->people_id) {
            $getCompany    = (clone $queryData)->where('people_id', $request->people_id)->first();
            if ($getCompany) {
                $peopleId  = (clone $queryData)->where('company_id', $getCompany->company_id)->pluck('people_id')->toArray();
            }
        }
        //dd($peopleId);
        //$query = CompanyContact::whereIn('id', array_unique($peopleId))->where('sub_type', 'P');

        /* Search functionality */
        // if ($request->search) {
        //     $search = $request->search;
        //     $query = $query->where(function ($q) use ($search) {
        //         //$q->where('first_name', 'LIKE', '%' . $search . '%');
        //         $q->where(DB::raw("CONCAT(`first_name`, ' ', `last_name`)"), 'LIKE', '%' . $search . '%')->orWhere(DB::raw("CONCAT(`last_name`, ' ', `first_name`)"), 'LIKE', '%' . $search . '%');
        //     });
        // }

        // $count = (clone $query)->count();
        /* Sort by asc and desc order based on created date */
        // if ($request->sort_by) {
        //     $query->orderBy('created_at', $request->sort_by);
        // }

        $peopleId = array_diff($peopleId, $contact_id);
        $teams = CompanyContact::whereIn('id', array_unique($peopleId))->where('sub_type', 'P');

        /* Search functionality */
        if ($request->search) {
            $search = $request->search;
            $teams->where(function ($query) use ($search) {
                //$q->where('first_name', 'LIKE', '%' . $search . '%');
                $query->where(DB::raw("CONCAT(`first_name`, ' ', `last_name`)"), 'LIKE', '%' . $search . '%')->orWhere(DB::raw("CONCAT(`last_name`, ' ', `first_name`)"), 'LIKE', '%' . $search . '%');
            });
        }

        $count = (clone $teams)->count();
        /* Sort by asc and desc order based on created date */
        if ($request->sort_by) {
            $teams->orderBy('created_at', $request->sort_by);
        } else {
            $teams->orderBy('created_at', 'DESC');
        }
        /*Pagination */

        //if ($request->page && $request->perPage) {
$page    = $request->page;
            $perPage = $request->perPage;
            $teams->skip($perPage * ($page - 1))->take($perPage);
        //}
        $contactIds = (clone $teams)->pluck('id')->toArray();
        $contactIds = array_merge($contactIds, $contact_id);
        $teams = $teams->get();
        return ok(__('Teams'), [
            'team' => $teams,
            'count' => $count,
            'contact_id' => $contactIds
        ]);
    }
    /* Sent notification create common function when create,edit,assing and share */

    public function sentAssignNotification($assingTo, $contacts, $share = null)
    {

        $role            = Role::where('name', 'Super Admin')->first();

        $users           = User::whereIn('id', $assingTo)->whereNotIn('role_id', [$role->id])->where('company_id', auth()->user()->company_id)->selectRaw("id, concat(first_name, ' ', last_name) as username")->pluck('username', 'id')->toArray();

        $contactData = [];

        $contactsName = CompanyContact::select('id', 'first_name', 'last_name', 'sub_type', 'type', 'company_name')->whereIn('id', $contacts)->get();

        foreach ($contactsName as $value) {
            if ($value->sub_type == "C") {
                $type = $value->type;
                array_push($contactData, $value->company_name);
            } else {
                array_push($contactData, $value->full_name);
            }
        }
        $userFullName    = implode(",", $users);
        $contactFullName = implode(",", $contactData);
        $shared = $share ?? 'assigned';
        if (!$users) {
            $message         = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has ' . $shared . ' to you';
        } else if (count($assingTo) == count($users)) {
            $message         = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has ' . $shared . ' the ' . $contactFullName . ' to ' . $userFullName;
        } else {
            $message         = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has ' . $shared . ' the ' . $contactFullName . ' to ' . $userFullName . ' and you';
        }
        $contactType     = $contactsName->first();

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
                    'id' => Str::uuid(),
                    'sender_id' => auth()->user()->id,
                    'receiver_id' => $key,
                    'title' => $teamMessage,
                    'icon' => $icon,
                    'created_at' => now()
                ];
            }
            SystemNotification::insert($notification);
        }
    }
}
