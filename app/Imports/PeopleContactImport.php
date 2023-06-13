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
use App\Exceptions\CustomException;
use Exception;
use Illuminate\Validation\Rule;
use App\Models\CompanyPeople;
use Illuminate\Support\Str;

class PeopleContactImport implements ToCollection, SkipsEmptyRows, WithHeadingRow
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
        $importData = [];
        //check validation
        peopleImportValidation($rows);
        // process valid rows and save them to the database
        foreach ($rows as $row) {
            if ($row->filter()->isNotEmpty()) {
                $companyContact = CompanyContact::query();
                $existContact = (clone $companyContact)->where('email', $row['email'])->where('company_id', auth()->user()->company_id)->first();
                if ($existContact) {
                    continue;
                }
                $data = $this->data;
                // Get country
                $country = Country::where('name', trim($row['country']))->first();
                // Get city
                $city = City::where('name', trim($row['city']))->first();
                //Create Contact
                $contact = (clone $companyContact)->create([
                    'company_id'        => auth()->user()->company_id,
                    'type'              => $data['type'],
                    'sub_type'          => $data['sub_type'],
                    'email'             => $row['email'] ?? null,
                    'phone_number'      => $row['phone_number'] ?? null,
                    'first_name'        => $row['first_name']  ?? null,
                    'last_name'         => $row['last_name']  ?? null,
                    'country_code'      => $country->code ?? null,
                    'city_id'           => $city->id ?? null,
                    'role'              => $row['role'] ?? null,
                    'point_of_contact'  => $row['point_of_contact'] ?? null,
                    'is_import'         => 1,
                    'is_private'        => $data['is_private'] ?? 0,
                    'is_lost'           => $data['is_lost'] ?? 0,
                ]);

                // Store upload contacts in array
                array_push($importData, $contact);
                $slug = Str::slug($contact->id);
                // Get Contact slag url
                $fianlslug = contactSlugUrl($data['type'], $slug);
                //Update slag url
                $contact->update([
                    'slag' => $fianlslug
                ]);
                //contact Company People Mapping
                contactCompanyPeopleMap($contact);
            }
        }
        /* Sent notification to the team member */
        sentMultipleNotification($this->data['type'], $this->data['sub_type'], $rows->count());
        // Store upload contacts in session
        session()->put('peopleContact', $importData);
    }
}
