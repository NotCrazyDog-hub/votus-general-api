<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('images/ivy.png') }}">

    <title>Votus | API</title>
    <style>
        :root {
            /* Paleta extraída da logo */
            --green: #4a6f2c;
            --yellow: #f9b515;
            --orange: #ff7a00;
            --orange-soft: #f78e31;
            --maroon: #8a1a0c;

            --bg-from: #fffdf7;
            --bg-to: #fff4e3;
            --text: #2f2a1f;
            --muted: #6b6455;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: linear-gradient(135deg, var(--bg-from), var(--bg-to)) fixed;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            font-family: 'Montserrat', sans-serif;
            color: var(--text);
            line-height: 1.6;
        }

        /* Camada fixa que recorta as manchas, evitando bordas/rolagem */
        .bg {
            position: fixed;
            inset: 0;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }

        /* Manchas de cor ao fundo, nos tons da logo */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.35;
        }

        .blob-1 { width: 420px; height: 420px; background: var(--yellow);      top: -140px;  left: -100px; }
        .blob-2 { width: 380px; height: 380px; background: var(--green);       bottom: -140px; right: -100px; opacity: 0.28; }
        .blob-3 { width: 300px; height: 300px; background: var(--orange-soft); top: 55%; left: -120px; opacity: 0.22; }
        .blob-4 { width: 280px; height: 280px; background: var(--maroon);      top: -100px; right: 12%; opacity: 0.14; }

        .container {
            position: relative;
            z-index: 1;
            overflow: hidden;
            width: 100%;
            max-width: 640px;
            padding: 56px 48px 44px;
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.95);
            border-radius: 28px;
            box-shadow:
                0 1px 2px rgba(60, 40, 10, 0.05),
                0 24px 50px -14px rgba(138, 80, 20, 0.25);
            animation: fade-in 0.8s ease-out both;
        }

        /* Faixa de blocos coloridos, remetendo às letras da logo */
        .container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(
                90deg,
                var(--green) 0 25%,
                var(--yellow) 25% 50%,
                var(--orange) 50% 75%,
                var(--maroon) 75% 100%
            );
        }

        /* ---------- Topo ---------- */
        .hero {
            text-align: center;
        }

        .logo {
            display: block;
            width: 100%;
            max-width: 340px;
            height: auto;
            margin: 0 auto 18px;
            will-change: transform;
        }

        .tagline {
            margin-bottom: 20px;
            font-size: 15px;
            font-style: italic;
            color: var(--muted);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            background: #fff;
            border: 1px solid rgba(74, 111, 44, 0.25);
            border-radius: 999px;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.01em;
            color: var(--green);
            box-shadow: 0 2px 10px rgba(74, 111, 44, 0.12);
        }

        .dot {
            position: relative;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--green);
        }

        .dot::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: var(--green);
            animation: pulse 2s ease-out infinite;
        }

        /* ---------- Conteúdo ---------- */
        .divider {
            height: 1px;
            margin: 32px 0 28px;
            background: linear-gradient(90deg, transparent, rgba(138, 80, 20, 0.2), transparent);
        }

        .section + .section {
            margin-top: 26px;
        }

        .section h2 {
            margin-bottom: 8px;
            font-size: 16px;
            font-weight: 700;
        }

        .section p {
            font-size: 14.5px;
            color: var(--muted);
        }

        pre {
            margin-top: 12px;
            padding: 14px 16px;
            overflow-x: auto;
            background: #2f2a1f;
            border-radius: 14px;
        }

        code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
            color: var(--text);
        }

        pre code {
            color: #fdf3dc;
            line-height: 1.7;
        }

        pre .c { color: #a8a08a; }

        .endpoints {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px 16px;
            margin-top: 12px;
        }

        .endpoints li {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .method {
            flex-shrink: 0;
            padding: 2px 7px;
            border-radius: 6px;
            background: rgba(74, 111, 44, 0.12);
            color: var(--green);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .actions {
            margin-top: 28px;
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 11px 24px;
            background: var(--green);
            color: #fff;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 6px 16px -6px rgba(74, 111, 44, 0.6);
            transition: box-shadow 0.2s ease, filter 0.2s ease;
        }

        .btn:hover {
            filter: brightness(1.08);
            box-shadow: 0 10px 20px -6px rgba(74, 111, 44, 0.6);
        }

        @keyframes pulse {
            0%   { transform: scale(1);   opacity: 0.6; }
            100% { transform: scale(2.8); opacity: 0; }
        }

        @keyframes fade-in {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @media (max-width: 560px) {
            .container {
                padding: 44px 24px 36px;
            }

            .endpoints {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true">
        <span class="blob blob-1"></span>
        <span class="blob blob-2"></span>
        <span class="blob blob-3"></span>
        <span class="blob blob-4"></span>
    </div>

    <div class="container">
        <header class="hero">
            <img
                class="logo"
                src="{{ asset('images/votus-logo.png') }}"
                alt="Logo do projeto"
            >
            <p class="tagline">Seu voto, sua escolha, seu futuro.</p>
            <p class="status">
                <span class="dot"></span>
                API operacional
            </p>
        </header>

        <div class="divider"></div>

        <section class="section">
            <h2>Sobre o projeto</h2>
            <p>
                O <strong>Votus</strong> é uma plataforma de transparência política desenvolvida
                para o <strong>Ceará Científico 2026</strong>. Esta API reúne dados de parlamentares,
                executivos e candidatos das eleições de 2026, além de notícias, explicações
                educativas, propostas cidadãs e oportunidades.
            </p>
        </section>

        <section class="section">
            <h2>Como usar</h2>
            <p>
                Os endpoints de consulta são públicos, usam o método <code>GET</code> e respondem em JSON.
                Não é preciso autenticação.
            </p>

            <pre><code><span class="c"># Exemplo: últimas notícias</span>
curl "{{ url('/api/news') }}?per_page=10" \
  -H "Accept: application/json"</code></pre>

            <ul class="endpoints">
                <li><span class="method">GET</span><code>/api/deputies</code></li>
                <li><span class="method">GET</span><code>/api/senators</code></li>
                <li><span class="method">GET</span><code>/api/state-deputies</code></li>
                <li><span class="method">GET</span><code>/api/president-candidates</code></li>
                <li><span class="method">GET</span><code>/api/governor-candidates</code></li>
                <li><span class="method">GET</span><code>/api/senate-candidates</code></li>
                <li><span class="method">GET</span><code>/api/news</code></li>
                <li><span class="method">GET</span><code>/api/explanations</code></li>
                <li><span class="method">GET</span><code>/api/proposals</code></li>
                <li><span class="method">GET</span><code>/api/opportunities</code></li>
            </ul>

            <p style="margin-top: 14px;">
                As rotas administrativas (<code>/api/admin/*</code>) exigem login e um token Bearer.
            </p>
        </section>

        <div class="actions">
            <a class="btn" href="{{ url('/api/documentation') }}">Ver documentação completa →</a>
        </div>
    </div>

    <script>
        // efeito de flutuação suave (somente a logo)
        const logo = document.querySelector('.logo');

        let start = null;

        function float(timestamp) {
            if (!start) start = timestamp;

            const elapsed = timestamp - start;
            const offset = Math.sin(elapsed / 1000) * 10;

            logo.style.transform = `translateY(${offset}px)`;

            requestAnimationFrame(float);
        }

        requestAnimationFrame(float);
    </script>
</body>
</html>