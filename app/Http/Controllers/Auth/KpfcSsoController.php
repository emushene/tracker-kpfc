<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Sso\KpfcSsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KpfcSsoController extends Controller
{
    public function __construct(
        protected KpfcSsoService $sso,
    ) {}

    /**
     * Show the login view with the KPFC Admin SSO sign-in button.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended('/');
        }

        return view('auth.login');
    }

    /**
     * Redirect the user to the KPFC Admin OAuth authorization endpoint.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $authData = $this->sso->createAuthorizationRequest($request);

        return redirect()->away($authData['url']);
    }

    /**
     * Handle the OAuth callback from KPFC Admin.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $this->sso->handleCallback($request);

            return redirect()->intended('/')->with('status', 'Signed in successfully via KPFC Admin.');
        } catch (HttpException $e) {
            return redirect()->route('login')->withErrors(['sso' => $e->getMessage()]);
        }
    }

    /**
     * Log the user out of Fleet, revoke the token, and terminate the session.
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            $this->sso->logout($user, $request);
        }

        return redirect()->route('login')->with('status', 'Signed out successfully.');
    }

    /**
     * Handle incoming signed user lifecycle events from KPFC Admin.
     */
    public function webhook(Request $request): JsonResponse
    {
        $result = $this->sso->handleWebhook($request);

        return response()->json($result, 200);
    }
}
