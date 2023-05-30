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
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Validators\ValidationException;
use App\Http\Controllers\Functions;
use App\Exceptions\CustomException;
use Exception;
use Illuminate\Validation\Rule;
use App\Models\CompanyPeople;
use Str;

class PeopleContactImportNew implements ToCollection, SkipsEmptyRows, WithHeadingRow
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
        $errorMessages = "";
        $count = 0;
        $importData = [];
        // Validation
        $validator = Validator::make($rows->toArray(), [
            '*.email'            => 'required',
            '*.phone_number'     => 'required',
            '*.role'             => 'required',
            '*.point_of_contact' => 'required',
            '*.country'          => 'required|exists:countries,name',
            '*.city'             => 'required|exists:cities,name',
            '*.first_name'       => 'required',
            '*.last_name'        => 'required',
        ],[
            '*.email.required'            => 'Please verify that your file title is correct and all required fields are filled.',
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
            $this->throwError($errorMessages);
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
                // Get country
                $country_code = Country::where('name', $val)->first();
                $val = trim($row['city']);
                // Get city
                $city = City::where('name', $val)->first();
                //Create Contact
                $contact = CompanyContact::create([
                    'company_id'        => auth()->user()->company_id,
                    'type'              => $data['type'],
                    'sub_type'          => $data['sub_type'],
                    'email'             => ($row['email'] ?? null),
                    'phone_number'      => ($row['phone_number'] ?? null),
                    'first_name'        => ($row['first_name']  ?? null),
                    'last_name'         => ($row['last_name']  ?? null),
                    'country_code'      => $country_code->code ?? null,
                    'city_id'           => $city->id ?? null,
                    'role'              => ($row['role'] ?? null),
                    'point_of_contact'  => ($row['point_of_contact'] ?? null),
                    'is_import'         => 1,
                    'is_private'        => $data['is_private'] ?? 0,
                    'is_lost'           => $data['is_lost'] ?? 0,
                ]);

                // Store upload contacts in array
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
        // Store upload contacts in session
        session()->put('peopleContact', $importData);
    }
}
