<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Protocol;
use Illuminate\Http\Request;
use App\Http\Controllers\Functions;

class ProtocolController extends Controller
{
    use Functions;

    /*Protocol lists */
    public function list(Request $request)
    {
        $this->validate($request, [
            'page'      => 'required|integer|min:1',
            'perPage'   => 'required|integer|min:1',
            'contact_id' =>  'required|exists:contacts,id',
            'category'  => 'required|in:notes,files,profile,all',
        ]);

        $protocol = Protocol::where('contact_id', $request->contact_id)->where('category',  $request->category);

        if ($request->category == 'all') {
            $protocol = Protocol::where('contact_id', $request->contact_id);
        }

        /**For pagination */
        $paginateDetails = $this->getPaginationDetails($request->page, $request->perPage);
        $page    = $paginateDetails['page'];
        $perPage = $paginateDetails['perPage'];
        $count = $protocol->count();
        $protocol->skip($perPage * ($page - 1))->take($perPage);

        $protocol = $protocol->orderBy('created_at', 'desc')->get();

        return ok(__('Protocol list'), [
            'Protocol' => $protocol,
            'count' => $count
        ]);
    }
}
