<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TradeMark;
use App\Models\TradeMarkHolder;
use App\Models\CompanyContact;

class TradeMarkController extends Controller
{
    public function listTradeMarks(Request $request)
    {
        $this->validate($request, [
            'contact_id' => 'required|exists:contacts,id',
            'sort_by'    =>  'nullable|in:asc,desc',
        ]);

        $contact = CompanyContact::where('id', $request->contact_id)->first();

        $query = TradeMark::with('image', 'holder.country', 'designation_country')
                            ->orWhereHas('holder', function ($query) use ($contact) {
                                if($contact->sub_type == 'C')
                                {
                                    $query->where('name', 'like', $contact->company_name);
                                }
                                elseif($contact->sub_type == 'P')
                                {
                                    $searchCompany = $contact->first_name.' '.$contact->last_name.' '.$contact->company_name.' '.$contact->email;
                                    $query->where('name', 'like', "%$searchCompany%");
                                }
                                else
                                {
                                    $query->where('name', 'like', $contact->company_name);
                                }
                            });

        if ($request->search) {
            $search = '%' . $request->search . '%';

            $query->where(function ($query) use ($search) {

                $query->where('irn_number', 'like', $search)
                    ->orWhere('registration_date', 'like', $search)
                    ->orWhere('application_date', 'like', $search)
                    ->orWhereHas('holder', function ($query) use ($search) {
                        $query->where('name', 'like', $search)->orWhere('address', 'like', $search);
                    })
                    ->orWhereHas('holder.country', function ($query) use ($search) {
                        $query->where('name', 'like', $search)->orWhere('code', 'like', $search);
                    })
                    ->orWhereHas('representative', function ($query) use ($search) {
                        $query->where('name', 'like', $search)->orWhere('address', 'like', $search);
                    })
                    ->orWhereHas('representative.country', function ($query) use ($search) {
                        $query->where('name', 'like', $search)->orWhere('code', 'like', $search);
                    })
                    ->orWhereHas('refusal', function ($query) use ($search) {
                        $query->where('gazette_number', 'like', $search)->orWhere('refusal_type', 'like', $search);
                    })
                    ->orWhereHas('refusal.country', function ($query) use ($search) {
                        $query->where('name', 'like', $search)->orWhere('code', 'like', $search);
                    });
            });
        }

        if ($request->sort_by) {
            $query->orderBy('created_at', $request->sort_by);
        }

        $count = $query->count();
        if ($request->page && $request->perPage) {
            $page    = $request->page;
            $perPage = $request->perPage;
            $query->skip($perPage * ($page - 1))->take($perPage);
        }

        $trademarks      = $query->get();
        return ok(__('Trademarks list'), [
            'trademarks' => $trademarks,
            'count'      => $count,
        ]);
    }

    public function getTradeMarkHolderInfo(Request $request)
    {
        $this->validate($request, [
            'contact_id' => 'required|exists:contacts,id',
        ]);

        $contact = CompanyContact::where('id', $request->contact_id)->first();

        $trademarkHolders = TradeMarkHolder::query();
        if($contact->sub_type == 'C')
        {
            $trademarkHolders->where('name', 'like', $contact->company_name);
        }
        elseif($contact->sub_type == 'P')
        {
            $search = $contact->first_name.' '.$contact->last_name.' '.$contact->company_name.' '.$contact->email;
            $trademarkHolders->where('name', 'like', "%$search%");
        }
        else
        {
            $trademarkHolders->where('name', 'like', $contact->company_name);
        }

        $trademarkHolderByCountry = clone $trademarkHolders;
        $count = $trademarkHolders->count();
        if ($request->page && $request->perPage) {
            $page    = $request->page;
            $perPage = $request->perPage;
            $trademarkHolders->skip($perPage * ($page - 1))->take($perPage);
        }

        $trademarkHolders = $trademarkHolders->with('country')->select('id','trademark_id','country_id','holder_id','name','address')->get();
        return ok(__('Holders Info.'), [
            'holder_details' => $trademarkHolders,
            'count'          => $count,
        ]);
    }

    public function getTradeMarkHolderCount(Request $request)
    {
        $this->validate($request, [
            'contact_id' => 'required|exists:contacts,id',
        ]);

        $contact = CompanyContact::where('id', $request->contact_id)->first();

        $trademarkHolders = \DB::connection(env('WIPO_DB_CONNECTION'))->table("trademark_holders")
        ->join('countries', 'trademark_holders.country_id', '=', 'countries.id');
        if($contact->sub_type == 'C')
        {
            $trademarkHolders->where('trademark_holders.name', 'like', $contact->company_name);
        }
        elseif($contact->sub_type == 'P')
        {
            $search = $contact->first_name.' '.$contact->last_name.' '.$contact->company_name.' '.$contact->email;
            $trademarkHolders->where('trademark_holders.name', 'like', "%$search%");
        }
        else
        {
            $trademarkHolders->where('trademark_holders.name', 'like', $contact->company_name);
        }
        $trademarkHolders = $trademarkHolders->select('countries.name','countries.code',\DB::raw('COUNT(`holder_id`) as total_holders'))->groupBy('trademark_holders.country_id')->get();

        return ok(__('Holders Info.'), [
            'country_wise_count' => $trademarkHolders
        ]);
    }
}
