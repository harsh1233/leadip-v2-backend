<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Region;

class RegionController extends Controller
{
    /**
     * get Roles api
     *
     * @param  mixed $request
     * @return void
     */
    public function list(Request $request)
    {
        // Get All regions
        $regions = Region::get(['id','name']);
        return ok(__('Region List'), $regions);
    }
}
