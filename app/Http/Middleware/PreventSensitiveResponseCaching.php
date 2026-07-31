<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventSensitiveResponseCaching
{
    /**
     * Menambahkan header anti-cache pada respons yang memuat
     * data transaksi, identitas, atau audit sensitif.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $response =
            $next(
                $request,
            );

        /*
         * Cache-Control:
         *
         * private
         * Respons hanya ditujukan kepada pengguna yang sedang login.
         *
         * no-store
         * Browser dan intermediary tidak boleh menyimpan respons.
         *
         * no-cache
         * Respons tidak boleh digunakan kembali tanpa validasi.
         *
         * max-age=0
         * Respons langsung dianggap kedaluwarsa.
         *
         * must-revalidate
         * Cache wajib melakukan validasi ulang setelah kedaluwarsa.
         */
        $response->headers->set(
            'Cache-Control',
            'private, no-store, no-cache, max-age=0, must-revalidate',
        );

        /*
         * Kompatibilitas dengan client dan proxy HTTP lama.
         */
        $response->headers->set(
            'Pragma',
            'no-cache',
        );

        $response->headers->set(
            'Expires',
            '0',
        );

        return $response;
    }
}
