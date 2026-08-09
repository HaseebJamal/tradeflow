<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $fingerprint = 'public-contact:'.hash('sha256', strtolower($data['email']).'|'.$data['phone'].'|'.trim($data['message']));
        if (! Cache::add($fingerprint, true, now()->addMinutes(2))) {
            return back()->withInput()->withErrors(['message' => 'This message was recently sent. Please wait before sending it again.']);
        }

        $ticket = SupportTicket::create([
            'contact_name' => trim($data['name']),
            'contact_email' => strtolower(trim($data['email'])),
            'contact_phone' => $data['phone'],
            'source' => 'Public Contact',
            'submitted_at' => now(),
            'type' => 'General Inquiry',
            'subject' => 'Website inquiry from '.$data['name'],
            'message' => trim($data['message']),
            'priority' => 'Medium',
            'status' => 'Open',
        ]);
        $ticket->update([
            'ticket_number' => 'TF-TKT-'.now()->format('Ymd').'-'.str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT),
        ]);

        return back()->with('success', 'Thank you. Your message has been sent to our support team.');
    }
}
