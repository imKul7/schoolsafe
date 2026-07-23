<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\GateFaceVerificationController;
use App\Http\Controllers\GatePickupEventController;
use App\Http\Middleware\PreventSensitiveResponseCaching;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GateRouteIntegrityTest extends TestCase
{
    public function test_required_gate_routes_are_registered_exactly_once_with_strict_contracts(): void
    {
        $expectedContracts = [
            'gate.face-verification.index' => [
                'uri' =>
                    'gate/face-verification',

                'methods' => [
                    'GET',
                    'HEAD',
                ],

                'action' =>
                    GateFaceVerificationController::class
                    .'@index',

                'middleware' => [
                    'auth',
                ],
            ],

            'gate.face-verification.challenge' => [
                'uri' =>
                    'gate/face-verification/challenge',

                'methods' => [
                    'POST',
                ],

                'action' =>
                    GateFaceVerificationController::class
                    .'@challenge',

                'middleware' => [
                    'auth',
                    'throttle:20,1',
                ],
            ],

            'gate.face-verification.verify' => [
                'uri' =>
                    'gate/face-verification',

                'methods' => [
                    'POST',
                ],

                'action' =>
                    GateFaceVerificationController::class
                    .'@verify',

                'middleware' => [
                    'auth',
                    'throttle:30,1',
                ],
            ],

            'gate.pickup-events.index' => [
                'uri' =>
                    'gate/pickup-events',

                'methods' => [
                    'GET',
                    'HEAD',
                ],

                'action' =>
                    GatePickupEventController::class
                    .'@index',

                'middleware' => [
                    'auth',
                ],
            ],

            'gate.pickup-events.store' => [
                'uri' =>
                    'gate/pickup-events',

                'methods' => [
                    'POST',
                ],

                'action' =>
                    GatePickupEventController::class
                    .'@store',

                'middleware' => [
                    'auth',
                    'throttle:30,1',
                ],
            ],

            'gate.pickup-events.students.cancel' => [
                'uri' =>
                    'gate/pickup-events/'
                    .'{pickupEvent}/students/'
                    .'{pickupEventStudent}/cancel',

                'methods' => [
                    'PATCH',
                ],

                'action' =>
                    GatePickupEventController::class
                    .'@cancelStudent',

                'middleware' => [
                    'auth',
                    'throttle:20,1',
                ],
            ],

            'gate.pickup-events.cancel' => [
                'uri' =>
                    'gate/pickup-events/'
                    .'{pickupEvent}/cancel',

                'methods' => [
                    'PATCH',
                ],

                'action' =>
                    GatePickupEventController::class
                    .'@cancel',

                'middleware' => [
                    'auth',
                    'throttle:20,1',
                ],
            ],

            'gate.pickup-events.show' => [
                'uri' =>
                    'gate/pickup-events/'
                    .'{pickupEvent}',

                'methods' => [
                    'GET',
                    'HEAD',
                ],

                'action' =>
                    GatePickupEventController::class
                    .'@show',

                'middleware' => [
                    'auth',
                    'throttle:60,1',
                ],
            ],
        ];

        $registeredRoutes =
            collect(
                Route::getRoutes()
                    ->getRoutes(),
            );

        foreach (
            $expectedContracts as
            $routeName =>
            $expectedContract
        ) {
            $matchingRoutes =
                $registeredRoutes
                    ->filter(
                        fn (
                            LaravelRoute $route,
                        ): bool =>
                            $route->getName()
                            === $routeName,
                    )
                    ->values();

            $this->assertCount(
                1,
                $matchingRoutes,
                sprintf(
                    'Route [%s] harus terdaftar tepat satu kali.',
                    $routeName,
                ),
            );

            $route =
                $matchingRoutes->first();

            $this->assertInstanceOf(
                LaravelRoute::class,
                $route,
            );

            $this->assertSame(
                $expectedContract[
                    'uri'
                ],
                $route->uri(),
                sprintf(
                    'URI route [%s] berubah.',
                    $routeName,
                ),
            );

            $actualMethods =
                $route->methods();

            $expectedMethods =
                $expectedContract[
                    'methods'
                ];

            sort(
                $actualMethods,
            );

            sort(
                $expectedMethods,
            );

            $this->assertSame(
                $expectedMethods,
                $actualMethods,
                sprintf(
                    'HTTP method route [%s] berubah.',
                    $routeName,
                ),
            );

            $this->assertSame(
                $expectedContract[
                    'action'
                ],
                $route->getActionName(),
                sprintf(
                    'Controller action route [%s] berubah.',
                    $routeName,
                ),
            );

            $actualMiddleware =
                $route->middleware();

            foreach (
                $expectedContract[
                    'middleware'
                ] as
                $expectedMiddleware
            ) {
                $this->assertContains(
                    $expectedMiddleware,
                    $actualMiddleware,
                    sprintf(
                        'Route [%s] kehilangan middleware [%s].',
                        $routeName,
                        $expectedMiddleware,
                    ),
                );
            }
        }
    }

    public function test_gate_route_names_are_unique(): void
    {
        $duplicateNames =
            $this->gateRoutes()
                ->filter(
                    fn (
                        LaravelRoute $route,
                    ): bool =>
                        is_string(
                            $route->getName(),
                        )
                        && $route->getName() !== '',
                )
                ->groupBy(
                    fn (
                        LaravelRoute $route,
                    ): string =>
                        (string) $route->getName(),
                )
                ->filter(
                    fn (
                        Collection $routes,
                    ): bool =>
                        $routes->count() > 1,
                )
                ->keys()
                ->values()
                ->all();

        $this->assertSame(
            [],
            $duplicateNames,
            sprintf(
                'Ditemukan nama route gerbang ganda: %s',
                implode(
                    ', ',
                    $duplicateNames,
                ),
            ),
        );
    }

    public function test_gate_method_and_uri_combinations_are_unique(): void
    {
        $routeSignatures =
            $this->gateRoutes()
                ->flatMap(
                    function (
                        LaravelRoute $route,
                    ): array {
                        return collect(
                            $route->methods(),
                        )
                            ->map(
                                fn (
                                    string $method,
                                ): string =>
                                    sprintf(
                                        '%s %s',
                                        $method,
                                        $route->uri(),
                                    ),
                            )
                            ->all();
                    },
                );

        $duplicateSignatures =
            $routeSignatures
                ->countBy()
                ->filter(
                    fn (
                        int $count,
                    ): bool =>
                        $count > 1,
                )
                ->keys()
                ->values()
                ->all();

        $this->assertSame(
            [],
            $duplicateSignatures,
            sprintf(
                'Ditemukan kombinasi method dan URI gerbang ganda: %s',
                implode(
                    ', ',
                    $duplicateSignatures,
                ),
            ),
        );
    }

    public function test_pickup_event_store_and_face_verification_routes_are_not_missing_or_duplicated(): void
    {
        $gateRoutes =
            $this->gateRoutes();

        $pickupEventStoreRoutes =
            $gateRoutes
                ->filter(
                    fn (
                        LaravelRoute $route,
                    ): bool =>
                        $route->uri()
                        === 'gate/pickup-events'
                        && in_array(
                            'POST',
                            $route->methods(),
                            true,
                        ),
                )
                ->values();

        $this->assertCount(
            1,
            $pickupEventStoreRoutes,
            'POST /gate/pickup-events harus terdaftar tepat satu kali.',
        );

        $faceVerificationContracts = [
            [
                'method' =>
                    'GET',

                'uri' =>
                    'gate/face-verification',
            ],
            [
                'method' =>
                    'POST',

                'uri' =>
                    'gate/face-verification/challenge',
            ],
            [
                'method' =>
                    'POST',

                'uri' =>
                    'gate/face-verification',
            ],
        ];

        foreach (
            $faceVerificationContracts as
            $contract
        ) {
            $matchingRoutes =
                $gateRoutes
                    ->filter(
                        fn (
                            LaravelRoute $route,
                        ): bool =>
                            $route->uri()
                            === $contract[
                                'uri'
                            ]
                            && in_array(
                                $contract[
                                    'method'
                                ],
                                $route->methods(),
                                true,
                            ),
                    )
                    ->values();

            $this->assertCount(
                1,
                $matchingRoutes,
                sprintf(
                    '%s /%s harus terdaftar tepat satu kali.',
                    $contract[
                        'method'
                    ],
                    $contract[
                        'uri'
                    ],
                ),
            );
        }
    }

    public function test_dynamic_gate_identifiers_have_explicit_route_constraints(): void
{
    $expectedRouteParameters = [
        'gate.pickup-events.show' => [
            'pickupEvent',
        ],

        'gate.pickup-events.cancel' => [
            'pickupEvent',
        ],

        'gate.pickup-events.students.cancel' => [
            'pickupEvent',
            'pickupEventStudent',
        ],
    ];

    $registeredRoutes =
        collect(
            Route::getRoutes()
                ->getRoutes(),
        );

    foreach (
        $expectedRouteParameters as
        $routeName =>
        $expectedParameters
    ) {
        $route =
            $registeredRoutes
                ->first(
                    fn (
                        LaravelRoute $registeredRoute,
                    ): bool =>
                        $registeredRoute->getName()
                        === $routeName,
                );

        $this->assertInstanceOf(
            LaravelRoute::class,
            $route,
            sprintf(
                'Route [%s] tidak ditemukan.',
                $routeName,
            ),
        );

        foreach (
            $expectedParameters as
            $expectedParameter
        ) {
            $this->assertArrayHasKey(
                $expectedParameter,
                $route->wheres,
                sprintf(
                    'Parameter [%s] pada route [%s] tidak memiliki constraint.',
                    $expectedParameter,
                    $routeName,
                ),
            );

            $this->assertIsString(
                $route->wheres[
                    $expectedParameter
                ],
            );

            $this->assertNotSame(
                '',
                trim(
                    $route->wheres[
                        $expectedParameter
                    ],
                ),
                sprintf(
                    'Constraint [%s] pada route [%s] tidak boleh kosong.',
                    $expectedParameter,
                    $routeName,
                ),
            );
        }
    }
}

public function test_gate_identifier_constraints_accept_only_canonical_positive_64_bit_integers(): void
{
    $route =
        collect(
            Route::getRoutes()
                ->getRoutes(),
        )
            ->first(
                fn (
                    LaravelRoute $registeredRoute,
                ): bool =>
                    $registeredRoute->getName()
                    === 'gate.pickup-events.students.cancel',
            );

    $this->assertInstanceOf(
        LaravelRoute::class,
        $route,
    );

    $parameterNames = [
        'pickupEvent',
        'pickupEventStudent',
    ];

    $acceptedIdentifiers = [
        '1',
        '9',
        '10',
        '123',
        '999999999999999999',
        '9223372036854775807',
    ];

    $rejectedIdentifiers = [
        '',
        '0',
        '00',
        '01',
        '000123',
        '-1',
        '+1',
        '1.0',
        '1e3',
        '1E3',
        '0x10',
        '1_000',
        '1,000',
        '9223372036854775808',
        '9999999999999999999',
        '9999999999999999999999999999999999999999999999999999999999999999',
        '１２３',
        '١٢٣',
    ];

    foreach (
        $parameterNames as
        $parameterName
    ) {
        $this->assertArrayHasKey(
            $parameterName,
            $route->wheres,
        );

        $constraint =
            $route->wheres[
                $parameterName
            ];

        $regularExpression =
            sprintf(
                '~^(?:%s)$~D',
                $constraint,
            );

        foreach (
            $acceptedIdentifiers as
            $acceptedIdentifier
        ) {
            $this->assertSame(
                1,
                preg_match(
                    $regularExpression,
                    $acceptedIdentifier,
                ),
                sprintf(
                    'Constraint [%s] seharusnya menerima identifier canonical [%s].',
                    $parameterName,
                    $acceptedIdentifier,
                ),
            );
        }

        foreach (
            $rejectedIdentifiers as
            $rejectedIdentifier
        ) {
            $this->assertSame(
                0,
                preg_match(
                    $regularExpression,
                    $rejectedIdentifier,
                ),
                sprintf(
                    'Constraint [%s] tidak boleh menerima identifier noncanonical [%s].',
                    $parameterName,
                    $rejectedIdentifier,
                ),
            );
        }
    }
}

public function test_all_pickup_event_routes_have_sensitive_response_cache_middleware(): void
{
    $expectedRouteNames = [
        'gate.pickup-events.index',
        'gate.pickup-events.store',
        'gate.pickup-events.students.cancel',
        'gate.pickup-events.cancel',
        'gate.pickup-events.show',
    ];

    $registeredRoutes =
        collect(
            Route::getRoutes()
                ->getRoutes(),
        );

    foreach (
        $expectedRouteNames as
        $expectedRouteName
    ) {
        $matchingRoutes =
            $registeredRoutes
                ->filter(
                    fn (
                        LaravelRoute $route,
                    ): bool =>
                        $route->getName()
                        === $expectedRouteName,
                )
                ->values();

        $this->assertCount(
            1,
            $matchingRoutes,
            sprintf(
                'Route [%s] harus terdaftar tepat satu kali.',
                $expectedRouteName,
            ),
        );

        $route =
            $matchingRoutes->first();

        $this->assertInstanceOf(
            LaravelRoute::class,
            $route,
        );

        $actualMiddleware =
            $route->middleware();

        $this->assertContains(
            PreventSensitiveResponseCaching::class,
            $actualMiddleware,
            sprintf(
                'Route [%s] wajib menggunakan middleware [%s]. Middleware aktual: [%s].',
                $expectedRouteName,
                PreventSensitiveResponseCaching::class,
                implode(
                    ', ',
                    $actualMiddleware,
                ),
            ),
        );
    }
}

public function test_sensitive_response_cache_middleware_is_scoped_only_to_pickup_event_routes(): void
{
    $expectedProtectedRouteNames = [
        'gate.pickup-events.cancel',
        'gate.pickup-events.index',
        'gate.pickup-events.show',
        'gate.pickup-events.store',
        'gate.pickup-events.students.cancel',
    ];

    $protectedRoutes =
        collect(
            Route::getRoutes()
                ->getRoutes(),
        )
            ->filter(
                fn (
                    LaravelRoute $route,
                ): bool =>
                    in_array(
                        PreventSensitiveResponseCaching::class,
                        $route->middleware(),
                        true,
                    ),
            )
            ->values();

    /*
     * Nama route digunakan untuk memastikan middleware tidak
     * menyebar ke dashboard, autentikasi, data siswa, data
     * penjemput, atau route verifikasi wajah.
     *
     * Route tanpa nama tetap dimunculkan sebagai signature
     * method dan URI sehingga tidak dapat tersembunyi.
     */
    $actualProtectedRouteNames =
        $protectedRoutes
            ->map(
                function (
                    LaravelRoute $route,
                ): string {
                    $routeName =
                        $route->getName();

                    if (
                        is_string(
                            $routeName,
                        )
                        && $routeName !== ''
                    ) {
                        return $routeName;
                    }

                    return sprintf(
                        '%s %s',
                        implode(
                            '|',
                            $route->methods(),
                        ),
                        $route->uri(),
                    );
                },
            )
            ->sort()
            ->values()
            ->all();

    $expectedProtectedRouteNames =
        collect(
            $expectedProtectedRouteNames,
        )
            ->sort()
            ->values()
            ->all();

    $this->assertSame(
        $expectedProtectedRouteNames,
        $actualProtectedRouteNames,
        sprintf(
            'Middleware [%s] hanya boleh digunakan oleh route transaksi penjemputan. Route aktual: [%s].',
            PreventSensitiveResponseCaching::class,
            implode(
                ', ',
                $actualProtectedRouteNames,
            ),
        ),
    );
}

    /**
     * @return Collection<int, LaravelRoute>
     */
    private function gateRoutes(): Collection
    {
        return collect(
            Route::getRoutes()
                ->getRoutes(),
        )
            ->filter(
                fn (
                    LaravelRoute $route,
                ): bool =>
                    $route->uri() === 'gate'
                    || str_starts_with(
                        $route->uri(),
                        'gate/',
                    ),
            )
            ->values();
    }
}