<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tickets = SupportTicket::query()
            ->with(['creator', 'assignedAdmin', 'assignedSubAdmin'])
            ->where('business_id', $user->business_id)
            ->when(!in_array($user->role, ['business_owner', 'business_admin', 'manager'], true), fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->paginate(15);

        return view('business.support.index', compact('tickets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'in:Low,Medium,High'],
        ]);

        $user = $request->user();
        $ticket = SupportTicket::create([
            'business_id' => $user->business_id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'Support',
            'subject' => $data['subject'],
            'message' => $data['message'],
            'priority' => $data['priority'],
            'status' => 'Open',
        ]);

        $ticket->update([
            'ticket_number' => 'TF-TKT-'.now()->format('Ymd').'-'.str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT),
        ]);

        return redirect()->route('business.support')->with('success', 'Your support ticket has been submitted.');
    }
}
