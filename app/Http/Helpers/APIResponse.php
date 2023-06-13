<?php
use App\Models\AssignedContact;
use App\Models\AssignedList;
use App\Models\CompanyContact;
use App\Models\CompanyPeople;
use App\Models\ContactList;
use App\Models\GlobalFile;
use App\Models\Protocol;
use App\Models\Folder;
use App\Models\Note;
use Illuminate\Support\Facades\Storage;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Api response helper file
 * This helper needs to register in composer.json file under the "autoload-dev > files" section
 * "files": [ "app/Http/Helpers/APIResponse.php" ]
 */

/**
 * Return success response
 *
 * @param int    $status
 * @param string $message
 * @param array  $data
 */
if(!function_exists('ok')){
    function ok($message = null, $data = [], $status = 200)
    {
        $response = [
            'status'    =>  $status,
            'message'   =>  $message ?? 'Process is successfully completed',
            'data'      =>  $data
        ];

        return response()->json($response,$status);
    }
}

/**
 * Return all type of error response with different status code
 *
 * @param string $message
 * @param array  $data
 * @param string $type = validation | unauthenticated | notfound | forbidden | internal server
 */
if(!function_exists('error')){
    function error($message = null, $data = [], $type = null)
    {
        $status = 500;

        switch ($type) {
            case 'validation':
                $status  = 422;
                $message ?? 'Validation Failed please check the request attributes and try again';
                break;

            case 'unauthenticated':
                $status  = 401;
                $message ?? 'User token has been expired';
                break;

            case 'notfound':
                $status  = 404;
                $message ?? 'Sorry no results query for your request';
                break;

            case 'forbidden':
                $status  = 403;
                $message ??  'You don\'t have permission to access this content';
                break;

            default:
                $status = 500;
                $message ?? $message = 'Server error, please try again later';
                break;
        }

        $response = [
            'status'    =>  $status,
            'message'   =>  $message,
            'data'      =>  $data
        ];

        return response()->json($response,$status);
    }
}

/**
 * Return message prefix
 *
 * @param string $type
 */
if(!function_exists('prefix')){
    function prefix($type)
    {
        switch ($type) {
            case 'G':
                return 'Contact(s)';
                break;
            case 'P':
                return 'Prospect(s)';
                break;
            case 'CL':
                return 'Client(s)';
                break;
            default:
                return 'Contact(s)';
                break;
        }
    }
}

/**
 * Return contact slag url
 *
 * @param string $url
 */
if(!function_exists('contactSlugUrl')){
    function contactSlugUrl($type, $slug)
    {
        switch ($type) {
            case 'G':
                return url(config('constants.CONTACT_SLAG_SERVER_URL')) . $slug;
                break;
            case 'P':
                return url(config('constants.PROSPECT_SLAG_SERVER_URL')) . $slug;
                break;
            case 'CL':
                return url(config('constants.CLIENT_SLAG_SERVER_URL')) . $slug;
                break;
            default:
                return url(config('constants.CONTACT_SLAG_SERVER_URL')) . $slug;
                break;
        }
    }
}

/**
 * Common function to filter, sort and paginate list items
 *
 */
if(!function_exists('filterSortPagination'))
{
    function filterSortPagination($query)
    {
        $count = $query->count();

        /* Sort records */
        if (request()->sort_field) {
            $query->orderBy(request()->sort_field, request()->sort_order);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        /* Pagination */
        if (request()->page && request()->perPage) {
            $page       = request()->page;
            $per_page   = request()->perPage;
            if ($per_page == '-1' || $per_page == 'All') {
                $per_page = $count;
            }
            $query = $query->skip($per_page * ($page - 1))->take($per_page);
        }

        //total_timesheet is not use for all API it only use for TimeSheetReport API
        return ['query' => $query, 'count' => $count];
    }
}

/**
 * add protocalse
 *
 */
if(!function_exists('addProtocol'))
{
    function addProtocol($contact_id, $category, $message)
    {
        $protocol = [];
        $protocol['contact_id'] = $contact_id;
        $protocol['category']   = $category;
        $protocol['message']    = $message;
        if ($category == 'profile') {
            $protocol['icon'] = 'Contacts.svg';
        } elseif ($category == 'files') {
            $protocol['icon'] = 'pin.svg';
        } elseif ($category == 'notes') {
            $protocol['icon'] = 'Notes.svg';
        } else {
            $protocol['icon'] = 'pin.svg';
        }

        Protocol::create($protocol);

        return $message;
    }
}

/**
 * throw Errors
 *
 */
if(!function_exists('throwError'))
{
    function throwError($message)
    {
        throw ValidationException::withMessages([
            'message' => [$message]
        ]);
    }
}

/**
 * upload file
 *
 */
if (!function_exists('uploadFile')) {
    function uploadFile($attachment, $directory)
    {
        $originalName = $attachment->getClientOriginalName();
        $extension    = $attachment->getClientOriginalExtension();
        $fileName = pathinfo($originalName, PATHINFO_FILENAME);
        $path = $directory . '/' . $fileName . '-' . mt_rand(1000000000, time()) . '.' . $extension;

        Storage::disk('s3')->put($path, fopen($attachment, 'r+'), 'public');
        return Storage::disk('s3')->url($path);
    }
}

/**
 * delete file
 *
 */
if (!function_exists('deleteFile')) {
    function deleteFile($url)
    {
        $path = parse_url($url)['path'];
        //If file exist in s3 bucket then remove
        if ($path && Storage::disk('s3')->exists($path)) {
            Storage::disk('s3')->delete($path);
        }
    }
}

/**
 * Store notification in table
 *
 */
if (!function_exists('sentNotification')) {
    function sentNotification($message, $type)
    {
        $role = Role::where('name', config('constants.super_admin'))->first();
        $user = User::where('company_id', auth()->user()->company_id)->where('id', '!=', auth()->user()->id)->where('role_id', $role->id)->first();
        if ($user) {
            if ($type == 'G') {
                $icon = 'Contacts.svg';
            } elseif ($type == 'P') {
                $icon = 'Prospects.svg';
            } elseif ($type == 'CL') {
                $icon = 'Clients.svg';
            } elseif ($type == 'lists') {
                $icon = 'list.svg';
            } elseif ($type == 'note') {
                $icon = 'Notes.svg';
            } elseif ($type == 'file') {
                $icon = 'pin.svg';
            } else {
                $icon = 'team.svg';
            }
            $notification = [
                'sender_id' => auth()->user()->id,
                'receiver_id' => $user->id,
                'title'      => $message,
                'icon'       => $icon,
            ];
            SystemNotification::create($notification);
        }
    }
}

/**
 * Store multiple notification in table
 *
 */
if (!function_exists('sentMultipleNotification')) {
    function sentMultipleNotification($type, $sub_type, $count = null)
    {
        $users = User::where('company_id', auth()->user()->company_id)->where('id', '!=', auth()->user()->id)->pluck('id')->toArray();

        if ($users) {
            if ($type == 'G') {
                $type = 'contact';
                $icon = 'Contacts.svg';
            } elseif ($type == 'P') {
                $type = 'prospect';
                $icon = 'Prospects.svg';
            } elseif ($type == 'CL') {
                $type = 'client';
                $icon = 'Clients.svg';
            }
            if ($sub_type == 'C') {
                $sub_type = 'company';
            } elseif ($sub_type == 'P') {
                $sub_type = 'people';
            }
            if ($count) {
                $message = $count . ' ' . $sub_type . ' ' . $type . 's are added';
            } else {
                $message = 'one new ' . $sub_type . ' ' . $type . ' is added';
            }
            foreach ($users as $user) {
                $notification[] = [
                    'id'          => Str::uuid(),
                    'title'       => $message,
                    'sender_id'   => auth()->user()->id,
                    'receiver_id' => $user,
                    'icon'        => $icon,
                    'created_at'  => now()
                ];
            }
            SystemNotification::insert($notification);
        }
    }
}

/**
 * Get User profile percentage
 *
 */
if (!function_exists('profilePercentage')) {
    function profilePercentage()
    {
        $auth = auth()->user();

        /*Count profile percentage*/
        $profile_percentage = config('constants.profile_percentage') / config('constants.profile_column');
        $percentage  = $auth->first_name ? $profile_percentage : 0;
        $percentage += $auth->company ? $profile_percentage : 0;
        $percentage += $auth->position ? $profile_percentage : 0;
        $percentage += $auth->email ? $profile_percentage : 0;

        if ($auth->userDetail) {
            $percentage += $auth->userDetail->point_of_contact ? $profile_percentage : 0;
            $percentage += $auth->userDetail->phone_number ? $profile_percentage : 0;
            $percentage += $auth->userDetail->expertises ? $profile_percentage : 0;
            $percentage += $auth->userDetail->interests ? $profile_percentage : 0;
        }

        if ($auth->location) {
            $percentage += $auth->location->address ? $profile_percentage : 0;
            $percentage += $auth->location->city ? $profile_percentage : 0;
            $percentage += $auth->location->country_code ? $profile_percentage : 0;
        }

        /*Count company percentage*/
        $company_percentage = config('constants.company_percentage') / config('constants.company_column');
        if ($auth->company) {
            $percentage += $auth->company->name ? $company_percentage : 0;
            $percentage += $auth->company->email ? $company_percentage : 0;
            $percentage += $auth->company->phone ? $company_percentage : 0;
            $percentage += $auth->company->website ? $company_percentage : 0;
            $percentage += $auth->company->services ? $company_percentage : 0;
            $percentage += $auth->company->expertises ? $company_percentage : 0;
            $percentage += $auth->company->regions ? $company_percentage : 0;

            if ($auth->company->offices->count() > 0) {
                $percentage += $auth->company->offices[0]->type ? $company_percentage : 0;
                $percentage += $auth->company->offices[0]->address ? $company_percentage : 0;
                $percentage += $auth->company->offices[0]->country_code ? $company_percentage : 0;
                $percentage += $auth->company->offices[0]->city_id ? $company_percentage : 0;
            }
        }
        /*Count upload contact percentage */
        if ($auth->companyContacts()->count() > 0) {
            $percentage += config('constants.contact_percentage');
        }
        return $percentage;
    }
}

/**
 * Sent notification when profile incomplete
 *
 */
if (!function_exists('sentUserNotification')) {
    function sentUserNotification()
    {
        $percentage = profilePercentage();
        if ($percentage < config('constants.max_percentage')) {
            $notification = [
                'sender_id' => auth()->user()->id,
                'receiver_id' => auth()->user()->id,
                'title' => "Your profile is {$percentage} % completed, please complete your profile by uploading/adding the contact and the profile details",
                'icon'  => 'profile.svg',
            ];
            SystemNotification::create($notification);
        }
    }
}

/**
 * transferOwnership
 *
 */
if (!function_exists('transferOwnership')) {
    function transferOwnership($id, $assign = 0, $newuser = null)
    {
        $companyContact = CompanyContact::query();
        $globalFile     = GlobalFile::query();
        $protocol       = Protocol::query();
        $contactList    = ContactList::query();
        $folder         = Folder::query();
        $note           = Note::query();
        if ($assign == 0) {
            /* Transfer all functionality rights to super admin when delete team member */
            $role              = Role::where('name', config('constants.super_admin'))->first();
            $superAdmin        = User::where('company_id', auth()->user()->company_id)->where('role_id', $role->id)->first();
            if ($superAdmin) {
                $newuser = $superAdmin->id;
            }
        }
        if ($id && $newuser) {
            /* Transfer all assign contact */
            transferAssignContact($id, $newuser);
            //Transfer all Assign list
            transferAssignContactList($id, $newuser);
            // contact create and updated id change
            (clone $companyContact)->where('created_by', $id)->update(['created_by' => $newuser]);
            (clone $companyContact)->where('updated_by', $id)->update(['updated_by' => $newuser]);
            // file create and updated id change
            (clone $globalFile)->where('created_by', $id)->update(['created_by' => $newuser]);
            (clone $globalFile)->where('updated_by', $id)->update(['updated_by' => $newuser]);
            // contact list create id change
            (clone $contactList)->whereIn('created_by', [$id])->update(['created_by' => $newuser]);
            // protocol create and assign by and assign to id change
            (clone $protocol)->whereIn('assigned_to_id', [$id])->update(['assigned_to_id' => $newuser]);
            (clone $protocol)->whereIn('assigned_by_id', [$id])->update(['assigned_by_id' => $newuser]);
            (clone $protocol)->whereIn('created_by', [$id])->update(['created_by' => $newuser]);
            // folder create id change
            (clone $folder)->whereIn('created_by', [$id])->update(['created_by' => $newuser]);
            // note create id change
            (clone $note)->whereIn('created_by', [$id])->update(['created_by' => $newuser]);
        }
    }
}

/**
 * transfer Assign contacts to selected user.
 *
 */
if (!function_exists('transferAssignContact')) {
    function transferAssignContact($id, $newUserId)
    {
        $assignContact  = AssignedContact::query();
        // Get all assign contacts collection
        $assiendContacts = (clone $assignContact)->where('assigned_to_id', $id)->orWhere('assigned_by_id', $id)->orWhere('created_by', $id)->orWhere('updated_by', $id)->get();
        foreach($assiendContacts as $assiendContact) {
            $existAssignRecord = (clone $assignContact)->where('contact_id', $assiendContact->contact_id)->where('assigned_to_id', $newUserId)->first();
            /* If assigned record not exist then update new user ids otherwise delete assign record */
            if (!$existAssignRecord) {
                if ($id == $assiendContact->assigned_to_id) {
                    $assiendContact->update(['assigned_to_id' => $newUserId]);
                }
                if ($id == $assiendContact->assigned_by_id) {
                    $assiendContact->update(['assigned_by_id' => $newUserId]);
                }
                if ($id == $assiendContact->created_by) {
                    $assiendContact->update(['created_by' => $newUserId]);
                }
                if ($id == $assiendContact->updated_by) {
                    $assiendContact->update(['updated_by' => $newUserId]);
                }
            } else {
                $assiendContact->delete();
            }
        }
    }
}

/**
 * transfer assign contact lists to selected user.
 *
 */
if (!function_exists('transferAssignContactList')) {
    function transferAssignContactList($id, $newUserId)
    {
        $assignedList   = AssignedList::query();

        //Get Assign list collecction
        $assiendContactLists = (clone $assignedList)->where('assigned_to', $id)->orWhere('assigned_from', $id)->orWhere('owned_by', $id)->get();
        foreach($assiendContactLists as $assiendContactList) {
            $existAssignListRecord = (clone $assignedList)->where('list_id', $assiendContactList->list_id)->where('assigned_to', $newUserId)->first();
            /* If assigned List record not exist then update new user ids otherwise delete assign record */
            if (!$existAssignListRecord) {
                if ($id == $assiendContactList->assigned_to) {
                    $assiendContactList->update(['assigned_to' => $newUserId]);
                }
                if ($id == $assiendContactList->assigned_from) {
                    $assiendContactList->update(['assigned_from' => $newUserId]);
                }
                if ($id == $assiendContactList->owned_by) {
                    $assiendContactList->update(['owned_by' => $newUserId]);
                }
            } else {
                $assiendContactList->delete();
            }
        }


    }
}

/**
 * Sent nenrichment notifications
 *
 */
if (!function_exists('sentEnrichContactNotification')) {
    function sentEnrichContactNotification($message)
    {
        $notification = [
            'sender_id'   => auth()->user()->id,
            'receiver_id' => auth()->user()->id,
            'title'       => $message,
            'icon'        => 'profile.svg',
        ];
        SystemNotification::create($notification);
    }
}

/**
 * Return contact profile report
 *
 * @param string $type
 */
if(!function_exists('contactProfileReport')){
    function contactProfileReport($contactIds, $type)
    {
        $companyContact         = CompanyContact::where('sub_type', $type)->whereIn('id',$contactIds);// C = Company
        $contactCount           = (clone $companyContact)->count();
        $duplicatePhoneNumber   = (clone $companyContact)->groupBy('phone_number')->having(DB::raw('count(phone_number)'), '>', 1)->pluck('phone_number')->toArray();
        if ($type == 'C') {
            $uniqueRows             = (clone $companyContact)->WhereNotIn('phone_number',  $duplicatePhoneNumber)->count();
            $phoneNumbers           = (clone $companyContact)->whereIn('phone_number',$duplicatePhoneNumber)->pluck('phone_number')->toArray();
            $duplicateRows          = collect( $phoneNumbers)->duplicates()->count();
        } else {
            $duplicateName      = (clone $companyContact)->groupBy('first_name')->having(DB::raw('count(first_name)'), '>', 1)->pluck('first_name')->toArray();
            $uniqueRows         = (clone $companyContact)->whereNotIn('first_name', $duplicateName)->WhereNotIn('phone_number', $duplicatePhoneNumber)->count();
            $contacts           = (clone $companyContact)->get()->map->only('first_name', 'phone_number');
            $duplicateRows      =  $contacts->duplicates('first_name','phone_number')->count();
        }

        $phoneNumbers           = (clone $companyContact)->whereIn('phone_number', $duplicatePhoneNumber)->pluck('phone_number')->toArray();
        $duplicateRows          = collect($phoneNumbers)->duplicates()->count();
        //Get profile completness percentage
        $completeData = contactProfileCompletePercentage($contactIds, $contactCount, $type);
        // Get Incomplete row count
        $incompleteData = contactInCompleteCount($contactIds, $type);

        return [
            'count'          => $contactCount ?? 0,
            'unique_rows'    => $uniqueRows ?? 0,
            'duplicate_rows' => $duplicateRows ?? 0,
            'complete_data'  => $completeData ?? 0,
            'incomplete_data'=> $incompleteData ?? 0,
            'contact_ids'    => $contactIds ?? [],
        ];
    }
}

/**
 * Return contact profile completness precentage
 *
 * @param string $incompleteData
 */
if(!function_exists('contactProfileCompletePercentage')){
    function contactProfileCompletePercentage($contactIds, $contactCount, $type)
    {
        $companyContact = CompanyContact::where('sub_type', $type)->whereIn('id', $contactIds);// C = Company
        //Get email,phonenumber,country,city,profile picture,industry count
        $emailCount             = (clone $companyContact)->whereNotNull('email')->count();
        $phoneNumber            = (clone $companyContact)->whereNotNull('phone_number')->count();
        $country                = (clone $companyContact)->whereNotNull('country_code')->count();
        $city                   = (clone $companyContact)->whereNotNull('city_id')->count();
        $profilePicture         = (clone $companyContact)->whereNotNull('profile_picture')->count();
        $industry               = (clone $companyContact)->whereNotNull('industry')->count();
        $contactCount = !empty($contactCount) ? $contactCount : 1;
        if ($type == 'C') {
            // Get category and company name count
            $category                 = (clone $companyContact)->whereNotNull('category')->count();
            $nameCount                = (clone $companyContact)->whereNotNull('company_name')->count();

            $namePercentage           = $nameCount / $contactCount * config('constants.company_name_percentahge');
            $emailPercentage          = $emailCount / $contactCount * config('constants.company_email_percentage');
            $phonePercentage          = $phoneNumber / $contactCount * config('constants.company_phone_percentage');
            $countryPercentage        = $country / $contactCount * config('constants.country_percentage');
            $cityPercentage           = $city / $contactCount * config('constants.company_city_percentage');
            $categoryPercentage       = $category / $contactCount * config('constants.category_percentage');
            $profilePicturePercentage = $profilePicture /  $contactCount * config('constants.profile_picture');
            $industryPercentage       = $industry / $contactCount * config('constants.industry');
            //calculate company profile complete percentage
            $completeData             = $namePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $cityPercentage + $categoryPercentage + $profilePicturePercentage + $industryPercentage;
        } else {
            //Get firstname,lastname,role,point of contact count
            $firstNameCount           = (clone $companyContact)->whereNotNull('first_name')->count();
            $lastNameCount            = (clone $companyContact)->whereNotNull('last_name')->count();
            $role                     = (clone $companyContact)->whereNotNull('role')->count();
            $pointOfContact           = (clone $companyContact)->whereNotNull('point_of_contact')->count();

            $firstNamePercentage     = $firstNameCount / $contactCount * config('constants.first_name_percentage');
            $lastNamePercentage      = $lastNameCount / $contactCount * config('constants.last_name_pecentage');
            $emailPercentage         = $emailCount / $contactCount * config('constants.people_email_percentage');
            $phonePercentage         = $phoneNumber / $contactCount * config('constants.people_phone_percentage');
            $countryPercentage       = $country / $contactCount * config('constants.people_country_percentage');
            $cityPercentage          = $city / $contactCount * config('constants.people_city_percentage');
            $profilePicturePercentage= $profilePicture /  $contactCount * config('constants.profile_picture');
            $industryPercentage      = $industry /  $contactCount * config('constants.industry');
            $rolePercentage          = $role / $contactCount * config('constants.role');
            $pointOfContactPercentage= $pointOfContact / $contactCount * config('constants.point_of_contact');
            //calculate people profile complete percentage
            $completeData            = $firstNamePercentage + $lastNamePercentage + $emailPercentage + $phonePercentage + $countryPercentage + $cityPercentage + $profilePicturePercentage + $industryPercentage + $rolePercentage + $pointOfContactPercentage;
        }
        return $completeData ?? 0;
    }
}

/**
 * Return contact profile in complete row count
 *
 * @param string $incompleteData
 */
if(!function_exists('contactInCompleteCount')){
    function contactInCompleteCount($contactIds, $type)
    {
        $companyContact = CompanyContact::where('sub_type', $type)->whereIn('id', $contactIds);// C=Company, P=People

        if ($type == 'C') {
           // Incomplete row count
            $incompleteData = (clone $companyContact)
                            ->where(function ($query) {
                                $query->whereNull('company_name')
                                ->orWhereNull('email')
                                ->orWhereNull('phone_number')
                                ->orWhereNull('country_code')
                                ->orWhereNull('city_id')
                                ->orWhereNull('industry')
                                ->orWhereNull('profile_picture')
                                ->orWhereNull('category');
                            })->count();
        } else {
            // Incomplete row count
            $incompleteData = (clone $companyContact)
                            ->where(function ($query) {
                                $query->whereNull('first_name')
                                ->orWhereNull('last_name')
                                ->orWhereNull('email')
                                ->orWhereNull('phone_number')
                                ->orWhereNull('country_code')
                                ->orWhereNull('city_id')
                                ->orWhereNull('role')
                                ->orWhereNull('industry')
                                ->orWhereNull('profile_picture')
                                ->orWhereNull('point_of_contact');
                            })->count();
        }
        return $incompleteData ?? 0;
    }
}

/**
 * People map with company and company map with people
 *
 * @param bool true
 */
if(!function_exists('contactCompanyPeopleMap')){
    function contactCompanyPeopleMap($contact)
    {
        $companyContact = CompanyContact::query();
        $companyPeople = CompanyPeople::query();
        $domain = substr($contact->email, strpos($contact->email, '@') + 1);
        if ($domain != 'hotmail.com' && $domain != 'yahoo.com' && $domain != 'gmail.com' && $domain != 'icloud.com' && $domain != 'outlook.com') {
            if ($contact->sub_type == 'P') {
                $existCompany = (clone $companyContact)->where('email', 'like', "%@{$domain}")->where('sub_type', 'C')->where('company_id', $contact->company_id)->first();
                if ($existCompany) {
                    $companyPeople->create(['company_id' => $existCompany->id, 'people_id' => $contact->id]);
                    $contact->update(['company_name' => $existCompany->company_name]);
                }
            }
            // If contact type company then getting same domain people with mapping logic
            if($contact->sub_type == 'C') {
                $existPeopleIds = (clone $companyContact)->where('email', 'like', "%@{$domain}")->where('sub_type', 'P')->where('company_id', $contact->company_id)->pluck('id')->toArray();
                if ($existPeopleIds)
                {
                    foreach ($existPeopleIds as $peopleId)
                    {
                        $companyPeopleMap[] = [
                            'people_id'  => $peopleId,
                            'company_id' => $contact->id
                        ];
                    }
                    $companyPeople->insert($companyPeopleMap);
                    $companyContact->whereIn('id', $existPeopleIds)->update(['company_name' => $contact->company_name]);
                }
            }
        }
    }
}

/**
 * People Import File Validation check
 *
 * @param string true
 */
if(!function_exists('peopleImportValidation')){
    function peopleImportValidation($rows)
    {
        $errorMessages = "";
        // Validation
        $validator = Validator::make($rows->toArray(), [
            '*.email'            => 'required|email',
            '*.phone_number'     => 'required',
            '*.role'             => 'required',
            '*.point_of_contact' => 'required',
            '*.country'          => 'required|exists:countries,name',
            '*.city'             => 'required|exists:cities,name',
            '*.first_name'       => 'required',
            '*.last_name'        => 'required',
        ],[
            '*.email.required'            => 'Please verify that your file title is correct and all required fields are filled.',
            '*.email.email'               => 'Please add valid email address.',
            '*.phone_number.required'     => 'Please verify that your file title is correct and all required fields are filled.',
            '*.role.required'             => 'Please verify that your file title is correct and all required fields are filled.',
            '*.point_of_contact.required' => 'Please verify that your file title is correct and all required fields are filled.',
            '*.country.required'          => 'Please verify that your file title is correct and all required fields are filled.',
            '*.city.required'             => 'Please verify that your file title is correct and all required fields are filled.',
            '*.first_name.required'       => 'Please verify that your file title is correct and all required fields are filled.',
            '*.last_name.required'        => 'Please verify that your file title is correct and all required fields are filled.',
        ]);

        if ($validator->fails()) {
            $errorMessages .= $validator->errors()->first('*.email');
            $errorMessages .= $validator->errors()->first('*.phone_number');
            $errorMessages .= $validator->errors()->first('*.role');
            $errorMessages .= $validator->errors()->first('*.point_of_contact');
            $errorMessages .= $validator->errors()->first('*.country');
            $errorMessages .= $validator->errors()->first('*.city');
            $errorMessages .= $validator->errors()->first('*.first_name');
            $errorMessages .= $validator->errors()->first('*.last_name');
            //Throw error message
            throwError($errorMessages);
        }

        return true;
    }
}

/**
 * Company Import File Validation check
 *
 * @param string true
 */
if(!function_exists('companyImportValidation')){
    function companyImportValidation($rows)
    {
        $errorMessages = "";
        // Validation
        $validator = Validator::make($rows->toArray(), [
            '*.company_name'     => 'required',
            '*.email'            => 'required|email',
            '*.phone_number'     => 'required',
            '*.category'         => 'required|in:Holder,Representative',
            '*.country'          => 'required|exists:countries,name',
            '*.city'             => 'required|exists:cities,name',
        ],[
            '*.email.required'            => 'Please verify that your file title is correct and all required fields are filled.',
            '*.email.email'               => 'Please add valid email address.',
            '*.phone_number.required'     => 'Please verify that your file title is correct and all required fields are filled.',
            '*.company_name.required'     => 'Please verify that your file title is correct and all required fields are filled.',
            '*.category.required'         => 'Please verify that your file title is correct and all required fields are filled.',
            '*.category.in'               => 'Please add category in Holder Or Representative.',
            '*.country.required'          => 'Please verify that your file title is correct and all required fields are filled.',
            '*.city.required'             => 'Please verify that your file title is correct and all required fields are filled.',
        ]);

        if ($validator->fails()) {
            $errorMessages .= $validator->errors()->first('*.email');
            $errorMessages .= $validator->errors()->first('*.phone_number');
            $errorMessages .= $validator->errors()->first('*.company_name');
            $errorMessages .= $validator->errors()->first('*.category');
            $errorMessages .= $validator->errors()->first('*.country');
            $errorMessages .= $validator->errors()->first('*.city');
            //Throw error message
            throwError($errorMessages);
        }

        return true;
    }
}
