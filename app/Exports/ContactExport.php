<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use App\Models\CompanyContact;
use Maatwebsite\Excel\Concerns\FromCollection;

class ContactExport implements FromView
{
    public $contact;

    public function __construct($accounts)
    {
        $this->contact = $accounts;
    }

    public function view(): View
    {
        return view('csv.contact', [
            'contact' => $this->contact
        ]);
    }
}
