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

class CompanyContactImport implements ToCollection, WithHeadingRow
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
        $importData = [];
        foreach ($rows as $rowIndex => $row) {
            if ($row->filter()->isNotEmpty()) {
                if (empty($row['email']) || empty($row['phone_number']) || empty($row['role']) || empty($row['point_of_contact']) || empty($row['country']) || empty($row['city']) || empty($row['first_name']) || empty($row['last_name'])) {
                    $errorMessages = '';
                    $errorMessages .= 'Please verify that your file title is correct and all required fields are filled.';
                    $count = $count + 1;
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
                    // contact row number with string
                    $rowcount = 'In Row ' . ($rowIndex + 2) . ', ';
                    $errorMessages =  $rowcount . $errorMessages;
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
        // process valid rows and save them to the database
        foreach ($rows as $row) {
            if ($row->filter()->isNotEmpty()) {

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
                $contact = CompanyContact::create([
                    'company_id'    =>  auth()->user()->company_id,
                    'type'    =>         $data['type'],
                    'section'    =>  $data['section'],
                    'sub_type'    =>   $data['sub_type'],
                    'email'    => ($row['email'] ?: null),
                    'phone_number'    => ($row['phone_number'] ?: null),
                    'first_name'    => ($row['first_name']  ?: null),
                    'last_name'    => ($row['last_name']  ?: null),
                    'country_code'    => $country_code->code,
                    'city_id'    => $c->id,
                    'role'     => ($row['role'] ?: null),
                    'point_of_contact'     => ($row['point_of_contact'] ?: null),
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
                if ($temp['sub_type'] == 'P') {
                    $email  = $row['email'];
                    $domain = substr($email, strpos($email, '@') + 1);
                    if ($domain != 'hotmail.com' && $domain != 'yahoo.com' && $domain != 'gmail.com' && $domain != 'icloud.com' && $domain != 'outlook.com') {
                        $compnyId = (clone $query)->where('email', 'like', "%@$domain")->where('sub_type', 'C')->where('company_id', auth()->user()->company_id)->first();
                        if ($compnyId) {
                            $queryData->create(['company_id' => $compnyId->id, 'people_id' => $contact->id]);
                            $query->where('id', $contact->id)->update(['company_name' => $compnyId->company_name]);
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
        session()->put('peopleContact', $importData);
    }
}
