<?php

namespace App\Http\Controllers\API;

use App\Exports\ContactListExport;
use App\Exports\ListContactsExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContactList;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ContactListAssigned;
use App\Models\User;
use App\Models\ContactListAssignedUser;
use App\Models\Contact;
use App\Models\AssignedList;
use Illuminate\Support\Str;
use App\Models\CompanyContact;
use App\Models\Role;
use App\Models\AssignedContact;
use App\Models\ListContact;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\DB;

class ContactListController extends Controller
{
    /**
     * Contact list api
     *
     * @param  mixed $request
     * @return void
     */
    public function list(Request $request)
    {
        //Validation
        $this->validate($request, [
            'page'              => 'integer',
            'perPage'           => 'integer',
            'search'            => 'nullable',
            'filter'            => 'nullable',
            'is_excel'          => 'nullable|boolean',
            'main_type'         => 'nullable|in:G,P,CL',
            'list_id'           => 'nullable|array|exists:lists,id',
            'assigned_to_id'    =>  'nullable|array|exists:users,id',
            'type'              => 'nullable|array|in:P,CL,C,LC',
            'sub_type'          => 'nullable|in:C,P',
        ]);
        $assingedListQuery = AssignedList::query();
        $query = ContactList::query();
        $role = Role::where('id', auth()->user()->role_id)->first();
        if ($role->name == config('constants.super_admin')) {
            $query  =  $query->where('company_id', auth()->user()->company_id)->with('users:id,first_name,last_name');
        } else {
            $query  = $query->with('users:id,first_name,last_name')->WhereHas('assignedList', function ($query) {
                $query->where('owned_by', auth()->user()->id)
                    ->orWhere('assigned_to', auth()->user()->id);
            });
        }

        /* Search functionality */
        if ($request->search) {
            $search = $request->search;
            $query  = $query->where(function ($subquery) use ($search) {
                $subquery->where('name', 'LIKE', "%{$search}%");
                $subquery->orWhere('type', 'LIKE', "%{$search}%")
                ->orWhereHas('users', function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', "%{$search}%");
                    $query->orWhere('last_name', 'LIKE', "%{$search}%");
                });
            });
        }

        // Type search filter
        if ($request->type) {
            $query = $query->whereIn('type', $request->type);
        }

        // Sub Type search filter
        if ($request->sub_type) {
            $query = $query->where('sub_type', $request->sub_type);
        }

        // assigned_to_id search filter
        if ($request->assigned_to_id) {
            $assignedtoContacts = (clone $assingedListQuery)->whereIn('assigned_to', $request->assigned_to_id)->pluck('list_id')->toArray();
            $query              = $query->whereIn('id', $assignedtoContacts);
        }

        /*For pagination and sorting filter*/
        $result = filterSortPagination($query);
        $contactList = $result['query']->get();
        $count  = $result['count'];

        if ($request->list_id) {
            return Excel::download(new ContactListExport($query->whereIn('id', $request->list_id)->get()), 'ContactList.csv');
        }
        foreach ($contactList as $lists) {

            $lists['assigned_to_user_details'] = (clone $assingedListQuery)->where('assigned_to', '!=', null)
                ->where(function ($query) {
                    $query->where('assigned_from', auth()->user()->id)
                        ->orWhere('assigned_to', auth()->user()->id);
                })->with('assigned_to_details', 'assigned_by_details')->where('list_id', $lists->id)->get();
        }
        return ok(__('Contactlist'), [
            'conatctList' => $contactList,
            'count'       => $count
        ]);
    }

    /**
     * Contact list merge api
     *
     * @param  mixed $request
     * @return void
     */
    public function merge(Request $request)
    {
        //Validation
        $this->validate($request, [
            'main_type' => 'required|in:G,P,CL',//G=General,P=Prospect,CL=Client
            'type'      => 'required|in:P,CL,C,LC',//P=Prospect, CL=Custom List, C=Client, LC=Lost Contacts
            'sub_type'  => 'nullable|in:C,P',//C=Company, P=People
            'name'      => 'required|min:3|max:100|unique:lists,name,NULL,id,deleted_at,NULL',
            'list_id'   => 'required|array|exists:lists,id',
        ]);

        $request['company_id'] = auth()->user()->company_id;
        $contactListQuery = ContactList::query();
        $listQuery        = ListContact::query();
        /* Get the sub_type of the first contact list */
        $firstContactList = (clone $contactListQuery)->find($request->list_id[0]);
        $subType = $firstContactList->sub_type;

        /* Check if all list_id have the same sub_type */
        foreach ($request->list_id as $listId) {
            $contactList = (clone $contactListQuery)->find($listId);
            if ($contactList->sub_type != $subType) {
                return error(__('Lists cannot be merged as they have different contact types. Please select lists with the same contact type'), [], 'validation');
            }
        }

        /* Retrieve all contacts for the given lists */
        $contacts = [];
        foreach ($request->list_id as $listId) {
            $listContacts = (clone $listQuery)->where('list_id', $listId)->get();
            foreach ($listContacts as $listContact) {
                $contacts[] = [
                    'contact_id' => $listContact->contact_id,
                    'type'       => $listContact->type
                ];
            }
        }

        /* Remove any duplicate contacts */
        $uniqueContacts = collect($contacts)->unique('contact_id')->values()->all();

        /*Store record in contact list */
        $contactList    = (clone $contactListQuery)->create($request->only('company_id', 'type', 'sub_type', 'main_type', 'name'));
        $successMessage = 'Lists merged successfully!';

        /*Store record in assigned list */
        AssignedList::create([
            'list_id'  => $contactList->id,
            'owned_by' => auth()->user()->id,
        ]);

        /* Store contacts in ListContact table for the new list */
        foreach ($uniqueContacts as $contact) {
            (clone $listQuery)->create([
                'list_id'    => $contactList->id,
                'contact_id' => $contact['contact_id'],
                'type'       => $contact['type']
            ]);
        }

        return ok(__($successMessage), $contactList);
    }

    /**
     * Contact list create api
     *
     * @param  mixed $request
     * @return void
     */
    public function store(Request $request)
    {
        //Validation
        $this->validate($request, [
            'main_type'  => 'required|in:G,P,CL',//G=General,P=Prospect,CL=Client
            'type'       => 'required|in:P,CL,C,LC',//P=Prospect, CL=Custom List, C=Client, LC=Lost Contacts
            'sub_type'   => 'nullable|in:C,P',//C=Company, P=People
            'name'       => 'required|min:3|max:100',
            'contact_id' => 'nullable|array|exists:contacts,id'

        ],[
           'name.required' => 'List name is required',
        ]);

        $query = ContactList::query();
        $existContactList = (clone $query)->where('name', $request->name)->where('company_id', auth()->user()->company_id)->first();
        if ($existContactList) {
            return error(__('List name is already taken'), ['name' => 'List name is already taken'], 'validation');
        }
        $request['company_id'] = auth()->user()->company_id;

        /*Store record in contact list */
        $contactList    = (clone $query)->create($request->only('company_id', 'type', 'sub_type', 'main_type', 'name'));
        $successMessage = 'List created successfully';
        /*Store record in assigned list */
        AssignedList::create([
            'list_id'  => $contactList->id,
            'owned_by' => auth()->user()->id,
        ]);
        /* Add contacts to new list */
        /*Sent notification when create list */
        $type    = 'lists';
        $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' ' . ' has created a list ' . $contactList->name;
        sentNotification($message, $type);
        if ($request->contact_id) {
            $query = CompanyContact::query();
            $count = (clone $query)->whereIn('id', $request->contact_id)->where('company_id', $contactList->company_id)->count();

            $contactCount = count($request->contact_id);

            if ($count != $contactCount) {
                return error(__('Can not add another company contact'), [], 'validation');
            }

            if (auth()->user()->company_id != $contactList->company_id) {
                return error(__('Contact can not add to other company member list'), [], 'validation');
            }

            $contactList->listContact()->attach($request->contact_id, ['type' => $contactList->type]);
            $contactsName = (clone $query)->whereIn('id', $request->contact_id)->where('company_id', auth()->user()->company_id)->get();
            $name = [];
            foreach ($contactsName as $value) {
                if ($value->sub_type == "C") {
                    array_push($name, $value->company_name);
                } else {
                    array_push($name, $value->full_name);
                }
            }
            /*Sent notification conatct add to new create list*/
            $getName = implode(",", $name);
            $message = $getName . ' ' . 'has been added to the list';
            sentNotification($message, $type);
            $contact = (clone $query)->where('id', $request->contact_id)->first();
            //Get Message Prefix
            $preFix = prefix($contact->type);
            $successMessage = $preFix . ' add to new list successfully';
        }

        return ok(__($successMessage), $contactList);
    }

    /**
     * Contact list update api
     *
     * @param  mixed $request
     * @return void
     */
    public function update(Request $request, $id)
    {
        //Validation
        $this->validate($request, [
            'main_type' => 'required|in:G,P,CL',//G=General,P=Prospect,CL=Client
            'type'      => 'required|in:P,CL,C,LC',//P=Prospect, CL=Custom List, C=Client, LC=Lost Contacts
            'sub_type'  => 'nullable|in:C,P',//C=Company, P=People
            'name'      => 'required|min:3|max:1000|unique:lists,name,' . $id . ',id,deleted_at,NULL',
        ]);

        // Find contact list
        $contactList = ContactList::find($id);

        if (!isset($contactList) && empty($contactList)) {
            return error(__('Contact list not found'), [], 'validation');
        }
        //Update contact list
        $contactList->update($request->only(['company_id', 'type', 'sub_type', 'main_type', 'name']));

        return ok(__('List updated successfully'), $contactList);
    }

    /**
     * Contact list delete api
     *
     * @param  mixed $request
     * @return void
     */
    public function delete(Request $request, $id = null)
    {
        $query            = AssignedList::query();
        $contactListQuery = ContactList::query();

        if (!isset($id)) {
            //Validation
            $this->validate($request, [
                'list_id' => 'nullable|array|exists:lists,id,deleted_at,NULL'
            ],[
                'list_id.exists' => 'Contact list not found.',
            ]);
            $role = Role::where('id', auth()->user()->role_id)->first();
            if ($role->name == config('constants.super_admin')) {
                (clone $contactListQuery)->whereIn('id', $request->list_id)->delete();
                ListContact::whereIn('list_id', $request->list_id)->delete();
                $query->whereIn('list_id', $request->list_id)->delete();
            } else {
                $contactList = (clone $query)->where('owned_by', auth()->user()->id)->whereIn('list_id', $request->list_id)->pluck('list_id')->toArray();
                if (empty($contactList)) {
                    (clone $query)->whereIn('list_id', $request->list_id)->where('assigned_to', auth()->user()->id)->delete();
                } else {

                    (clone $query)->where('owned_by', auth()->user()->id)->whereIn('list_id', $contactList)->delete();
                }
            }
        } else {
            $role = Role::where('id', auth()->user()->role_id)->first();
            if ($role->name == config('constants.super_admin')) {
                $listId = (clone $contactListQuery)->findOrFail($id);
                if (!$listId) {
                    return error(__('Contact list not found'));
                }
                $listId->listContact()->detach();
                $listId->assignedList()->delete();
                $listId->delete();
            } else {
                $contactList = (clone $query)->where('owned_by', auth()->user()->id)->where('list_id', $id)->first();

                if (!$contactList) {

                    $contactList = (clone $query)->where('assigned_to', auth()->user()->id)->where('list_id', $id)->first();

                    if (!$contactList) {
                        return error(__('Contact list not found'));
                    }
                    /*Delete on single */
                    $contactList->delete();
                } else {
                    /*Delete on single */
                    $contactList->delete();
                }
            }
        }
        return ok(__('List deleted successfully'));
    }

    /**
     * Contact list assing api
     *
     * @param  mixed $request
     * @return void
     */
    public function assign(Request $request)
    {
        //Validation
        $this->validate($request, [
            'list_id'     => 'nullable|array|exists:lists,id',
            'assigned_to' => 'nullable|array|exists:users,id'
        ]);
        $assignedContact = [];
        $query = AssignedList::query();

        $contactList = ContactList::query();

        /* Check already list assinged or not */
        $listIds = $query->whereIn('list_id', $request->list_id)->whereIn('assigned_to', $request->assigned_to)->pluck('list_id')->toArray();

        if (!empty($listIds)) {
            $listData = (clone $contactList)->whereIn('id', $listIds)->pluck('name')->toArray();
            $lists = implode(",", $listData);
            return error(__($lists . ' list already assigned to team member '), [], 'validation');
        }

        /*check contact added or not in list */
        $listContact     = ListContact::whereIn('list_id', $request->list_id)->pluck('list_id')->toArray();

        $differenceArray = array_diff($request->list_id, $listContact);

        if (!empty($differenceArray)) {
            $listsName = (clone $contactList)->whereIn('id', $differenceArray)->pluck('name')->toArray();
            $listNameData = implode(",", $listsName);
        }

        foreach ($request->list_id  as $list_id_detail) {

            $list = (clone $contactList)->findOrFail($list_id_detail);

            if (!$list->listContact()->exists()) {
                return error(__('You cannot assign blank ' . $listNameData . ' list to team member'), [], 'validation');
            }
            foreach ($request->assigned_to as $assigned_to_user) {
                $assignedContact[] = [
                    'id'            => Str::uuid(),
                    'list_id'       => $list_id_detail,
                    'assigned_to'   => $assigned_to_user,
                    'assigned_from' => auth()->user()->id
                ];
            }
        }
        //Insert record
        $query->insert($assignedContact);
        return ok(__('List assigned successfully'));
    }

    /**
     *  Add users to list api
     *
     * @param  mixed $request
     * @return void
     */
    public function addToList(Request $request)
    {
        //Validation
        $this->validate($request, [
            'contact_id' => 'nullable|array|exists:contacts,id',
            'list_id'    => 'required'
        ]);

        $query        = CompanyContact::query();

        $listContactIds = ListContact::where('list_id', $request->list_id)->pluck('contact_id')->toArray();

        $contactIds = $request->contact_id;

        $listContactIds = array_intersect($listContactIds, $contactIds);

        if (!empty($listContactIds)) {
            $data = [];
            $contactsName = (clone $query)->select('id', 'first_name', 'last_name', 'sub_type', 'company_name')->whereIn('id', $listContactIds)->get();
            foreach ($contactsName as $value) {
                if ($value->sub_type == "C") {
                    array_push($data, $value->company_name);
                } else {
                    array_push($data, $value->full_name);
                }
            }
            $contacts = implode(",", $data);
            return error(__($contacts . ' contacts already added to this list'), [], 'validation');
        }

        $contactList = ContactList::find($request->list_id);

        if (!isset($contactList)) {
            return error(__('List not found'), [], 'validation');
        }
        $count = (clone $query)->whereIn('id', $request->contact_id)->where('company_id', $contactList->company_id)->where('sub_type', $contactList->sub_type)->count();

        if ($count != count($request->contact_id)) {
            return error(__('Can not add contact to another company or type list'), [], 'validation');
        }
        // Detach contact list
        $contactList->listContact()->detach($request->contact_id);
        // Attach new contact list
        $contactList->listContact()->attach($request->contact_id, ['type' => $contactList->type]);
        $contactsName = (clone $query)->whereIn('id', $request->contact_id)->where('company_id', auth()->user()->company_id)->get();
        $name = [];
        foreach ($contactsName as $value) {
            if ($value->sub_type == "C") {
                array_push($name, $value->company_name);
            } else {
                array_push($name, $value->full_name);
            }
        }

        $getName = implode(",", $name);
        $message = $getName . ' ' . 'has been added to the list';
        $type = 'lists';
        //sent notification for contact added in list successfully.
        sentNotification($message, $type);
        return ok(__('Contacts added in list'));
    }

    /**
     * Get contact based on list api
     *
     * @param  mixed $request
     * @return void
     */
    public function listContact(Request $request)
    {
        //Validation
        $this->validate($request, [
            'list_id'    => 'required',
            'contact_id' => 'nullable|array|exists:contacts,id'
        ]);

        $assignQuery  = AssignedContact::query();
        $contactQuery = CompanyContact::query();
        $listId = $request->list_id;
        $assignedId = (clone $assignQuery)->whereIn('assigned_to_id', [auth()->user()->id])->pluck('contact_id')->toArray();
        $role = Role::where('name', config('constants.super_admin'))->first();
        // If auth user role is super admin then he/her can see all list(self and member) otherwise see only self and assign list
        if (auth()->user()->role_id == $role->id) {
            $query = (clone $contactQuery)->with('city_details', 'country_details', 'company:id,name', 'listContact')->whereHas('listContact', function ($query) use ($listId) {
                $query->where('list_id', $listId);
            });
        } else {
            $query = (clone $contactQuery)->with('city_details', 'country_details', 'company:id,name', 'listContact')->whereHas('listContact', function ($query) use ($listId) {
                $query->where('list_id', $listId);
            })->where(function ($query) use ($assignedId) {
                $query->whereIn('id', $assignedId)
                    ->orWhere('created_by', auth()->user()->id)->orWhere('is_private', 0)->orWhere('is_private', 1)->orWhere('is_lost', 0)->orWhere('is_lost', 1);
            });
        }

        /*Search functionality */
        if ($request->search) {
            $search = $request->search;
            $query = $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orwhereHas('company', function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })->orwhereHas('city_details', function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })->orwhereHas('country_details', function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        /*For pagination and sorting filter*/
        $result = filterSortPagination($query);
        /* Get Records */
        $contacts = $result['query']->get();
        $count  = $result['count'];
        // Add assign user details
        foreach ($contacts as $contact)
        {
            $contact['assigned_to_user_details'] = (clone $assignQuery)->with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $contact->id)->get();
        }
        /*Set export functionality */
        if ($request->contact_id) {
            $listname = ContactList::where('id', $request->list_id)->first();
            return Excel::download(new ListContactsExport($query->with('company', 'city_details', 'country_details')->whereIn('id', $request->contact_id)->get(), $listname), 'ContactList.csv');
        }
        return ok(__('List of contact'), [
            'contacts' => $contacts,
            'count'    => $count,
        ]);
    }

    /**
     * move contact to another type api
     *
     * @param  mixed $request
     * @return void
     */
    public function moveList(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'list_id'   => 'required|exists:lists,id',
            'main_type' =>  'required|in:G,P,CL',//G=General,P=Prospect,CL=Client
        ]);

        $list = ContactList::findOrFail($request->list_id);
        $list->update($request->only(['main_type']));

        return ok(__('List moved successfully'), $list);
    }

    /**
     * Change list type api
     *
     * @param  mixed $request
     * @return void
     */
    public function changeTypeList(Request $request)
    {
        //Validation
        $this->validate($request, [
            'type'    => 'required|in:P,CL,C,LC',//P=Prospect, CL=Custom List, C=Client, LC=Lost Contacts
            'list_id' => 'required'
        ]);
        $contactList = ContactList::findOrFail($request->list_id);
        //Update Type
        $contactList->update($request->only('type'));
        return ok(__('List type updated successfully'), $contactList);
    }

    /**
     * Get list based on Sub type api
     *
     * @param  mixed $request
     * @return void
     */
    public function getList(Request $request)
    {
        //Validation
        $this->validate($request, [
            'main_type' => 'nullable|in:G,C,P,CL',//P=Prospect, CL=Custom List, C=Client, LC=Lost Contacts
            'sub_type'  => 'required|in:C,P'//C=Company, P=People
        ]);
        $role  = Role::where('id', auth()->user()->role_id)->first();
        $query = ContactList::query();

        if ($role->name == config('constants.super_admin')) {
            $list = (clone $query)->where('sub_type', $request->sub_type)->where('company_id', auth()->user()->company_id)->get();
        } else {
            $list = (clone $query)->where('sub_type', $request->sub_type)->WhereHas('assignedList', function ($query) {
                $query->where('owned_by', auth()->user()->id)
                    ->orwhere('assigned_to', auth()->user()->id);
            })->get();
        }
        return ok(__('List'), $list);
    }

    /**
     * Edit contact api
     *
     * @param  mixed $request
     * @return void
     */
    public function editContact(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'contact_id' => 'required|exists:contacts,id'
        ]);
        //Get Company Contact
        $contacts = CompanyContact::findOrFail($request->contact_id);
        return ok(__('Contact detail'), $contacts);
    }

    /**
     * Assing contact to another team member api
     *
     * @param  mixed $request
     * @return void
     */
    public function assignContact(Request $request)
    {
        //Validation
        $this->validate($request, [
            'contact_id'     => 'required|exists:contacts,id',
            'assigned_to_id' => 'required|exists:users,id'
        ]);

        $assingContact = [];

        $users = User::whereIn('id', $request->assigned_to_id)->where('company_id', auth()->user()->company_id)->exists();

        if (!$users) {
            return error(__('Contact can not assing to another team member'), [], 'validation');
        }

        $query = AssignedContact::query();
        $contactIds = $query->whereIn('contact_id', $request->contact_id)->whereIn('assigned_to_id', $request->assigned_to_id)->pluck('contact_id')->toArray();
        if (!empty($contactIds)) {
            $data = [];
            $contactsName = CompanyContact::select('id', 'first_name', 'last_name', 'sub_type', 'company_name')->whereIn('id', $contactIds)->get();
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
        foreach ($request->contact_id as $contact_id) {
            foreach ($request->assigned_to_id as $assigned_to_ids) {
                $assingContact[] = [
                    'id'             => Str::uuid(),
                    'contact_id'     => $contact_id,
                    'assigned_by_id' => auth()->user()->id,
                    'assigned_to_id' => $assigned_to_ids
                ];
            }
        }

        $assingContact  = $query->insert($assingContact);
        return ok(__('Contact assigned successfully'), $assingContact);
    }

    /**
     * move contact to another list api
     *
     * @param  mixed $request
     * @return void
     */
    public function moveContact(Request $request)
    {
        //Validation
        $this->validate($request, [
            'contact_id'       => 'required|array|exists:list_contacts,contact_id',
            'existing_list_id' => 'required|exists:list_contacts,list_id',
            'list_id'          => 'required|exists:lists,id'
        ]);

        $query        = ListContact::query();

        $listType     = ContactList::where('id', $request->list_id)->first();

        $contactCount = CompanyContact::whereIn('id', $request->contact_id)->where('company_id', $listType->company_id)->where('sub_type', $listType->sub_type)->count();
        $requestCount = count($request->contact_id);

        if ($contactCount != $requestCount) {
            return error(__('Please select a single contact type either People or Company to move them to the same list'), [], 'validation');
        }
        // Get contact count in list
        $count = (clone $query)->whereIn('contact_id', $request->contact_id)
            ->where('list_id', $request->list_id)
            ->where('type', $listType->type)
            ->count();
        (clone $query)->whereIn('contact_id', $request->contact_id)->where('list_id', $request->existing_list_id)->delete();
        DB::commit();
        if ($count > 0) {
            return error(__('Contact already moved'), [], 'validation');
        }
        foreach ($request->contact_id as $value) {
            $moveContact[] = [
                'contact_id' => $value,
                'list_id'    => $request->list_id,
                'type'       => $listType->type
            ];
        }
        //create list contacts
        ListContact::insert($moveContact);
        return ok(__('Contact moved successfully to the selected list'));
    }

    /**
     * Remove contact from list api
     *
     * @param  mixed $request
     * @return void
     */
    public function removeContact(Request $request)
    {
        //Validation
        $this->validate($request, [
            'contact_id' => 'required|exists:list_contacts,contact_id',
            'list_id'    => 'required|exists:list_contacts,list_id'
        ]);
        $query       = ListContact::query();

        $contactList = $query->where('contact_id', $request->contact_id)->where('list_id', $request->list_id)->exists();
        if (!$contactList) {
            return error(__('Contact not found in list'), [], 'validation');
        }
        $query->where('contact_id', $request->contact_id)->where('list_id', $request->list_id)->delete();

        return ok(__('Contact removed from list successfully'));
    }

    /**
     * To do list to contact in list-contact api
     *
     * @param  mixed $request
     * @return void
     */
    public function listToProspect() // TODO: "not use this api"
    {
        $query = ListContact::query();
        $listToProspect = (clone $query)->where('type', 'L')->pluck('list_id')->toArray();
        (clone $query)->whereIn('list_id', $listToProspect)->update(['type' => 'P']); //P=Prospect
        return ok(__('List contact updated successfully'));
    }
}
