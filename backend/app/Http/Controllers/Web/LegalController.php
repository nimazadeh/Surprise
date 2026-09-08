<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class LegalController extends Controller
{
    public function terms()
    {
        return view('web.legal.terms');
    }

    public function privacy()
    {
        return view('web.legal.privacy');
    }

    public function takedown()
    {
        return view('web.legal.takedown');
    }
}
