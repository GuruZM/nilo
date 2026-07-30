<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseInquiry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnterpriseInquiryController extends Controller
{
    public function create(): \Inertia\Response
    {
        return Inertia::render('subscription/enterprise');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        EnterpriseInquiry::create($request->only([
            'name', 'email', 'phone', 'company_name', 'message',
        ]));

        return back()->with('success', 'Thank you for your inquiry! We will get back to you soon.');
    }
}
