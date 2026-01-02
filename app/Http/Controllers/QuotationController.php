<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quotation;
use Inertia\Inertia;

class QuotationController extends Controller
{
    /**
     * Display a listing of the quotations.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // You may want to filter quotations by company or user
        $quotations = Quotation::where('user_id', $user->id)->get(['id', 'number', 'amount', 'status', 'created_at']);
        return Inertia::render('Quotations/Index', [
            'quotations' => $quotations,
        ]);
    }

    /**
     * Show the form for creating a new quotation.
     */
    public function create()
    {
        return Inertia::render('Quotations/Create');
    }
}
