<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnterpriseInquiry;
use Inertia\Inertia;

class InquiryController extends Controller
{
    public function index(): \Inertia\Response
    {
        $inquiries = EnterpriseInquiry::latest()->paginate(20);

        return Inertia::render('admin/inquiries/index', [
            'inquiries' => $inquiries,
        ]);
    }

    public function handle(EnterpriseInquiry $inquiry): \Illuminate\Http\RedirectResponse
    {
        $inquiry->update(['is_handled' => true]);

        return back()->with('success', 'Inquiry marked as handled.');
    }
}
