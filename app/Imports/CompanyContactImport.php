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
use App\Exceptions\CustomException;
use Exception;
use Illuminate\Validation\Rule;
use App\Models\CompanyPeople;
use Illuminate\Support\Str;

class CompanyContactImport implements ToCollection, SkipsEmptyRows, WithHeadingRow

{
    public $data;

    public function  __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @param Collection $collection
     */
    public function collection(Collection $rows)
    {
        // check validation
        companyImportValidation($rows);

        $errorMessages = [];
        $domains = [];
        $rowsWithDomain = [];
        $importData = [];
        $existDomains = [];
        $companyContact = CompanyContact::query();
        foreach ($rows as $rowIndex => $row)
        {
            // If row empty then skip row.
            if ($row->filter()->isNotEmpty()) {
                $rowIndex += 2;

                $emailDomain = substr($row['email'], strpos($row['email'], '@') + 1);
                // Check company domain if domain in array then continue otherwise check unique domain validation
                if (in_array($emailDomain, config('constants.ALLOWED_DOMAINS'))) {
                    continue;
                }
                // Find exist email domain
                $existDomain = (clone $companyContact)->where('email', 'like', "%@{$emailDomain}")->where('sub_type', 'C')->where('company_id', auth()->user()->company_id)->exists();
                if ($existDomain) {
                    $existDomains[] = $emailDomain;
                }
                // Duplicate domain in sheet array create with line number
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

        // In sheet get count of same domain company.
        $duplicateDomains = array_filter($rowsWithDomain, function ($row) {
            return count($row) > 1;
        });

        foreach ($duplicateDomains as $domain => $rows) {
            $rowNumber = implode(',', $rows);
            $errorMessages[] = "Duplicate email domain found {$domain} in rows {$rowNumber}. Please enter a unique domain for each company";
            break;
        }

        // Throw error if company domain already exist in upload file.
        if (is_array($errorMessages) && count($errorMessages) > 0)
        {
            $errorMessage = implode(' ', $errorMessages);
            //Throw error message
            throwError($errorMessage);
        }

        // Throw error if company domain already exist in database.
        if (is_array($existDomains) && count($existDomains) > 0)
        {
            $existDomains = implode(',', $existDomains);
            //Throw error message
            throwError("This domain ({$existDomains}) already exist.");
        }

        // process valid rows and save them to the database
        foreach ($rows as $row) {
            if ($row->filter()->isNotEmpty()) {

                $existContact = (clone $companyContact)->where('email', $row['email'])->where('company_id', auth()->user()->company_id)->first();
                if ($existContact) {
                    continue;
                }

                // Get country
                $country = Country::where('name', trim($row['country']))->first();

                // Get city
                $city = City::where('name', trim($row['city']))->first();

                if (isset($row['category']) && $row['category'] == 'Holder') {
                    $category = 'H';
                } else {
                    $category = 'R';
                }
                //Create Contact
                $contact = (clone $companyContact)->create([
                    'company_id'      => auth()->user()->company_id,
                    'type'            => $this->data['type'],
                    'sub_type'        => $this->data['sub_type'],
                    'category'        => $category,
                    'company_name'    => $row['company_name'] ?? null,
                    'email'           => $row['email'] ?? null,
                    'phone_number'    => $row['phone_number'] ?? null,
                    'country_code'    => $country->code ?? null,
                    'city_id'         => $city->id ?? null,
                    'is_import'       => 1,
                    'is_private'      => $this->data['is_private'] ?? 0,
                    'is_lost'         => $this->data['is_lost'] ?? 0,
                ]);
                // Store upload contacts in array
                array_push($importData, $contact);
                $slug = Str::slug($contact->id);
                // Get Contact slag url
                $fianlslug = contactSlugUrl($this->data['type'], $slug);
                //Update slag url
                $contact->update([
                    'slag' => $fianlslug
                ]);
                // Company People Mapping
                contactCompanyPeopleMap($contact);
            }
        }
        /* Sent notification to the team member */
        sentMultipleNotification($this->data['type'], $this->data['sub_type'], $rows->count());
        // Store upload contacts in session
        session()->put('contact', $importData);
    }
}
