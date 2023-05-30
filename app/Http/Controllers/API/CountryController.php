<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;

class CountryController extends Controller
{
    /**
     * get Country api
     *
     * @param  mixed $request
     * @return void
     */
    public function list(Request $request)
    {
        $countries = Country::get(['code','name']);
        return ok(__('Country list successfully!'),$countries);

    }
}
