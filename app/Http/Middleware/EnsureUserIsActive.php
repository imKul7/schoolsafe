<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! (bool) $user->is_active) {
            return $this->terminateSession(
                $request,
                'Akun Anda telah dinonaktifkan.',
            );
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->school_id === null) {
            return $this->terminateSession(
                $request,
                'Akun Anda belum terhubung dengan sekolah.',
            );
        }

        $user->loadMissing('school');

        if ($user->school === null) {
            return $this->terminateSession(
                $request,
                'Data sekolah untuk akun Anda tidak ditemukan.',
            );
        }

        if (! (bool) $user->school->is_active) {
            return $this->terminateSession(
                $request,
                'Sekolah Anda telah dinonaktifkan.',
            );
        }

        return $next($request);
    }

    private function terminateSession(
        Request $request,
        string $message,
    ): Response {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(
                [
                    'message' => $message,
                ],
                401,
            );
        }

        return redirect()
            ->route('login')
            ->with('status', $message);
    }
}