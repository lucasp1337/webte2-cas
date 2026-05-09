<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SetLocaleController extends Controller
{
    /** @var list<string> */
    private const array SUPPORTED_LOCALES = ['sk', 'en'];

    private const int COOKIE_LIFETIME_MINUTES = 60 * 24 * 365;

    public function __invoke(Request $request): RedirectResponse
    {
        /** @var array{locale: string} $validated */
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(self::SUPPORTED_LOCALES)],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return back()->withCookie(cookie('locale', $validated['locale'], self::COOKIE_LIFETIME_MINUTES));
    }
}
