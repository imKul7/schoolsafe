<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserHasRole
{
    private const DEFAULT_CONTEXT = 'default';

    /**
     * @var array<string, string>
     */
    private const DENIAL_MESSAGES = [
        self::DEFAULT_CONTEXT => 'Akun tidak memiliki izin mengakses fitur ini.',

        'gate' => 'Akun tidak memiliki izin mengelola transaksi gerbang.',

        'students' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.',

        'pickup-persons' => 'Anda tidak memiliki izin melihat data penjemput.',
    ];

    /**
     * Memastikan pengguna memiliki salah satu role yang diizinkan.
     *
     * Parameter pertama merupakan konteks pesan penolakan.
     * Parameter berikutnya merupakan daftar role yang diizinkan.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $context = self::DEFAULT_CONTEXT,
        string ...$roles,
    ): Response {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $allowedRoles = array_values(
            array_filter(
                array_map(
                    static fn (string $role): string => trim($role),
                    $roles,
                ),
                static fn (string $role): bool => $role !== '',
            ),
        );

        if (
            $allowedRoles === []
            || ! $user->hasRole(...$allowedRoles)
        ) {
            return $this->forbiddenResponse(
                $request,
                self::DENIAL_MESSAGES[$context]
                    ?? self::DENIAL_MESSAGES[self::DEFAULT_CONTEXT],
            );
        }

        return $next($request);
    }

    private function forbiddenResponse(
        Request $request,
        string $message,
    ): Response {
        if ($request->expectsJson()) {
            return new JsonResponse(
                [
                    'message' => $message,
                ],
                Response::HTTP_FORBIDDEN,
            );
        }

        abort(
            Response::HTTP_FORBIDDEN,
            $message,
        );
    }
}
