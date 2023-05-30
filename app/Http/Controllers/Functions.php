<?php

namespace App\Http\Controllers;

use DB;
use App\Models\CompanyContact;
use App\Exceptions\CustomException;
use Illuminate\Support\Facades\Validator;
use App\Models\Protocol;
use Illuminate\Validation\ValidationException;

trait Functions
{
    // send json response
    public function sendResponse($status, $message, $data = null)
    {
        if ($status) {
            return response()->json(['message' => $message, 'data' => $data], 200);
        } else {
            return response()->json(['error' => $message], 400);
        }
    }

    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    public function convertArrayInStrings($val = array())
    {
        return "'" . implode("', '", $val) . "'";
    }

    public function validationRules()
    {
        return [
            'company_name'          =>  'required',
            'email'                 =>  'required|email',
            'phone_number'          =>  'required|min:12|numeric',
            'country'               =>  'required|exists:countries,name',
            'city'                  =>  'required|exists:cities,name',
            'first_name'            =>  'required_if:sub_type,==,P',
            'last_name'             =>  'required_if:sub_type,==,P',
        ];
    }

    public function addProtocol($contact_id, $category, $message)
    {
        $protocol = [];
        $protocol['contact_id'] = $contact_id;
        $protocol['category'] =   $category;
        $protocol['message'] = $message;
        if ($category == 'profile') {
            $protocol['icon'] = 'Contacts.svg';
        } elseif ($category == 'files') {
            $protocol['icon'] = 'pin.svg';
        } elseif ($category == 'notes') {
            $protocol['icon'] = 'Notes.svg';
        } else {
            $protocol['icon'] = 'pin.svg';
        }

        $protocol = Protocol::create($protocol);

        return $message;
    }

    public function checkValidations($input, $message)
    {
        $input = (object) $input;


        if ($input->email) {
            $user = CompanyContact::where('email', $input->email)->first();
            if ($user != NULL) {
                $this->throwError(__(' :model is already taken.', ['model' => 'Email']));
            }
        }

        return $input;
    }

    public function throwError($message)
    {
        throw ValidationException::withMessages([
            'message' => [$message]
        ]);
    }

    /**
     * Get pagination details
     * page : pagination start point like 1, 2, 3, etc..
     * perPage : per page data value like 5, 10, etc..
     * isRowQuery : if result data are from DB::row query then `true` else `false` normal laravel get listing
     */
    public function getPaginationDetails($page = null, $perPage = null, $isRowQuery = false)
    {
        if ($isRowQuery) {
            if ($page && $perPage) {
                $page    = $page;
                $perPage = $perPage;
                if ($page == 1) {
                    $page = config('constants.DEFAULT_OFFSET');
                } else {
                    $page = $perPage * ($page - 1);
                }
            } else {
                $page    = config('constants.DEFAULT_OFFSET');
                $perPage = config('constants.DEFAULT_PER_PAGE');
            }
        } else {
            if ($page && $perPage) {
                $page    = $page;
                $perPage = $perPage;
            } else {
                $page    = config('constants.DEFAULT_PAGE');
                $perPage = config('constants.DEFAULT_PER_PAGE');
            }
        }
        return array('page' => $page, 'perPage' => $perPage);
    }
}
