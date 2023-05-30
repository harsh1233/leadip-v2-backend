<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\City;
use Illuminate\Support\Str;

class CityController extends Controller
{
    
    /**
     * get City api
     *
     * @param  mixed $request
     * @return void
     */
    public function list(Request $request)
    {
        $this->validate($request, [
            'country_code'  => 'nullable'
        ]);

        $query = City::query();

        if($request->country_code){
            $query->where('country_code',$request->country_code);
        }
        
        $query->select('id','country_code','name');

        $count = $query->count();
        if($request->page && $request->perPage){
            $page    = $request->page;
            $perPage = $request->perPage;
            $query->skip($perPage * ($page - 1))->take($perPage);
        }

        $cities = $query->get();

        return ok(__('Cities list'),[ 
            'cities' => $cities,
            'count' => $count,
        ]);

    }

}
