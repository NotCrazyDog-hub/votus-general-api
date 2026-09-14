<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Concursos e processos seletivos</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

<main class="mx-auto max-w-7xl px-6 py-12">

    <header class="mb-10 max-w-3xl">

        <span
            class="rounded-full bg-indigo-100 px-3 py-1
                   text-sm font-semibold text-indigo-700"
        >
            Juventude em Pauta
        </span>

        <h1
            class="mt-5 text-4xl font-bold tracking-tight
                   md:text-5xl"
        >
            Concursos e oportunidades públicas
        </h1>

        <p class="mt-4 text-lg leading-relaxed text-slate-500">
            Encontre concursos e processos seletivos publicados
            oficialmente pelos municípios.
        </p>

    </header>


    {{-- Filtros --}}
    <form
        method="GET"
        class="mb-10 grid gap-3 rounded-2xl border border-slate-200
               bg-white p-5 shadow-sm md:grid-cols-4"
    >

        <input
            name="search"
            value="{{ request('search') }}"
            placeholder="Pesquisar..."
            class="rounded-xl border border-slate-300 px-4 py-3 text-sm
                   outline-none focus:border-indigo-500
                   focus:ring-4 focus:ring-indigo-100"
        >


        <select
            name="type"
            class="rounded-xl border border-slate-300 px-4 py-3 text-sm"
        >

            <option value="">
                Todos os tipos
            </option>

            <option
                value="concurso_publico"
                @selected(
                    request('type')
                    === 'concurso_publico'
                )
            >
                Concurso público
            </option>

            <option
                value="processo_seletivo"
                @selected(
                    request('type')
                    === 'processo_seletivo'
                )
            >
                Processo seletivo
            </option>

        </select>


        <input
            name="municipality"
            value="{{ request('municipality') }}"
            placeholder="Município"
            class="rounded-xl border border-slate-300 px-4 py-3 text-sm"
        >


        <button
            class="rounded-xl bg-indigo-600 px-5 py-3
                   text-sm font-semibold text-white
                   transition hover:bg-indigo-700"
        >
            Buscar
        </button>

    </form>


    {{-- Resultados --}}
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">

        @forelse($opportunities as $opportunity)

            <article
                class="flex flex-col rounded-2xl border border-slate-200
                       bg-white p-6 shadow-sm transition
                       hover:-translate-y-1 hover:shadow-lg"
            >

                <div class="mb-4 flex items-center justify-between gap-3">

                    <span
                        class="rounded-full bg-indigo-50 px-3 py-1
                               text-xs font-semibold text-indigo-700"
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

                    @else

                        <span
                            class="rounded-full bg-slate-100
                                   px-3 py-1 text-xs font-semibold
                                   text-slate-500"
                        >
                            {{ $opportunity->status }}
                        </span>

                    @endif

                </div>


                <h2 class="text-xl font-bold leading-snug">
                    {{ $opportunity->title }}
                </h2>


                <p class="mt-2 text-sm text-slate-500">
                    {{ $opportunity->agency }}
                </p>


                <div class="mt-6 space-y-2 text-sm text-slate-600">

                    <p>
                        📍
                        {{ $opportunity->municipality }}
                        —
                        {{ $opportunity->state }}
                    </p>

                    @if($opportunity->vacancies !== null)

                        <p>
                            👥
                            {{ $opportunity->vacancies }}
                            vagas
                        </p>

                    @endif


                    @if($opportunity->salary_max)

                        <p>
                            💰 Até R$
                            {{
                                number_format(
                                    $opportunity->salary_max,
                                    2,
                                    ',',
                                    '.'
                                )
                            }}
                        </p>

                    @endif


                    @if($opportunity->registration_end)

                        <p>
                            📅 Inscrições até
                            {{
                                $opportunity
                                    ->registration_end
                                    ->format('d/m/Y')
                            }}
                        </p>

                    @endif

                </div>


                <a
                    href="{{ route(
                        'public-opportunities.show',
                        $opportunity
                    ) }}"
                    class="mt-7 inline-flex items-center
                           font-semibold text-indigo-600
                           transition hover:text-indigo-800"
                >
                    Ver oportunidade →
                </a>

            </article>

        @empty

            <div
                class="col-span-full rounded-2xl border
                       border-dashed border-slate-300
                       bg-white py-16 text-center"
            >
                <h2 class="font-semibold">
                    Nenhuma oportunidade encontrada
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Tente alterar os filtros da pesquisa.
                </p>
            </div>

        @endforelse

    </div>


    <div class="mt-10">
        {{ $opportunities->links() }}
    </div>

</main>

</body>
</html>