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

        // Country wise filter
        if($request->country_code){
            $query->where('country_code', $request->country_code);
        }

        $query->select('id','country_code','name');

        /* For Pagination and shorting filter*/
        $result = filterSortPagination($query);
        $cities = $result['query']->get();
        $count  = $result['count'];

        return ok(__('Cities List'),[
            'cities' => $cities,
            'count' => $count,
        ]);
    }

}
