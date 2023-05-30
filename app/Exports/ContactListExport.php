<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class ContactListExport implements FromView
{
    public $contactlist;

    public function __construct($contactlist)
    {
        $this->contactlist = $contactlist;
    }

    public function view(): View
    {
        return view('exports.contactlist', [
            'contactlist' => $this->contactlist
        ]);
    }
}

