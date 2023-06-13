<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Language;
use Illuminate\Support\Str;

class LanguageController extends Controller
{
     /**
     * get laguages api
     *
     * @param  mixed $request
     * @return void
     */
    public function list(Request $request)
    {
        // Get All Languages
        $languages = Language::get();
        return ok(__('Language list successfully!'), $languages);
    }
}
