<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Protocol;
use Illuminate\Http\Request;

class ProtocolController extends Controller
{
    /**
     * Protocol lists
     *
     *
     * @return void
     */
    public function list(Request $request)
    {
        $this->validate($request, [
            'page'       => 'required|integer|min:1',
            'perPage'    => 'required|integer|min:1',
            'contact_id' =>  'required|exists:contacts,id',
            'category'   => 'required|in:notes,files,profile,all',
        ]);

        $protocolQuery = Protocol::query();
        $protocol = (clone $protocolQuery)->where('contact_id', $request->contact_id)->where('category', $request->category);

        if ($request->category == 'all') {
            $protocol = (clone $protocolQuery)->where('contact_id', $request->contact_id);
        }

        /*For pagination and sorting filter*/
        $result = filterSortPagination($protocol);
        $protocols = $result['query']->get();
        $count     = $result['count'];

        return ok(__('Protocol list'), [
            'Protocol' => $protocols,
            'count'    => $count
        ]);
    }
}
