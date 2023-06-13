<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Company, User};
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * update company description api
     *
     * @param  mixed $request
     * @return void
     */
    public function description(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'company_logo'  =>  'nullable|mimes:png,jpg,jpeg|max:5120', //upto 5 Mb
            'name'          =>  'required|max:64',
            'headline'      =>  'required|max:100',
            'description'   =>  'nullable'
        ], [
            'company_logo.max' => 'The company logo must not be greater than 5 MB.'
        ]);

        $user = auth()->user();
        if ($request->company_logo) {
            $file         = $request->company_logo;
            $directory    = 'company/logo';
            //Upload image in s3 bucket
            $company_logo = uploadFile($file, $directory);
        } else {
            $company_logo = $user->company->profile_picture;
        }

        // Update Company Details
        $user->company->update([
            'profile_picture'   =>  $company_logo,
            'name'              =>  $request->name,
            'headline'          =>  $request->headline,
            'description'       =>  $request->description,
        ]);
        return ok(__('Company description updated successfully'), $user);
    }

    /**
     * update comapny contacts And Channels api
     *
     * @param  mixed $request
     * @return void
     */
    public function contactsAndChannels(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'email'             =>  'required|email',
            'phone'             =>  'required|min:12|numeric',
            'website'           =>  'required|url',
            'linkedin_profile'  =>  'nullable|url',
            'facebook_profile'  =>  'nullable|url',
            'other_profile'     =>  'nullable|url',
            'extra_channels'    =>  'nullable|array',

        ]);

        $user           = auth()->user();
        $extra_channels = serialize($request->input('extra_channels'));

        // create or update user details
        $company = Company::updateOrCreate([
            'id'                =>  $user->company_id
        ], [
            'email'             => $request->email,
            'phone'             => $request->phone,
            'whatsapp_number'   => $request->whatsapp_number,
            'linkedin_profile'  => $request->linkedin_profile,
            'facebook_profile'  => $request->facebook_profile,
            'other_profile'     => $request->other_profile,
            'website'           => $request->website,
            'extra_channels'    => $extra_channels,
        ]);
        return ok(__('Contacts and channels updated successfully!'), ['user' => $user, 'company' => $company]);
    }


    /**
     * update comapny office api
     *
     * @param  mixed $request
     * @return void
     */
    public function companyOffice(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'offices'                  =>  'nullable|array',
            'offices.*.address'        =>  'required',
            'offices.*.country_code'   =>  'required|exists:countries,code',
            'offices.*.city_id'        =>  'required|exists:cities,id',
            'offices.*.type'           =>  'required|in:B,H', //B: Branch, H: Headquarters
        ]);

        $user = auth()->user();
        // delete old location
        $user->company->offices()->delete();
        //create new location
        if (isset($request['offices']) && count($request['offices']) > 0) {
            foreach ($request['offices'] as $office) {
                $user->company->offices()->create([
                    'company_id'    => $user->company->id,
                    'address'       => $office['address'],
                    'country_code'  => $office['country_code'],
                    'city_id'       => $office['city_id'],
                    'type'          => $office['type'],
                ]);
            }
        }
        return ok(__('Offices updated successfully!'), $user->load('company.offices'));
    }

    /**
     * update company languages api
     *
     * @param  mixed $request
     * @return void
     */
    public function companyLanguages(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'languages'     =>  'nullable|array',
        ]);

        $user = auth()->user();
        // create or update user details
        $company = Company::updateOrCreate([
            'id'           =>  $user->company_id
        ], [
            'languages'    => $request->languages,
        ]);
        return ok(__('Languages updated successfully!'), $company);
    }


    /**
     * update company expertises api
     *
     * @param  mixed $request
     * @return void
     */
    public function companyExpertises(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'expertises'    =>  'nullable|array',
        ]);

        $user  = auth()->user();
        // create or update user details
        $company = Company::updateOrCreate([
            'id'           =>  $user->company_id
        ], [
            'expertises'   => $request->expertises,
        ]);
        return ok(__('Expertises updated successfully!'), $company);
    }
    /**
     * update company services api
     *
     * @param  mixed $request
     * @return void
     */
    public function companyServices(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'services'    =>  'nullable|array',
        ]);
        $user = auth()->user();
        // create or update user details
        $company = Company::updateOrCreate([
            'id'           =>  $user->company_id
        ], [
            'services'     => $request->services,
        ]);
        return ok(__('Services updated successfully!'), $company);
    }

    /**
     * update company regions api
     *
     * @param  mixed $request
     * @return void
     */
    public function companyRegions(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'regions'       =>  'nullable|array',
        ]);
        $user = auth()->user();        // create or update user details
        $company = Company::updateOrCreate([
            'id'           =>  $user->company_id
        ], [
            'regions'      => $request->regions,
        ]);
        return ok(__('Regions updated successfully!'), $company);
    }

    /**
     * update company details api
     *
     * @param  mixed $request
     * @return void
     */
    public function update(Request $request)
    {
        //Validation
        $this->validate($request, [
            'company_logo'                          =>  'nullable|mimes:png,jpg,jpeg|max:5120', //upto 5 Mb
            'name'                                  =>  'required|max:64',
            'email'                                 =>  'required|email',
            'phone'                                 =>  'required|min:12|numeric',
            'website'                               =>  'required|url',
            'offices'                               =>  'required|array',
            'offices.*.address'                     =>  'required|string|max:512',
            'offices.*.country_code'                =>  'required|exists:countries,code',
            'offices.*.city_id'                     =>  'required|exists:cities,id',
            'offices.*.type'                        =>  'required|in:B,H', //B: Branch, H: Headquarters
            'services'                              =>  'required|array',
            'expertises'                            =>  'required|array',
            'regions'                               =>  'required|array',
            'headline'                              =>  'nullable|max:100',
            'description'                           =>  'nullable|max:250',
            'linkedin_profile'                      =>  'nullable|url',
            'facebook_profile'                      =>  'nullable|url',
            'other_profile'                         =>  'nullable|url',
            'extra_channels'                        =>  'nullable|array',
            'languages'                             =>  'nullable|array',
            'certifications'                        =>  'nullable|array',
            "certifications.*.name"                 =>  'nullable|max:100',
            "certifications.*.issuing_organization" =>  'nullable|max:64',
            'certifications.*.issue_date'           =>  'nullable|date_format:Y-m-d H:i:s',
            'meets'                                 =>  'nullable|array',
            'meets.*.name'                          =>  'nullable|max:64',
            'meets.*.link'                          =>  'nullable|url',
            'meets.*.date'                          =>  'nullable|date_format:Y-m-d H:i:s',
            'meets.*.country_code'                  =>  'nullable|exists:countries,code',
        ], [
            'company_logo.max'                      => 'The company logo must not be greater than 5 MB.'
        ]);


        $user = auth()->user();

        if ($request->company_logo) {
            $file         = $request->company_logo;
            $directory    = 'company/logo';
            //Image store in s3 bucket
            $company_logo = uploadFile($file, $directory);
        } else {
            $company_logo = $user->company->profile_picture;
        }

        $extra_channels = NULL;
        if ($request->input('extra_channels')) {
            $extra_channels = serialize($request->input('extra_channels'));
        }

        $languages = NULL;
        if ($request->languages) {
            $languages = $request->languages;
        }

        if ($request->expertises) {
            $expertises = $request->expertises;
        } else {
            $expertises = $user->company->expertises;
        }

        if ($request->services) {
            $services = $request->services;
        } else {
            $services = $user->company->services;
        }

        if ($request->regions) {
            $regions = $request->regions;
        } else {
            $regions = $user->company->regions;
        }

        // Update company info
        $user->company->update([
            'profile_picture'   => $company_logo,
            'name'              => $request->name,
            'headline'          => $request->headline,
            'description'       => $request->description,
            'email'             => $request->email,
            'phone'             => $request->phone,
            'linkedin_profile'  => $request->linkedin_profile,
            'facebook_profile'  => $request->facebook_profile,
            'other_profile'     => $request->other_profile,
            'website'           => $request->website,
            'extra_channels'    => $extra_channels,
            'languages'         => $languages,
            'expertises'        => $expertises,
            'services'          => $services,
            'regions'           => $regions,
            'id'                => $user->company_id
        ]);

        // If office already exist then delete and create new office otherwise create office
        if (isset($request['offices']) && count($request['offices']) > 0) {

            // delete old location
            $user->company->offices()->delete();
            //create new offices
            foreach ($request['offices'] as $office) {
                $user->company->offices()->create([
                    'company_id'    => $user->company->id,
                    'address'       => $office['address'],
                    'country_code'  => $office['country_code'],
                    'city_id'       => $office['city_id'],
                    'type'          => $office['type'],
                ]);
            }
        }

        // If certification already exist then delete and create new certification otherwise create certification
        if (isset($request['certifications']) && count($request['certifications']) > 0) {
            // delete old certification
            $user->company->certifications()->delete();
            //create new certifications
            foreach ($request['certifications'] as $certification) {
                $user->company->certifications()->create([
                    'company_id'            => $user->company->id,
                    'name'                  => $certification['name'] ?? '',
                    'issuing_organization'  => $certification['issuing_organization'] ?? '',
                    'issue_date'            => $certification['issue_date'] ?? null,
                ]);
            }
        }

        // If meets already exist then delete and create new meets otherwise create meets
        if (isset($request['meets']) && count($request['meets']) > 0) {
            //delete old meets
            $user->company->meets()->delete();
            //create new meets
            foreach ($request['meets'] as $certification) {
                $user->company->meets()->create([
                    'company_id'            => $user->company->id,
                    'name'                  => $certification['name'] ?? '',
                    'link'                  => $certification['link'] ?? '',
                    'date'                  => $certification['date'] ?? null,
                    'country_code'          => $certification['country_code'] ?? null,
                ]);
            }
        } else {
            $user->company->meets()->delete();
        }

        /*Sent notification when update company profile */
        sentUserNotification();
        return ok(__('Your company data has been successfully updated'), $user);
    }

    /**
     * update company certifications api
     *
     * @param  mixed $request
     * @return void
     */
    public function companyCertifications(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'user_id'                               =>  'required|exists:users,id',
            'certifications'                        =>  'nullable|array',
            'certifications.*.name'                 =>  'nullable',
            'certifications.*.issuing_organization' =>  'nullable',
            'certifications.*.issue_date'           =>  'nullable|date_format:Y-m-d H:i:s',
        ]);

        $user = User::findOrFail($request->user_id);

        // delete old location
        $user->company->certifications()->delete();

        //create new location
        if (isset($request['certifications']) && count($request['certifications']) > 0) {
            foreach ($request['certifications'] as $certification) {
                $user->company->certifications()->create([
                    'company_id'            => $user->company->id,
                    'name'                  => $certification['name'] ?? '',
                    'issuing_organization'  => $certification['issuing_organization'] ?? '',
                    'issue_date'            => $certification['issue_date'] ?? date('Y-m-d H:i:s'),
                ]);
            }
        }
        return ok(__('Certifications updated successfully!'), $user->load('company.certifications'));
    }

    /**
     * update company meet api
     *
     * @param  mixed $request
     * @return void
     */
    public function companyMeet(Request $request) // TODO: "not use this api"
    {
        //Validation
        $this->validate($request, [
            'user_id'               =>  'required|exists:users,id',
            'meets'                 =>  'nullable|array',
            'meets.*.name'          =>  'nullable',
            'meets.*.link'          =>  'nullable|url',
            'meets.*.date'          =>  'nullable|date_format:Y-m-d H:i:s',
            'meets.*.country_code'  =>  'nullable|exists:countries,code',
        ]);

        $user = User::findOrFail($request->user_id);
        // delete old location
        $user->company->meets()->delete();
        //create new location
        if (isset($request['meets']) && count($request['meets']) > 0) {
            foreach ($request['meets'] as $certification) {
                $user->company->meets()->create([
                    'company_id'            => $user->company->id,
                    'name'                  => $certification['name'] ?? '',
                    'link'                  => $certification['link'] ?? '',
                    'date'                  => $certification['date'] ?? date('Y-m-d H:i:s'),
                    'country_code'          => $certification['country_code'],
                ]);
            }
        }
        return ok(__('Meets updated successfully!'), $user->load('company.meets'));
    }

    /**
     * get company view profile api
     *
     * @param  mixed $id
     * @return void
     */
    public function companyViewProfile($id)
    {
        // Get user using id
        $user = User::find($id);

        if (!$user) {
            return error(__('This user does not exist!'), [], 'validation');
        }

        // Get company info using user company id
        $company = Company::with('users', 'offices.city_details', 'offices.country_details', 'certifications', 'meets.country_details')->findOrFail($user->company_id);

        return ok(__('View company profile successfully!'), $company);
    }
}
