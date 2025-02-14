<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PaymentController extends Controller
{
    public function downloadReceipt(Request $request, ReceiptService $receiptService)
    {
        $payments = Payment::whereIn('id', $request->payments)->get();

        if ($payments->isEmpty()) {
            abort(404, 'Payments not found');
        }

        $pdfContent = $receiptService->generatePdf($payments);

        $filename = 'receipt_' . $payments->first()->id . '_' . now()->format('Y_m_d') . '.pdf';

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
