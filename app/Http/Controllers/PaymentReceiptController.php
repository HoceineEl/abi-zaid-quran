<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentReceiptController extends Controller
{
    public function show(Request $request)
    {
        $payments = Payment::whereIn('id', explode(',', $request->payments))->get();

        if ($payments->isEmpty()) {
            abort(404);
        }

        return view('payments.print-receipt', [
            'payments' => $payments,
            'autoPrint' => true,
        ]);
    }
}
