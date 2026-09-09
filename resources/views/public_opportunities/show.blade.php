<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>{{ $opportunity->title }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

<main class="mx-auto max-w-5xl px-6 py-12">

    <a
        href="{{ route('public-opportunities.index') }}"
        class="text-sm font-medium text-slate-500
               hover:text-indigo-600"
    >
        ← Voltar para oportunidades
    </a>


    <header
        class="mt-6 rounded-3xl border border-slate-200
               bg-white p-8 shadow-sm md:p-10"
    >

        <div class="flex flex-wrap gap-2">

            <span
                class="rounded-full bg-indigo-100
                       px-3 py-1 text-xs font-semibold
                       text-indigo-700"
            >
                {{
                    $opportunity->type === 'concurso_publico'
                        ? 'Concurso público'
                        : 'Processo seletivo'
                }}
            </span>


            @if($opportunity->status === 'aberto')

                <span
                    class="rounded-full bg-emerald-100
                           px-3 py-1 text-xs font-semibold
                           text-emerald-700"
                >
                    Inscrições abertas
                </span>

            @elseif($opportunity->status === 'em_breve')

                <span
                    class="rounded-full bg-blue-100
                           px-3 py-1 text-xs font-semibold
                           text-blue-700"
                >
                    Em breve
                </span>

            @endif

        </div>


        <h1
            class="mt-5 text-3xl font-bold tracking-tight
                   md:text-4xl"
        >
            {{ $opportunity->title }}
        </h1>


        <p class="mt-3 text-slate-500">
            {{ $opportunity->agency }}
        </p>


        <p class="mt-2 text-sm font-medium text-slate-600">
            📍
            {{ $opportunity->municipality }}
            —
            {{ $opportunity->state }}
        </p>

    </header>


    <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_300px]">

        <div class="space-y-8">

            @if($opportunity->summary)

                <section
                    class="rounded-2xl border border-slate-200
                           bg-white p-7 shadow-sm"
                >

                    <h2 class="text-xl font-bold">
                        Sobre
                    </h2>

                    <p class="mt-4 leading-7 text-slate-600">
                        {{ $opportunity->summary }}
                    </p>

                </section>

            @endif


            @if(!empty($opportunity->positions))

                <section
                    class="rounded-2xl border border-slate-200
                           bg-white p-7 shadow-sm"
                >

                    <h2 class="text-xl font-bold">
                        Cargos disponíveis
                    </h2>

                    <div class="mt-4 flex flex-wrap gap-2">

                        @foreach($opportunity->positions as $position)

                            <span
                                class="rounded-lg bg-slate-100
                                       px-3 py-2 text-sm text-slate-700"
                            >
                                {{ $position }}
                            </span>

                        @endforeach

                    </div>

                </section>

            @endif


            @if(!empty($opportunity->education_levels))

                <section
                    class="rounded-2xl border border-slate-200
                           bg-white p-7 shadow-sm"
                >

                    <h2 class="text-xl font-bold">
                        Escolaridade
                    </h2>

                    <ul class="mt-4 space-y-2 text-slate-600">

                        @foreach($opportunity->education_levels as $level)

                            <li>
                                • {{ $level }}
                            </li>

                        @endforeach

                    </ul>

                </section>

            @endif


            <section
                class="rounded-2xl border border-slate-200
                       bg-white p-7 shadow-sm"
            >

                <h2 class="text-xl font-bold">
                    Publicações oficiais
                </h2>

                <div class="mt-5 space-y-4">

                    @foreach($opportunity->publications as $publication)

                        <div
                            class="rounded-xl border border-slate-200
                                   p-4"
                        >

                            <p class="font-semibold">
                                {{
                                    $publication->publication_type
                                    ?? 'Publicação oficial'
                                }}
                            </p>


                            @if($publication->gazette_date)

                                <p class="mt-1 text-sm text-slate-500">
                                    {{
                                        $publication
                                            ->gazette_date
                                            ->format('d/m/Y')
                                    }}
                                </p>

                            @endif


                            @if($publication->gazette_url)

                                <a
                                    href="{{ $publication->gazette_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-3 inline-block
                                           text-sm font-semibold
                                           text-indigo-600
                                           hover:text-indigo-800"
                                >
                                    Ver publicação oficial ↗
                                </a>

                            @endif

                        </div>

                    @endforeach

                </div>

            </section>

        </div>


        {{-- Informações rápidas --}}
        <aside>

            <div
                class="sticky top-6 rounded-2xl
                       border border-slate-200
                       bg-white p-6 shadow-sm"
            >

                <h2 class="font-bold">
                    Informações
                </h2>

                <dl class="mt-5 space-y-5">

                    @if($opportunity->vacancies !== null)

                        <div>
                            <dt class="text-xs font-medium text-slate-500">
                                Vagas
                            </dt>

                            <dd class="mt-1 font-semibold">
                                {{ $opportunity->vacancies }}
                            </dd>
                        </div>

                    @endif


                    @if($opportunity->salary_max)

                        <div>
                            <dt class="text-xs font-medium text-slate-500">
                                Remuneração
                            </dt>

                            <dd class="mt-1 font-semibold">
                                Até R$
                                {{
                                    number_format(
                                        $opportunity->salary_max,
                                        2,
                                        ',',
                                        '.'
                                    )
                                }}
                            </dd>
                        </div>

                    @endif


                    @if($opportunity->registration_start)

                        <div>
                            <dt class="text-xs font-medium text-slate-500">
                                Início das inscrições
                            </dt>

                            <dd class="mt-1 font-semibold">
                                {{
                                    $opportunity
                                        ->registration_start
                                        ->format('d/m/Y')
                                }}
                            </dd>
                        </div>

                    @endif


                    @if($opportunity->registration_end)

                        <div>
                            <dt class="text-xs font-medium text-slate-500">
                                Fim das inscrições
                            </dt>

                            <dd class="mt-1 font-semibold">
                                {{
                                    $opportunity
                                        ->registration_end
                                        ->format('d/m/Y')
                                }}
                            </dd>
                        </div>

                    @endif


                    @if($opportunity->exam_date)

                        <div>
                            <dt class="text-xs font-medium text-slate-500">
                                Prova
                            </dt>

                            <dd class="mt-1 font-semibold">
                                {{
                                    $opportunity
                                        ->exam_date
                                        ->format('d/m/Y')
                                }}
                            </dd>
                        </div>

                    @endif

                </dl>


                @if($opportunity->registration_url)

                    @php
                        $externalUrl = $opportunity->registration_url;

                        if ($externalUrl && !preg_match('/^https?:\/\//i', $externalUrl)) {
                            $externalUrl = 'https://' . ltrim($externalUrl, '/');
                        }
                    @endphp

                    <a
                        href="{{ $externalUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-7 flex w-full items-center
                               justify-center rounded-xl bg-indigo-600
                               px-4 py-3 text-sm font-semibold
                               text-white transition hover:bg-indigo-700"
                    >
                        Ir para oportunidade ↗
                    </a>

                @endif

            </div>

        </aside>

    </div>

</main>

</body>
</html>