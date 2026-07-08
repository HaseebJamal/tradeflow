<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        SupportTicket::create([
            'subject' => 'Website inquiry from '.$data['name'],
            'message' => trim(($data['email'] ?? '').' '.($data['phone'] ?? '')."\n\n".$data['message']),
            'priority' => 'Medium',
            'status' => 'Open',
        ]);

        return back()->with('success', 'Your message has been saved. Our team can review it from support tickets.');
    }
}
