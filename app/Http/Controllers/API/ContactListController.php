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
    /*Contact list */
    public function list(Request $request)
    {

        $this->validate($request, [
            'page'      => 'integer',
            'perPage'   => 'integer',
            'search'    => 'nullable',
            'filter'    => 'nullable',
            'is_excel'  => 'nullable|boolean',
            'main_type' => 'nullable|in:G,P,CL',
            'list_id'   => 'nullable|array|exists:lists,id'

        ]);

        $query = ContactList::query();
        $role = Role::where('id', auth()->user()->role_id)->first();
        if ($role->name == config('constants.super_admin')) {
            //$query  =  $query->where('main_type', $request->main_type)->where('company_id', auth()->user()->company_id)->with('users:id,first_name,last_name');
            $query  =  $query->where('company_id', auth()->user()->company_id)->with('users:id,first_name,last_name');
        } else {
            // $query  = $query->where('main_type', $request->main_type)->with('users:id,first_name,last_name')->WhereHas('assignedList', function ($query) {
            //     $query->where('owned_by', auth()->user()->id)
            //         ->orwhere('assigned_to', auth()->user()->id);
            // });
            $query  = $query->with('users:id,first_name,last_name')->WhereHas('assignedList', function ($query) {
                $query->where('owned_by', auth()->user()->id)
                    ->orWhere('assigned_to', auth()->user()->id);
            });
        }


        /* Search functionality */
        if ($request->search) {

            $search = $request->search;

            $query  = $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%");
                //$q->orWhere('size', 'LIKE', "$search");
                $q->orWhere('type', 'LIKE', "$search")->orWhereHas('users', function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', "%$search%");
                    $query->orWhere('last_name', 'LIKE', "%$search%");
                });
            });
        }
        /*Pagination */
        $count       = $query->count();
        if ($request->page && $request->perPage) {
            $page       = $request->page;
            $perPage    = $request->perPage;
            $query      = $query->skip($perPage * ($page - 1))->take($perPage);
        }
        $contactList = $query->orderBy('created_at', 'desc')->get();

        if ($request->list_id) {
            return Excel::download(new ContactListExport($query->whereIn('id', $request->list_id)->get()), 'ContactList.csv');
        }
        foreach ($contactList as $lists) {
            $assingedList = AssignedList::query();
            $lists['assigned_to_user_details'] = (clone $assingedList)->where('assigned_to', '!=', null)
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

    /*Contact list filter */
    public function filter(Request $request)
    {

        $this->validate($request, [
            'page'      => 'integer',
            'perPage'   => 'integer',
            'assigned_to_id'    =>  'nullable|array|exists:users,id',
            'main_type' => 'required|in:G,P,CL',
            'list_id'   => 'nullable|array|exists:lists,id',
            'type'      => 'nullable|array|in:P,CL,C,LC',
            'sub_type'  => 'nullable|in:C,P',
        ]);

        $query = ContactList::query();
        $role = Role::where('id', auth()->user()->role_id)->first();
        if ($role->name == config('constants.super_admin')) {
            $query =  $query->where('main_type', $request->main_type)->where('company_id', auth()->user()->company_id)->with('users:id,first_name,last_name');
        } else {
            $query = $query->where('main_type', $request->main_type)->with('users:id,first_name,last_name')->WhereHas('assignedList', function ($query) {
                $query->where('owned_by', auth()->user()->id)
                    ->orwhere('assigned_to', auth()->user()->id);
            });
        }

        /**Search in filter */
        if ($request->type) {
            $query = $query->whereIn('type', $request->type);
        }

        if ($request->sub_type) {
            $query = $query->where('sub_type', $request->sub_type);
        }

        if ($request->assigned_to_id) {

            $assignedtoContacts = AssignedList::whereIn('assigned_to', $request->assigned_to_id)->where('assigned_from', auth()->user()->id)->pluck('list_id')->toArray();
            $query              = $query->whereIn('id', $assignedtoContacts);
        }

        /*Pagination */
        $count       = $query->count();
        if ($request->page && $request->perPage) {
            $page       = $request->page;
            $perPage    = $request->perPage;
            $query      = $query->skip($perPage * ($page - 1))->take($perPage);
        }
        $contactList = $query->orderBy('created_at', 'desc')->get();

        if ($request->list_id) {
            return Excel::download(new ContactListExport($query->whereIn('id', $request->list_id)->get()), 'ContactList.csv');
        }
        foreach ($contactList as $lists) {
            $assingedList = AssignedList::query();
            $lists['assigned_to_user_details'] = (clone $assingedList)->where('assigned_to', '!=', null)->where('assigned_from', auth()->user()->id)->with('assigned_to_details', 'assigned_by_details')->where('list_id', $lists->id)->get();
        }
        return ok(__('Contactlist'), [
            'conatctList' => $contactList,
            'count'       => $count
        ]);
    }

    /* Contact list merge */
    public function merge(Request $request)
    {
        $this->validate($request, [
            'main_type' => 'required|in:G,P,CL',
            'type'      => 'required|in:P,CL,C,LC',
            'sub_type'  => 'nullable|in:C,P',
            'name'      => 'required|min:3|max:100|unique:lists,name,NULL,id,deleted_at,NULL',
            'list_id'   => 'required|array|exists:lists,id',
        ]);

        $request['company_id'] = auth()->user()->company_id;

        /* Get the sub_type of the first contact list */
        $firstContactList = ContactList::find($request->list_id[0]);
        $subType = $firstContactList->sub_type;

        /* Check if all list_id have the same sub_type */
        foreach ($request->list_id as $listId) {
            $contactList = ContactList::find($listId);
            if ($contactList->sub_type != $subType) {
                return error(__('Lists cannot be merged as they have different contact types. Please select lists with the same contact type'), [], 'validation');
            }
        }

        /* Retrieve all contacts for the given lists */
        $contacts = [];
        foreach ($request->list_id as $listId) {
            $listContacts = ListContact::where('list_id', $listId)->get();
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
        $contactList    = ContactList::create($request->only('company_id', 'type', 'sub_type', 'main_type', 'name'));
        $successMessage = 'Lists merged successfully!';

        /*Store record in assigned list */
        AssignedList::create([
            'list_id' => $contactList->id,
            'owned_by' => auth()->user()->id,
        ]);

        /* Store contacts in ListContact table for the new list */
        foreach ($uniqueContacts as $contact) {
            ListContact::create([
                'list_id'    => $contactList->id,
                'contact_id' => $contact['contact_id'],
                'type'       => $contact['type']
            ]);
        }

        return ok(__($successMessage), $contactList);
    }

    /* Contact list create */
    public function store(Request $request)
    {
        $this->validate($request, [
            'main_type' => 'required|in:G,P,CL',
            'type'      => 'required|in:P,CL,C,LC',
            'sub_type'  => 'nullable|in:C,P',
            'name'      => 'required|min:3|max:100|unique:lists,name,NULL,id,deleted_at,NULL',
            //'size'      => 'required|integer|max:1000',
            'contact_id' => 'nullable|array|exists:contacts,id'

        ]);
        $request['company_id'] = auth()->user()->company_id;

        /*Store record in contact list */
        $contactList    = ContactList::create($request->only('company_id', 'type', 'sub_type', 'main_type', 'name'));
        $successMessage = 'List created successfully';
        /*Store record in assigned list */
        AssignedList::create([
            'list_id' => $contactList->id,
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

            // if ($contactList->size < $contactCount) {
            //     return error(__('Can not add more contacts then list size '), [], 'validation');
            // }

            $contactList->listContact()->attach($request->contact_id, ['type' => $contactList->type]);
            $contactsName = (clone $query)->whereIn('id', $request->contact_id)->where('company_id', auth()->user()->company_id)->get();
            $name = [];
            foreach ($contactsName as $key => $value) {
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
            /*Set message based on contact type */
            if ($contact->type == "G") {
                $typeName = 'Contact';
            } else if ($contact->type == "P") {
                $typeName = 'Prospect';
            } else {
                $typeName = 'Client';
            }
            $successMessage = $typeName . '(s) add to new list successfully';
        }

        return ok(__($successMessage), $contactList);
    }

    /* Contact list update */

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'main_type' => 'required|in:G,P,CL',
            'type'      => 'required|in:P,CL,C,LC',
            'sub_type'  => 'nullable|in:C,P',
            'name'      => 'required|min:3|max:1000|unique:lists,name,' . $id . ',id,deleted_at,NULL',
            //'size'      => 'required|integer|max:1000',

        ]);

        $contactList = ContactList::find($id);

        if (!isset($contactList) && empty($contactList)) {
            return error(__('Contact list not found'), [], 'validation');
        }
        $contactList->update($request->only(['company_id', 'type', 'sub_type', 'main_type', 'name']));

        return ok(__('List updated successfully'), $contactList);
    }

    /*Contact list delete */
    public function delete(Request $request, $id = null)
    {
        $query = AssignedList::query();

        if (!isset($id)) {
            $this->validate($request, [
                'list_id' => 'nullable|array|exists:lists,id'
            ]);
            $role = Role::where('id', auth()->user()->role_id)->first();
            if ($role->name == config('constants.super_admin')) {
                $list        = ContactList::whereIn('id', $request->list_id)->delete();
                $contactList = ListContact::whereIn('list_id', $request->list_id)->delete();
                $assingList  = $query->whereIn('list_id', $request->list_id)->delete();
                return ok(__('List deleted successfully'));
            } else {
                $contactList = (clone $query)->where('owned_by', auth()->user()->id)->whereIn('list_id', $request->list_id)->pluck('list_id')->toArray();
                if (empty($contactList)) {
                    $conatctList = (clone $query)->whereIn('list_id', $request->list_id)->where('assigned_to', auth()->user()->id)->delete();
                    return ok(__('Contactlist deleted'));
                } else {

                    (clone $query)->where('owned_by', auth()->user()->id)->whereIn('list_id', $contactList)->delete();

                    return ok(__('List deleted successfully'));
                }
            }
        } else {
            $role = Role::where('id', auth()->user()->role_id)->first();
            if ($role->name == config('constants.super_admin')) {
                $listId = ContactList::findOrFail($id);
                $listId->delete();
                $listId->listContact()->detach();
                $listId->assignedList()->delete();
                return ok(__('List deleted successfully'));
            }
            /*Delete on single */ else {
                $contactList = (clone $query)->where('owned_by', auth()->user()->id)->where('list_id', $id)->first();

                if ($contactList == null) {

                    $contactList = (clone $query)->where('assigned_to', auth()->user()->id)->where('list_id', $id)->first();

                    if ($contactList == null) {
                        return error(__('Contactlist not found'));
                    }
                    $contactList->delete();
                    return ok(__('List deleted successfully'));
                } else {
                    $contactList->delete();
                    return ok(__('List deleted successfully'));
                }
            }
        }
    }

    /* Contact list assing */
    public function assign(Request $request)
    {

        $this->validate($request, [
            'list_id' => 'nullable|array|exists:lists,id',
            'assigned_to' => 'nullable|array|exists:users,id'
        ]);
        $assigned_contact = [];
        /*Check already assigned or not */
        $query = AssignedList::query();

        $contactList = ContactList::query();

        /* Check already list assinged or not */
        $listsId = $query->whereIn('list_id', $request->list_id)->whereIn('assigned_to', $request->assigned_to)->pluck('list_id')->toArray();
        //$id      = (clone $contactList)->whereIn('id', $request->list_id)->where('created_by', $request->assigned_to)->pluck('id')->toArray();
        $id = [];
        $is_set  = array_merge($listsId, $id);

        if (!empty($is_set)) {
            $listData = (clone $contactList)->whereIn('id', $is_set)->pluck('name')->toArray();
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
                return error(__($listNameData . ' cannot assign empty list'), [], 'validation');
            }
            foreach ($request->assigned_to as $assigned_to_user) {
                $assigned_contact[] = [
                    'id'            => Str::uuid(),
                    'list_id'       => $list_id_detail,
                    'assigned_to'   => $assigned_to_user,
                    'assigned_from' => auth()->user()->id
                ];
            }
        }
        $assigned_contact_create = $query->insert($assigned_contact);
        return ok(__('List assigned successfully'));
    }

    /* Add users to list  */
    public function addToList(Request $request)
    {

        $this->validate($request, [
            'contact_id' => 'nullable|array|exists:contacts,id',
            'list_id' => 'required'
        ]);

        $query             = CompanyContact::query();

        $listContacts      = ListContact::where('list_id', $request->list_id)->pluck('contact_id')->toArray();

        $contact_id_detail = $request->contact_id;

        $result = array_intersect($listContacts, $contact_id_detail);

        if (!empty($result)) {
            $data = [];
            $contactsName = (clone $query)->select('id', 'first_name', 'last_name', 'sub_type', 'company_name')->whereIn('id', $result)->get();
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

        $contactCount = count($request->contact_id);

        if ($count != $contactCount) {
            return error(__('Can not add contact to another company or type list'), [], 'validation');
        }
        /* hide contact size functionality */
        // $requestCount            = count($request->contact_id);

        // $conatctListCount        = $contactList->listContact()->count();

        // $total                   = $requestCount + $conatctListCount;

        // if ($total > $contactList->size) {
        //     return error(__('Contact list size limit finished, please remove some records'), [], 'validation');
        // }
        $contactList->listContact()->detach($request->contact_id);
        $contactList->listContact()->attach($request->contact_id, ['type' => $contactList->type]);
        $contactsName = (clone $query)->whereIn('id', $request->contact_id)->where('company_id', auth()->user()->company_id)->get();
        $name = [];
        foreach ($contactsName as $key => $value) {
            if ($value->sub_type == "C") {
                array_push($name, $value->company_name);
            } else {
                array_push($name, $value->full_name);
            }
        }

        $getName = implode(",", $name);
        $message = $getName . ' ' . 'has been added to the list';
        $type = 'lists';
        sentNotification($message, $type);
        return ok(__('Contacts added in list'));
    }
    /*Get contact based on list */
    public function listContact(Request $request)
    {
        $this->validate($request, [
            'list_id'   => 'required',
            'contact_id' => 'nullable|array|exists:contacts,id'
        ]);

        $listId = $request->list_id;
        $assignedId = AssignedContact::whereIn('assigned_to_id', [auth()->user()->id])->pluck('contact_id')->toArray();
        $role = Role::where('name', config('constants.super_admin'))->first();
        if (auth()->user()->role_id == $role->id) {
            $query = CompanyContact::with('city_details', 'country_details', 'company:id,name', 'listContact')->whereHas('listContact', function ($query) use ($listId) {
                $query->where('list_id', $listId);
            });
        } else {
            $query = CompanyContact::with('city_details', 'country_details', 'company:id,name', 'listContact')->whereHas('listContact', function ($query) use ($listId) {
                $query->where('list_id', $listId);
            })->where(function ($query) use ($assignedId) {
                $query->whereIn('id', $assignedId)
                    //->orWhere('created_by', auth()->user()->id)->orWhere('section', 'A');
                    ->orWhere('created_by', auth()->user()->id)->orWhere('is_private', 0)->orWhere('is_private', 1)->orWhere('is_lost', 0)->orWhere('is_lost', 1);
            });
        }

        /*Search functionality */
        if ($request->search) {
            $search = $request->search;
            $query = $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orwhereHas('company', function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%$search%");
                    })->orwhereHas('city_details', function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%$search%");
                    })->orwhereHas('country_details', function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%$search%");
                    });
            });
        }
        /*Set Pagination */
        $count = $query->count();
        if ($request->page && $request->perPage) {
            $page       = $request->page;
            $perPage    = $request->perPage;
            $query      = $query->skip($perPage * ($page - 1))->take($perPage);
        }
        /* Get Records */
        $contacts = $query->orderBy('created_at', 'DESC')->get();
        // Add assign user details
        foreach ($contacts as $contact)
        {
            $contact['assigned_to_user_details'] = AssignedContact::with('assigned_to_details')->with('assigned_by_details')->where('contact_id', $contact->id)->get();
        }
        /*Set export functionality */
        if ($request->contact_id) {
            $listname = ContactList::where('id', $request->list_id)->first();
            return Excel::download(new ListContactsExport($query->with('company', 'city_details', 'country_details')->whereIn('id', $request->contact_id)->get(), $listname), 'ContactList.csv');
        }
        return ok(__('List of contact'), [
            'contacts' => $contacts,
            'count'   => $count,
        ]);
    }

    /*move contact to another type */
    public function moveList(Request $request)
    {
        $this->validate($request, [
            'list_id'   => 'required|exists:lists,id',
            'main_type' =>  'required|in:G,P,CL',
        ]);

        $list = ContactList::findOrFail($request->list_id);
        $list->update($request->only(['main_type']));

        return ok(__('List moved successfully'), $list);
    }
    /* Change list type  */
    public function changeTypeList(Request $request)
    {
        $this->validate($request, [
            'type'    => 'required|in:P,CL,C,LC',
            'list_id' => 'required'
        ]);
        $contactList = ContactList::findOrFail($request->list_id);
        $contactList->update($request->only('type'));
        return ok(__('List type updated successfully'), $contactList);
    }
    /* Get list based on Sub type */
    public function getList(Request $request)
    {
        $this->validate($request, [
            'main_type' => 'nullable|in:G,C,P,CL',
            'sub_type' => 'required|in:C,P'
        ]);
        $role = Role::where('id', auth()->user()->role_id)->first();
        $query = ContactList::query();

        if ($role->name == config('constants.super_admin')) {
            //$list = (clone $query)->where('main_type', $request->main_type)->where('sub_type', $request->sub_type)->where('company_id', auth()->user()->company_id)->get();
            $list = (clone $query)->where('sub_type', $request->sub_type)->where('company_id', auth()->user()->company_id)->get();
        } else {
            // $list = (clone $query)->where('main_type', $request->main_type)->where('sub_type', $request->sub_type)->WhereHas('assignedList', function ($query) {
            //     $query->where('owned_by', auth()->user()->id)
            //         ->orwhere('assigned_to', auth()->user()->id);
            // })->get();
            $list = (clone $query)->where('sub_type', $request->sub_type)->WhereHas('assignedList', function ($query) {
                $query->where('owned_by', auth()->user()->id)
                    ->orwhere('assigned_to', auth()->user()->id);
            })->get();
        }
        return ok(__('List'), $list);
    }
    /* Edit contact */
    public function editContact(Request $request)
    {
        $this->validate($request, [
            'contact_id' => 'required|exists:contacts,id'
        ]);
        $contacts = CompanyContact::findOrFail($request->contact_id);
        return ok(__('Contact detail'), $contacts);
    }
    /*Assing contact to another team member */
    public function assignContact(Request $request)
    {

        $this->validate($request, [
            'contact_id' => 'required|exists:contacts,id',
            'assigned_to_id' => 'required|exists:users,id'
        ]);

        $assingContact = [];

        $users = User::whereIn('id', $request->assigned_to_id)->where('company_id', auth()->user()->company_id)->exists();

        if (!$users) {
            return error(__('Contact can not assing to another team member'), [], 'validation');
        }

        $query = AssignedContact::query();
        $contactId = $query->whereIn('contact_id', $request->contact_id)->whereIn('assigned_to_id', $request->assigned_to_id)->pluck('contact_id')->toArray();
        //$id        = CompanyContact::whereIn('id', $request->contact_id)->whereIn('created_by', $request->assigned_to_id)->pluck('id')->toArray();
        $isSet     = array_merge($contactId);
        if (!empty($isSet)) {
            $data = [];
            $contactsName = CompanyContact::select('id', 'first_name', 'last_name', 'sub_type', 'company_name')->whereIn('id', $isSet)->get();
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
        foreach ($request->contact_id as $key => $value) {
            foreach ($request->assigned_to_id as $assigned_to_ids) {
                $assingContact[] = [
                    'id'            => Str::uuid(),
                    'contact_id'    => $value,
                    'assigned_by_id' => auth()->user()->id,
                    'assigned_to_id' => $assigned_to_ids
                ];
            }
        }

        $assingContact  = $query->insert($assingContact);
        //$companyContact = CompanyContact::whereIn('id',$request->contact_id)->update(['section'=>'A']);
        return ok(__('Contact assigned successfully'), $assingContact);
    }
    /* move contact to another list */
    public function moveContact(Request $request)
    {

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
        $count = (clone $query)->whereIn('contact_id', $request->contact_id)
            ->where('list_id', $request->list_id)
            ->where('type', $listType->type)
            ->count();
        (clone $query)->whereIn('contact_id', $request->contact_id)->where('list_id', $request->existing_list_id)->delete();
        DB::commit();
        if ($count > 0) {
            return error(__('Contact already moved'), [], 'validation');
        }
        foreach ($request->contact_id as $key => $value) {
            $moveContact[] = [
                'contact_id' => $value,
                'list_id'   => $request->list_id,
                'type'      => $listType->type
            ];
        }
        ListContact::insert($moveContact);
        return ok(__('Contact moved successfully to the selected list'));
    }
    /* Remove contact from list */

    public function removeContact(Request $request)
    {

        $this->validate($request, [
            'contact_id' => 'required|exists:list_contacts,contact_id',
            'list_id'   => 'required|exists:list_contacts,list_id'
        ]);
        $query       = ListContact::query();

        $contactList = $query->where('contact_id', $request->contact_id)->where('list_id', $request->list_id)->exists();
        if (!$contactList) {
            return error(__('Contact not found in list'), [], 'validation');
        }
        $contactList = $query->where('contact_id', $request->contact_id)->where('list_id', $request->list_id)->delete();

        return ok(__('Contact removed from list successfully'));
    }
    /* To do list to contact in list-contact api */
    public function listToProspect()
    {

        $query = ListContact::query();
        $listToProspect = (clone $query)->where('type', 'L')->pluck('list_id')->toArray();
        (clone $query)->whereIn('list_id', $listToProspect)->update(['type' => 'P']);
        return ok(__('List contact updated successfully'));
    }
}
