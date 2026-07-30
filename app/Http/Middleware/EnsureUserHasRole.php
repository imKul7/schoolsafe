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
    /**
     * Memastikan pengguna memiliki salah satu role yang diizinkan.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
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
            $message =
                'Akun tidak memiliki izin mengakses fitur ini.';

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

        return $next($request);
    }
}
