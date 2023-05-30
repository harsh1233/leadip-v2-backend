<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class ListContactsExport implements FromView
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public $contacts;
    public $listname;

    public function __construct($contacts,$listname)
    {
        $this->contacts = $contacts;
        $this->listname = $listname;
    }

    public function view(): View
    {
        return view('exports.listcontact', [
            'contacts' => $this->contacts,
            'listname' => $this->listname
        ]);
    }
}
