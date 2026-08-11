<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ThemePreferenceController extends Controller
{
    /** Persist an authenticated user's visual preference without touching workspace data. */
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        $request->user()->forceFill([
            'theme_preference' => $data['theme'],
        ])->save();

        return response()->noContent();
    }
}
