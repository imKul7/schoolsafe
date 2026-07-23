<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="robots"
        content="noindex, nofollow, noarchive"
    >

    <meta
        name="referrer"
        content="no-referrer"
    >

    <title>
        @yield('code') — @yield('title') | SchoolSafe
    </title>

    <style>
        :root {
            color-scheme: light;

            --background-start: #eff6ff;
            --background-end: #f8fafc;
            --surface: rgba(255, 255, 255, 0.94);
            --surface-border: rgba(148, 163, 184, 0.28);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-soft: #dbeafe;
            --focus-ring: rgba(37, 99, 235, 0.28);
            --shadow:
                0 24px 64px rgba(15, 23, 42, 0.12),
                0 8px 24px rgba(15, 23, 42, 0.06);
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: var(--background-end);
        }

        body {
            min-height: 100vh;
            margin: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 32px 20px;

            color: var(--text-primary);

            background:
                radial-gradient(
                    circle at top left,
                    rgba(59, 130, 246, 0.16),
                    transparent 38%
                ),
                radial-gradient(
                    circle at bottom right,
                    rgba(14, 165, 233, 0.11),
                    transparent 34%
                ),
                linear-gradient(
                    145deg,
                    var(--background-start),
                    var(--background-end)
                );

            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            line-height: 1.6;
        }

        .page {
            width: min(100%, 720px);
        }

        .card {
            overflow: hidden;

            border: 1px solid var(--surface-border);
            border-radius: 28px;

            background: var(--surface);
            box-shadow: var(--shadow);

            backdrop-filter: blur(18px);
        }

        .accent {
            height: 6px;

            background:
                linear-gradient(
                    90deg,
                    #2563eb,
                    #0ea5e9,
                    #38bdf8
                );
        }

        .content {
            padding: clamp(32px, 7vw, 64px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;

            margin-bottom: 36px;

            color: var(--text-primary);
            text-decoration: none;
        }

        .brand-mark {
            width: 44px;
            height: 44px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            flex: 0 0 auto;

            border-radius: 14px;

            color: #ffffff;
            background:
                linear-gradient(
                    145deg,
                    #2563eb,
                    #0284c7
                );

            box-shadow:
                0 10px 24px rgba(37, 99, 235, 0.24);

            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 750;
            letter-spacing: -0.025em;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 9px;

            margin-bottom: 18px;
            padding: 7px 12px;

            border: 1px solid rgba(37, 99, 235, 0.16);
            border-radius: 999px;

            color: #1d4ed8;
            background: var(--primary-soft);

            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .status-dot {
            width: 8px;
            height: 8px;

            flex: 0 0 auto;

            border-radius: 999px;

            background: var(--primary);
            box-shadow:
                0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .error-code {
            margin: 0 0 6px;

            color: var(--primary);

            font-size: clamp(48px, 12vw, 88px);
            font-weight: 850;
            line-height: 1;
            letter-spacing: -0.065em;
        }

        .title {
            margin: 0;

            color: var(--text-primary);

            font-size: clamp(28px, 5vw, 42px);
            font-weight: 800;
            line-height: 1.16;
            letter-spacing: -0.045em;
        }

        .message {
            max-width: 580px;
            margin: 20px 0 0;

            color: var(--text-secondary);

            font-size: 17px;
        }

        .guidance {
            max-width: 580px;
            margin: 12px 0 0;

            color: var(--text-muted);

            font-size: 14px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;

            margin-top: 32px;
        }

        .button {
            min-height: 46px;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 11px 18px;

            border: 1px solid transparent;
            border-radius: 13px;

            font-size: 14px;
            font-weight: 750;
            line-height: 1;
            text-decoration: none;

            cursor: pointer;

            transition:
                transform 150ms ease,
                background-color 150ms ease,
                border-color 150ms ease,
                box-shadow 150ms ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button:focus-visible {
            outline: none;

            box-shadow:
                0 0 0 4px var(--focus-ring);
        }

        .button-primary {
            color: #ffffff;
            background: var(--primary);

            box-shadow:
                0 10px 22px rgba(37, 99, 235, 0.22);
        }

        .button-primary:hover {
            background: var(--primary-hover);
        }

        .button-secondary {
            color: var(--text-primary);

            border-color: rgba(148, 163, 184, 0.4);
            background: #ffffff;
        }

        .button-secondary:hover {
            border-color: rgba(100, 116, 139, 0.5);
            background: #f8fafc;
        }

        .footer {
            margin-top: 36px;
            padding-top: 24px;

            border-top: 1px solid rgba(148, 163, 184, 0.22);

            color: var(--text-muted);

            font-size: 13px;
        }

        .footer strong {
            color: var(--text-secondary);
        }

        @media (max-width: 520px) {
            body {
                align-items: flex-start;

                padding: 16px;
            }

            .card {
                border-radius: 22px;
            }

            .content {
                padding: 28px 24px;
            }

            .brand {
                margin-bottom: 28px;
            }

            .actions {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
            }
        }

        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;

                --background-start: #07111f;
                --background-end: #0f172a;
                --surface: rgba(15, 23, 42, 0.92);
                --surface-border: rgba(148, 163, 184, 0.2);
                --text-primary: #f8fafc;
                --text-secondary: #cbd5e1;
                --text-muted: #94a3b8;
                --primary: #60a5fa;
                --primary-hover: #3b82f6;
                --primary-soft: rgba(37, 99, 235, 0.16);
                --focus-ring: rgba(96, 165, 250, 0.28);
                --shadow:
                    0 24px 72px rgba(0, 0, 0, 0.32),
                    0 8px 24px rgba(0, 0, 0, 0.18);
            }

            body {
                background:
                    radial-gradient(
                        circle at top left,
                        rgba(37, 99, 235, 0.18),
                        transparent 38%
                    ),
                    radial-gradient(
                        circle at bottom right,
                        rgba(14, 165, 233, 0.12),
                        transparent 34%
                    ),
                    linear-gradient(
                        145deg,
                        var(--background-start),
                        var(--background-end)
                    );
            }

            .status {
                color: #bfdbfe;
            }

            .button-secondary {
                color: var(--text-primary);

                border-color: rgba(148, 163, 184, 0.28);
                background: rgba(30, 41, 59, 0.82);
            }

            .button-secondary:hover {
                border-color: rgba(148, 163, 184, 0.42);
                background: rgba(51, 65, 85, 0.9);
            }
        }
    </style>
</head>

<body>
    <main
        class="page"
        aria-labelledby="error-title"
    >
        <section class="card">
            <div
                class="accent"
                aria-hidden="true"
            ></div>

            <div class="content">
                <a
                    class="brand"
                    href="/"
                    aria-label="Kembali ke halaman utama SchoolSafe"
                >
                    <span
                        class="brand-mark"
                        aria-hidden="true"
                    >
                        SS
                    </span>

                    <span class="brand-name">
                        SchoolSafe
                    </span>
                </a>

                <div class="status">
                    <span
                        class="status-dot"
                        aria-hidden="true"
                    ></span>

                    <span>
                        @yield('status', 'Informasi sistem')
                    </span>
                </div>

                <p
                    class="error-code"
                    aria-hidden="true"
                >
                    @yield('code')
                </p>

                <h1
                    id="error-title"
                    class="title"
                >
                    @yield('title')
                </h1>

                <p class="message">
                    @yield('message')
                </p>

                @hasSection('guidance')
                    <p class="guidance">
                        @yield('guidance')
                    </p>
                @endif

                <div class="actions">
                    <a
                        class="button button-primary"
                        href="/"
                    >
                        Kembali ke halaman utama
                    </a>

                    <button
                        class="button button-secondary"
                        type="button"
                        onclick="window.location.reload()"
                    >
                        Muat ulang halaman
                    </button>
                </div>

                <footer class="footer">
                    <strong>SchoolSafe</strong>
                    membantu sekolah mengelola proses penjemputan
                    secara aman dan tertib.
                </footer>
            </div>
        </section>
    </main>
</body>
</html>