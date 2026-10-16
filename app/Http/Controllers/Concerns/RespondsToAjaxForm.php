<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RespondsToAjaxForm
{
    protected function formSuccess(Request $request, string $redirectUrl, string $message): RedirectResponse|JsonResponse
    {
        if ($this->wantsAjaxForm($request)) {
            $request->session()->flash('success', $message);

            return response()->json([
                'message' => $message,
                'redirect' => $redirectUrl,
            ]);
        }

        return redirect()
            ->to($redirectUrl)
            ->with('success', $message);
    }

    protected function wantsAjaxForm(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';
    }
}
