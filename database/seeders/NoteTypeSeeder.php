<?php

namespace Database\Seeders;

use App\Models\NoteType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Str;
use Illuminate\Support\Facades\Storage;
use File;
class NoteTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        
        $noteType = [
            [   
                'id'                =>Str::uuid(),
                'name'              => 'Meeting',
                'icon_url'          =>'meeting.svg',

            ],
            [
                'id'                =>Str::uuid(),
                'name'              => 'Arrangement',
                'icon_url'          =>'tv.svg',

            ],
            [
                'id'                =>Str::uuid(),
                'name'              => 'Incident',
                'icon_url'          => 'incident.svg',

            ],
        ];
        NoteType::insert($noteType);
        
    }
}
