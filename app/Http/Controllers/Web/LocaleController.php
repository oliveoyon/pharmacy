<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function __invoke(string $locale): RedirectResponse
    {
        if (! in_array($locale, ['en', 'bn'], true)) {
            $locale = 'en';
        }

        session(['locale' => $locale]);

        return back();
    }
}

