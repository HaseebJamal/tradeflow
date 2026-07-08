<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class BusinessOnboardingController extends Controller
{
    public function create() { return view('onboarding.register-business'); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', 'min:8'],
            'business_type' => ['required', Rule::in(['Manufacturer', 'Distributor', 'Wholesaler', 'Retail Shop'])],
            'business_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'cnic_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'business_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'shop_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        DB::transaction(function () use ($request, $data, &$user) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'business_owner',
                'status' => 'active',
            ]);

            $business = Business::create([
                'owner_id' => $user->id,
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'category' => $data['category'] ?? null,
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'],
                'registration_number' => $data['registration_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'status' => 'Pending',
            ]);

            $user->update(['business_id' => $business->id]);

            foreach (['cnic_image', 'business_document', 'shop_image'] as $field) {
                if ($request->hasFile($field)) {
                    BusinessDocument::create([
                        'business_id' => $business->id,
                        'document_type' => $field,
                        'file_path' => $request->file($field)->store('business-documents', 'public'),
                    ]);
                }
            }
        });

        return redirect()->route('register.business')->with('success', 'Your business registration has been submitted for approval.');
    }
}
