<!DOCTYPE html>
<html lang="pt-BR" class="min-h-full bg-gradient-to-br from-cream-50 to-cream-100 bg-fixed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('images/ivy.png') }}">

    <title>Votus | API</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Montserrat', 'sans-serif'],
                    },
                    colors: {
                        // Paleta extraída da logo
                        votus: {
                            green: '#4a6f2c',
                            yellow: '#f9b515',
                            orange: '#ff7a00',
                            'orange-soft': '#f78e31',
                            maroon: '#8a1a0c',
                        },
                        cream: { 50: '#fffdf7', 100: '#fff4e3' },
                        ink: '#2f2a1f',
                        muted: '#6b6455',
                    },
                    keyframes: {
                        'pulse-ring': {
                            '0%':   { transform: 'scale(1)',   opacity: '0.6' },
                            '100%': { transform: 'scale(2.8)', opacity: '0' },
                        },
                        'fade-in': {
                            from: { opacity: '0' },
                            to:   { opacity: '1' },
                        },
                    },
                    animation: {
                        'pulse-ring': 'pulse-ring 2s ease-out infinite',
                        'fade-in': 'fade-in 0.8s ease-out both',
                    },
                },
            },
        };
    </script>
</head>
<body class="flex min-h-screen items-center justify-center px-5 py-10 font-sans leading-[1.6] text-ink">

    <!-- Camada fixa que recorta as manchas, evitando bordas/rolagem -->
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden" aria-hidden="true">
        <span class="absolute -left-[100px] -top-[140px] h-[420px] w-[420px] rounded-full bg-votus-yellow opacity-[.35] blur-[90px]"></span>
        <span class="absolute -bottom-[140px] -right-[100px] h-[380px] w-[380px] rounded-full bg-votus-green opacity-[.28] blur-[90px]"></span>
        <span class="absolute -left-[120px] top-[55%] h-[300px] w-[300px] rounded-full bg-votus-orange-soft opacity-[.22] blur-[90px]"></span>
        <span class="absolute -top-[100px] right-[12%] h-[280px] w-[280px] rounded-full bg-votus-maroon opacity-[.14] blur-[90px]"></span>
    </div>

    <div class="relative z-10 w-full max-w-[640px] animate-fade-in overflow-hidden rounded-[28px] border border-white/95 bg-white/80 px-12 pb-12 pt-14 text-center shadow-[0_1px_2px_rgba(60,40,10,0.05),0_24px_50px_-14px_rgba(138,80,20,0.25)] backdrop-blur-[14px] max-[560px]:px-6 max-[560px]:pb-10 max-[560px]:pt-11">

        <!-- Faixa de blocos coloridos, remetendo às letras da logo -->
        <span class="absolute inset-x-0 top-0 h-1.5 bg-[linear-gradient(90deg,#4a6f2c_0_25%,#f9b515_25%_50%,#ff7a00_50%_75%,#8a1a0c_75%_100%)]"></span>

        <!-- Topo -->
        <header>
            <img
                class="logo mx-auto mb-[18px] block h-auto w-full max-w-[340px] will-change-transform"
                src="{{ asset('images/votus-logo.png') }}"
                alt="Logo do projeto"
            >
            <p class="mb-5 text-[15px] italic text-muted">Seu voto, sua escolha, seu futuro.</p>

            <p class="inline-flex items-center gap-2.5 rounded-full border border-votus-green/25 bg-white px-5 py-2.5 text-[15px] font-semibold tracking-[0.01em] text-votus-green shadow-[0_2px_10px_rgba(74,111,44,0.12)]">
                <span class="relative h-2.5 w-2.5 rounded-full bg-votus-green">
                    <span class="absolute inset-0 animate-pulse-ring rounded-full bg-votus-green"></span>
                </span>
                API operacional
            </p>
        </header>

        <div class="mb-7 mt-8 h-px bg-gradient-to-r from-transparent via-[rgba(138,80,20,0.2)] to-transparent"></div>

        <!-- Sobre -->
        <section>
            <h2 class="mb-2 text-base font-bold">Sobre o projeto</h2>
            <p class="text-[14.5px] text-muted">
                O <strong>Votus</strong> é uma plataforma de transparência política desenvolvida
                para o <strong>Ceará Científico 2026</strong>. Esta API reúne dados de parlamentares,
                executivos e candidatos das eleições de 2026, além de notícias, explicações
                educativas, propostas cidadãs e oportunidades.
            </p>
        </section>

        <!-- Botão da documentação -->
        <a
            href="{{ url('/api/documentation') }}"
            class="mt-9 inline-block rounded-full bg-votus-green px-10 py-4 text-base font-bold text-white no-underline shadow-[0_10px_24px_-8px_rgba(74,111,44,0.65)] transition hover:-translate-y-0.5 hover:brightness-110 hover:shadow-[0_14px_28px_-8px_rgba(74,111,44,0.7)] max-[560px]:w-full max-[560px]:px-6"
        >
            Ver documentação completa →
        </a>
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