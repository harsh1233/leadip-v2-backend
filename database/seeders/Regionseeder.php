<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Support\Str;

class Regionseeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $regions = [
            [
                'id'                => Str::uuid(),
                'name'              => 'Western Europe',
                'created_at'        => Carbon::now(),
                'created_by'        => 1
            ],
            [
                'id'                => Str::uuid(),
                'name'              => 'Central and Eastern Europe',
                'created_at'        => Carbon::now(),
                'created_by'        => 1
            ],
            [
                'id'                => Str::uuid(),
                'name'              => 'Asia',
                'created_at'        => Carbon::now(),
                'created_by'        => 1
            ],
            [
                'id'                => Str::uuid(),
                'name'              => 'Africa',
                'created_at'        => Carbon::now(),
                'created_by'        => 1
            ],
            [
                'id'                => Str::uuid(),
                'name'              => 'Mediterranean & Middle East',
                'created_at'        => Carbon::now(),
                'created_by'        => 1
            ],
            [
                'id'                => Str::uuid(),
                'name'              => 'Americas',
                'created_at'        => Carbon::now(),
                'created_by'        => 1
            ]
        ];
        Region::insert($regions);
    }
}
