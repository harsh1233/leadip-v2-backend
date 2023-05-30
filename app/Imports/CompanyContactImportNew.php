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
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\ValidationException;
use App\Http\Controllers\Functions;
use App\Exceptions\CustomException;
use Exception;
use Illuminate\Validation\Rule;
use App\Models\CompanyPeople;
use Str;

class CompanyContactImportNew implements ToCollection, SkipsEmptyRows, WithHeadingRow

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
            $this->throwError($errorMessages);
        }

        $errorMessages = [];
        $domains = [];
        $rowsWithDomain = [];
        $importData = [];
        $existDomains = [];
        $existDomain = CompanyContact::query();
        foreach ($rows as $rowIndex => $row)
        {
            if ($row->filter()->isNotEmpty())
            {
                // If row empty then skip row.
                if (empty($row['company_name']) && empty($row['category']) && empty($row['email']) && empty($row['phone_number'])  && empty($row['country']) && empty($row['city']))
                {
                    continue;
                }
                else
                {
                    $rowIndex =  $rowIndex + 2;

                    $emailDomain = substr($row['email'], strpos($row['email'], '@') + 1);
                    // Check company domain if domain in array then continue otherwise check unique domain validation
                    if (in_array($emailDomain, config('constants.ALLOWED_DOMAINS')))
                    {
                        continue;
                    }
                    if (isset($domains[$emailDomain]))
                    {
                        $rowWithDomain = $rowsWithDomain[$emailDomain];
                        $rowWithDomain[] = $rowIndex;
                        $rowsWithDomain[$emailDomain] = $rowWithDomain;
                    }
                    else
                    {
                        $domains[$emailDomain] = true;
                        $rowsWithDomain[$emailDomain] = [$rowIndex];
                    }

                    $email  = $row['email'];
                    $domain = substr($email, strpos($email, '@') + 1);

                    // Skip the duplicate check for gmail.com and yahoo.com domains
                    if ($domain != 'hotmail.com' && $domain != 'yahoo.com' && $domain != 'gmail.com' && $domain != 'icloud.com' && $domain != 'outlook.com') {
                        $compnyId = (clone $existDomain)->where('email', 'like', "%@$domain")->where('sub_type', 'C')->where('company_id', auth()->user()->company_id)->exists();
                        if ($compnyId) {
                            $existDomains[] = $domain;
                        }
                    }
                }
            }
        }

        // Throw error if company domain already exist in database.
        if (is_array($existDomains) && count($existDomains) > 0)
        {
            $existDomains = implode(',', $existDomains);
            $error = "This domain (".$existDomains.") already exist.";
            $this->throwError($error);
        }

        // In sheet get count of same domain company.
        $duplicateDomains = array_filter($rowsWithDomain, function ($row) {
            return count($row) > 1;
        });

        foreach ($duplicateDomains as $domain => $rows) {
            $rowIndices = implode(',', $rows);
            $errorMessages[] = "Duplicate email domain found '$domain' in rows $rowIndices. Please enter a unique domain for each company";
            break;
        }

        // Throw error if company domain already exist in upload file.
        if (is_array($errorMessages) && count($errorMessages) > 0)
        {
            $errorMessage = implode(' ', $errorMessages);
            $this->throwError($errorMessage);
        }


        // process valid rows and save them to the database
        foreach ($rows as $row)
        {
            if ($row->filter()->isNotEmpty())
            {
                if (isset($row['company_name']) && empty($row['company_name']) && isset($row['category']) && empty($row['category']) && isset($row['email']) && empty($row['email']) && isset($row['phone_number']) && empty($row['phone_number'])  && isset($row['country']) && empty($row['country']) && isset($row['city']) && empty($row['city']))
                {
                    continue;
                }
                else
                {
                    $existContact = CompanyContact::where('email', $row['email'])->where('company_id', auth()->user()->company_id)->first();
                    if($existContact)
                    {
                        continue;
                    }

                    $data = $this->data;

                    $val = trim($row['country']);
                    // Get country
                    $country_code = Country::where('name', $val)->first();

                    $val = trim($row['city']);
                    // Get city
                    $city = City::where('name', $val)->first();

                    if (isset($row['category']) && $row['category'] == 'Holder')
                    {
                        $category = 'H';
                    }
                    else
                    {
                        $category = 'R';
                    }
                    //Create Contact
                    $contact = CompanyContact::create([
                        'company_id'      => auth()->user()->company_id,
                        'type'            => $data['type'],
                        'sub_type'        => $data['sub_type'],
                        'category'        => $category,
                        'company_name'    => ($row['company_name'] ? $row['company_name'] : null),
                        'email'           => ($row['email'] ?? null),
                        'phone_number'    => ($row['phone_number'] ? $row['phone_number'] : null),
                        'country_code'    => $country_code->code ?? null,
                        'city_id'         => $city->id ?? null,
                        'is_import'       => 1,
                        'is_private'      => $data['is_private'] ?? 0,
                        'is_lost'         => $data['is_lost'] ?? 0,
                    ]);
                    // Store upload contacts in array
                    array_push($importData, $contact);
                    $slug = Str::slug($contact->id);
                    if ($data['type'] == 'G')
                    {
                        $fianlslug =   url(config('constants.CONTACT_SLAG_SERVER_URL')) . $slug;
                    }
                    elseif ($data['type'] == 'P')
                    {
                        $fianlslug =  url(config('constants.PROSPECT_SLAG_SERVER_URL')) . $slug;
                    }
                    else
                    {
                        $fianlslug =  url(config('constants.CLIENT_SLAG_SERVER_URL')) . $slug;
                    }


                    CompanyContact::whereId($contact->id)->update([
                        'slag' => $fianlslug
                    ]);

                    $query = CompanyContact::query();
                    $queryData = CompanyPeople::query();
                    $temp = $this->data;
                    if ($temp['sub_type'] == 'C')
                    {
                        $email  = $row['email'];

                        $domain = substr($email, strpos($email, '@') + 1);
                        if ($domain != 'hotmail.com' && $domain != 'yahoo.com' && $domain != 'gmail.com' && $domain != 'icloud.com' && $domain != 'outlook.com')
                        {
                            $compnyId = (clone $query)->where('email', 'like', "%@$domain")->where('sub_type', 'P')->where('company_id', auth()->user()->company_id)->pluck('id')->toArray();
                            if ($compnyId)
                            {
                                foreach ($compnyId as $key => $value)
                                {
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
        // Store upload contacts in session
        session()->put('contact', $importData);
    }
}
