<?php

namespace App\Imports;

use App\Models\CompanyContact;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use App\Models\City;
use App\Models\Country;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\ValidationException;
use App\Http\Controllers\Functions;
use App\Exceptions\CustomException;
use Exception;
use Illuminate\Validation\Rule;
use App\Models\CompanyPeople;
use Str;

class PeopleContactImport implements ToCollection, WithHeadingRow

{
    use Functions;

    public function  __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @param Collection $collection
     */
    public function collection(Collection $rows)
    {
        $errorMessages = '';
        $count = 0;
        foreach ($rows as $rowIndex => $row) {
            if ($row->filter()->isNotEmpty()) {
                if (empty($row['company_name']) && empty($row['category']) && empty($row['email']) && empty($row['phone_number'])  && empty($row['country']) && empty($row['city'])) {
                    continue;
                } elseif ((isset($row['company_name']) && empty($row['company_name'])) || (isset($row['company_name']) && empty($row['category'])) || (isset($row['company_name']) && empty($row['email'])) || (isset($row['company_name']) && empty($row['phone_number']))  || (isset($row['company_name']) && empty($row['country'])) || (isset($row['company_name']) && empty($row['city']))) {
                    $errorMessages = '';
                    $errorMessages .= 'Please verify that your file title is correct and all required fields are filled.';
                    $count = $count + 1;
                }

                // if there were no errors, return true to indicate success
                if ($count == 0) {
                    continue;
                } else {
                    // throw an exception if there are any errors
                    $this->throwError($errorMessages);
                }
            }
        }

        $errorMessages = [];
        $domains = [];
        $rowsWithDomain = [];
        $importData = [];
        foreach ($rows as $rowIndex => $row) {
            if ($row->filter()->isNotEmpty()) {
                if (empty($row['company_name']) && empty($row['category']) && empty($row['email']) && empty($row['phone_number'])  && empty($row['country']) && empty($row['city'])) {
                    continue;
                } else {
                    $rowIndex =  $rowIndex + 2;

                    $validator = Validator::make(['email' => $row['email']], [
                        'email' => 'email',
                    ]);

                    if ($validator->fails()) {
                        $errorMessages[] = "Invalid email address in row $rowIndex: " . $validator->errors()->first('email');
                    } else {
                        $emailDomain = substr($row['email'], strpos($row['email'], '@') + 1);
                        if (in_array($emailDomain, config('constants.ALLOWED_DOMAINS'))) {
                            continue;
                        }
                        if (isset($domains[$emailDomain])) {
                            $rowWithDomain = $rowsWithDomain[$emailDomain];
                            $rowWithDomain[] = $rowIndex;
                            $rowsWithDomain[$emailDomain] = $rowWithDomain;
                        } else {
                            $domains[$emailDomain] = true;
                            $rowsWithDomain[$emailDomain] = [$rowIndex];
                        }
                    }
                }
            }
        }

        $duplicateDomains = array_filter($rowsWithDomain, function ($row) {
            return count($row) > 1;
        });

        foreach ($duplicateDomains as $domain => $rows) {
            $rowIndices = implode(',', $rows);
            $errorMessages[] = "Duplicate email domain found '$domain' in rows $rowIndices. Please enter a unique domain for each company";
            break;
        }

        if (count($errorMessages) > 0) {
            $errorMessage = implode(' ', $errorMessages);
            $this->throwError($errorMessage);
        }

        $errorMessages = '';
        $count = 0;
        foreach ($rows as $rowIndex => $row) {
            if ($row->filter()->isNotEmpty()) {
                if (isset($row['company_name']) && empty($row['company_name']) && isset($row['category']) && empty($row['category']) && isset($row['email']) && empty($row['email']) && isset($row['phone_number']) && empty($row['phone_number'])  && isset($row['country']) && empty($row['country']) && isset($row['city']) && empty($row['city'])) {
                    continue;
                } else {
                    $errorMessages = '';

                    // validate 'Email' field

                    $validator = Validator::make(['email' => $row['email']], [
                        'email' => ['email', Rule::unique('contacts')->where(function ($query) {
                            return $query->where('company_id', auth()->user()->company_id);
                        })],

                    ]);

                    if ($validator->fails()) {
                        continue;
                        // $errorMessages .=  $validator->errors()->first('email');
                        // $count = $count + 1;
                    }

                    // validate 'Email' field with domain name

                    $query = CompanyContact::query();
                    $temp = $this->data;

                    if ($temp['sub_type'] == 'C') {
                        $email  = $row['email'];

                        $domain = substr($email, strpos($email, '@') + 1);

                        // Skip the duplicate check for gmail.com and yahoo.com domains
                        if ($domain != 'hotmail.com' && $domain != 'yahoo.com' && $domain != 'gmail.com' && $domain != 'icloud.com' && $domain != 'outlook.com') {
                            $compnyId = (clone $query)->where('email', 'like', "%@$domain")->where('sub_type', 'C')->where('company_id', auth()->user()->company_id)->exists();

                            if ($compnyId) {
                                continue;
                                // $errorMessages .=  'Please enter a different email address with a unique domain name.';
                                // $count = $count + 1;
                            }
                        }
                    }

                    // validate 'phone_number' field

                    $validator = Validator::make(['phone_number' => $row['phone_number']], [
                        'phone_number' => 'nullable',
                    ]);

                    if ($validator->fails()) {
                        $errorMessages .=  $validator->errors()->first('phone_number');
                        $count = $count + 1;
                    }

                    // validate 'country' field

                    $validator = Validator::make(['country' => $row['country']], [
                        'country' => 'exists:countries,name',
                    ]);

                    if ($validator->fails()) {
                        $errorMessages .=  $validator->errors()->first('country');
                        $count = $count + 1;
                    }

                    // validate 'city' field

                    $validator = Validator::make(['city' => $row['city']], [
                        'city' => 'exists:cities,name',
                    ]);

                    if ($validator->fails()) {
                        $errorMessages .=  $validator->errors()->first('city');
                        $count = $count + 1;
                    }

                    // validate 'category' field

                    if(isset($row['category']))
                    {
                        $validator = Validator::make(['category' => $row['category']], [
                            'category' => 'in:Holder,Representative',
                        ]);

                        if ($validator->fails()) {
                            $errorMessages .=  $validator->errors()->first('category');
                            $count = $count + 1;
                        }
                    }
                    // contact row number with string

                    $rowcount = 'In Row ' . ($rowIndex + 2) . ', ';
                    $errorMessages =  $rowcount . $errorMessages;


                    // if there were no errors, return true to indicate success
                    if ($count == 0) {
                        continue;
                    } else {
                        // throw an exception if there are any errors
                        $this->throwError($errorMessages);
                    }
                }
            }
        }

        // process valid rows and save them to the database
        foreach ($rows as $row) {
            if ($row->filter()->isNotEmpty()) {
                if (isset($row['company_name']) && empty($row['company_name']) && isset($row['category']) && empty($row['category']) && isset($row['email']) && empty($row['email']) && isset($row['phone_number']) && empty($row['phone_number'])  && isset($row['country']) && empty($row['country']) && isset($row['city']) && empty($row['city'])) {
                    continue;
                } else {
                    $validator = Validator::make(['email' => $row['email']], [
                        'email' => ['email', Rule::unique('contacts')->where(function ($query) {
                            return $query->where('company_id', auth()->user()->company_id);
                        })],
                    ]);
                    if ($validator->fails()) {
                        continue;
                    }
                    $data = $this->data;

                    $val = trim($row['country']);
                    $country_code = Country::where('name', $val)->first();

                    $val = trim($row['city']);
                    $c = City::where('name', $val)->first();

                    if (isset($row['category']) && $row['category'] == 'Holder') {
                        $category = 'H';
                    } else {
                        $category = 'R';
                    }

                    $contact = CompanyContact::create([
                        'company_id'    =>  auth()->user()->company_id,
                        'type'    =>         $data['type'],
                        'section'    =>  $data['section'],
                        'sub_type'    =>   $data['sub_type'],
                        'category'    =>   $category,
                        'company_name'     => ($row['company_name'] ? $row['company_name'] : null),
                        'email'    => ($row['email'] ?: null),
                        'phone_number'    => ($row['phone_number'] ? $row['phone_number'] : null),
                        'country_code'    => $country_code->code,
                        'city_id'    => $c->id,
                        'is_import'    => 1,
                    ]);
                    array_push($importData, $contact);
                    $slug = Str::slug($contact->id);
                    if ($data['type'] == 'G') {
                        $fianlslug =   url(config('constants.CONTACT_SLAG_SERVER_URL')) . $slug;
                    } elseif ($data['type'] == 'P') {
                        $fianlslug =  url(config('constants.PROSPECT_SLAG_SERVER_URL')) . $slug;
                    } else {
                        $fianlslug =  url(config('constants.CLIENT_SLAG_SERVER_URL')) . $slug;
                    }


                    CompanyContact::whereId($contact->id)->update([
                        'slag' => $fianlslug
                    ]);

                    $query = CompanyContact::query();
                    $queryData = CompanyPeople::query();
                    $temp = $this->data;
                    if ($temp['sub_type'] == 'C') {
                        $email  = $row['email'];

                        $domain = substr($email, strpos($email, '@') + 1);
                        if ($domain != 'hotmail.com' && $domain != 'yahoo.com' && $domain != 'gmail.com' && $domain != 'icloud.com' && $domain != 'outlook.com') {
                            $compnyId = (clone $query)->where('email', 'like', "%@$domain")->where('sub_type', 'P')->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
                            if ($compnyId) {
                                foreach ($compnyId as $key => $value) {
                                    $comdata[] = [
                                        'people_id' => $value,
                                        'company_id' => $contact->id
                                    ];
                                }
                                $queryData->insert($comdata);
                                $query->whereIn('id', $compnyId)->update(['company_name' => $contact->company_name]);
                            }
                        }
                    }
                }
            }
        }
        /* Sent notification to the team member */
        $count    = $rows->count();
        $temp     = $this->data;
        $type     = $temp['type'];
        $sub_type = $temp['sub_type'];
        sentMultipleNotification($type, $sub_type, $count);
        session()->put('contact', $importData);
    }
}
