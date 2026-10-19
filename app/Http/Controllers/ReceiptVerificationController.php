<?php

namespace App\Http\Controllers;

use App\Services\ReceiptVerificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReceiptVerificationController extends Controller
{
    public function __construct(
        protected ReceiptVerificationService $verification,
    ) {}

    public function show(Request $request, string $kind, string $id, string $purpose): View
    {
        if (! in_array($kind, ReceiptVerificationService::kinds(), true)
            || ! in_array($purpose, ReceiptVerificationService::purposes(), true)
            || ! ctype_digit((string) $id)) {
            throw new NotFoundHttpException;
        }

        $payload = $this->verification->verifyPayload($kind, (int) $id, $purpose);

        return view('operations.receipts.verify', [
            'payload' => $payload,
        ]);
    }
}
