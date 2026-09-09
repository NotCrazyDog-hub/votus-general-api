<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oportunidades</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-900">
<main class="min-h-screen">

    {{-- Cabeçalho --}}
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-6 py-12">
            <span
                class="text-sm font-semibold text-indigo-600"
            >
                Oportunidades
            </span>

            <h1
                class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl"
            >
                Encontre oportunidades para você
            </h1>

            <p
                class="mt-3 max-w-2xl text-slate-600"
            >
                Explore vagas de emprego, estágio e Jovem Aprendiz
                disponíveis no Ceará.
            </p>

        </div>

    </section>

    <div class="mx-auto max-w-6xl px-6 py-10">

        {{-- Pesquisa e filtros --}}
        <form
            method="GET"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >

            <div
                class="grid gap-4 md:grid-cols-[1fr_220px_220px_auto]"
            >

                {{-- Pesquisa --}}
                <div>

                    <label
                        for="search"
                        class="mb-2 block text-sm font-medium text-slate-700"
                    >
                        Pesquisar
                    </label>

                    <input
                        id="search"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Ex.: administração, tecnologia..."
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                    >

                </div>

                <div>

                    <label
                            for="search"
                            class="mb-2 block text-sm font-medium text-slate-700"
                        >
                        Localização
                    </label>

                    <select
                        id="location"
                        name="location"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                    >

                        <option value="">
                            Todas as cidades
                        </option>

                        @foreach($locations as $location)

                            <option
                                value="{{ $location }}"
                                @selected(request('location') === $location)
                            >
                                {{ $location }}
                            </option>

                        @endforeach

                    </select>

                </div>

                {{-- Tipo --}}
                <div>

                    <label
                        for="type"
                        class="mb-2 block text-sm font-medium text-slate-700"
                    >
                        Tipo
                    </label>

                    <select
                        id="type"
                        name="type"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                    >

                        <option value="">
                            Todas
                        </option>

                        <option
                            value="jovem_aprendiz"
                            @selected(
                                request('type')
                                === 'jovem_aprendiz'
                            )
                        >
                            Jovem Aprendiz
                        </option>

                        <option
                            value="estagio"
                            @selected(
                                request('type')
                                === 'estagio'
                            )
                        >
                            Estágio
                        </option>

                        <option
                            value="emprego"
                            @selected(
                                request('type')
                                === 'emprego'
                            )
                        >
                            Emprego
                        </option>

                    </select>

                </div>


                {{-- Botão --}}
                <div class="flex items-end">

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 md:w-auto"
                    >
                        Buscar
                    </button>

                </div>

            </div>

        </form>



        {{-- Informações --}}
        <div
            class="mt-8 flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >

            <div>

                <h2 class="text-xl font-semibold">
                    Oportunidades disponíveis
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    {{ $opportunities->total() }}
                    oportunidades encontradas
                </p>

            </div>


            @if(
                request('search')
                || request('type')
                || request('location')
            )

                <a
                    href="{{ route('opportunities.index') }}"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                >
                    Limpar filtros
                </a>

            @endif

        </div>



        {{-- Cards --}}
        <div
            class="mt-6 grid gap-5 md:grid-cols-2"
        >

            @forelse(
                $opportunities
                as $opportunity
            )

                <article
                    class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
                >

                    {{-- Categoria + data --}}
                    <div
                        class="flex items-start justify-between gap-4"
                    >

                        <span
                            class="
                                inline-flex rounded-full px-3 py-1
                                text-xs font-semibold

                                @if(
                                    $opportunity->opportunity_type
                                    === 'jovem_aprendiz'
                                )
                                    bg-emerald-50 text-emerald-700

                                @elseif(
                                    $opportunity->opportunity_type
                                    === 'estagio'
                                )
                                    bg-blue-50 text-blue-700

                                @else
                                    bg-violet-50 text-violet-700
                                @endif
                            "
                        >

                            @if(
                                $opportunity->opportunity_type
                                === 'jovem_aprendiz'
                            )
                                Jovem Aprendiz

                            @elseif(
                                $opportunity->opportunity_type
                                === 'estagio'
                            )
                                Estágio

                            @else
                                Emprego
                            @endif

                        </span>


                        @if($opportunity->published_at)

                            <span
                                class="text-xs text-slate-400"
                            >
                                {{
                                    $opportunity
                                        ->published_at
                                        ->format('d/m/Y')
                                }}
                            </span>

                        @endif

                    </div>



                    {{-- Título --}}
                    <h3
                        class="mt-5 text-lg font-semibold leading-snug text-slate-900"
                    >
                        {{ $opportunity->title }}
                    </h3>



                    {{-- Empresa --}}
                    @if($opportunity->company)

                        <p
                            class="mt-2 text-sm font-medium text-slate-600"
                        >
                            {{ $opportunity->company }}
                        </p>

                    @endif

                    {{-- Informações --}}
                    <div
                        class="mt-4 space-y-2 text-sm text-slate-500"
                    >

                        @if($opportunity->location)

                            <div
                                class="flex items-center gap-2"
                            >

                                <svg
                                    width="17"
                                    height="17"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"
                                    />

                                    <circle
                                        cx="12"
                                        cy="10"
                                        r="2"
                                    />
                                </svg>

                                <span>
                                    {{ $opportunity->location }}
                                </span>

                            </div>

                        @endif

                        @if(
                            $opportunity->salary_min
                            ||
                            $opportunity->salary_max
                        )

                            <div
                                class="flex items-center gap-2"
                            >

                                <span>
                                    R$
                                </span>

                                <span>

                                    @if(
                                        $opportunity->salary_min
                                    )

                                        R$
                                        {{
                                            number_format(
                                                $opportunity->salary_min,
                                                2,
                                                ',',
                                                '.'
                                            )
                                        }}

                                    @endif


                                    @if(
                                        $opportunity->salary_min
                                        &&
                                        $opportunity->salary_max
                                    )

                                        –

                                    @endif


                                    @if(
                                        $opportunity->salary_max
                                    )

                                        R$
                                        {{
                                            number_format(
                                                $opportunity->salary_max,
                                                2,
                                                ',',
                                                '.'
                                            )
                                        }}

                                    @endif

                                </span>

                            </div>

                        @endif

                    </div>



                    {{-- Descrição --}}
                    @if($opportunity->description)

                        <p
                            class="mt-5 flex-1 text-sm leading-6 text-slate-600"
                        >
                            {{
                                Str::limit(
                                    strip_tags(
                                        $opportunity->description
                                    ),
                                    220
                                )
                            }}
                        </p>

                    @endif



                    {{-- Footer --}}
                    <div
                        class="mt-6 flex items-center justify-between border-t border-slate-100 pt-5"
                    >

                        <div
                            class="text-xs text-slate-400"
                        >
                            Fonte:
                            <span class="font-medium">
                                Adzuna
                            </span>
                        </div>


                        <a
                            href="{{ $opportunity->external_url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 transition hover:text-indigo-800"
                        >
                            Ver oportunidade

                            <span>
                                →
                            </span>
                        </a>

                    </div>

                </article>


            @empty

                <div
                    class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"
                >

                    <div
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-xl"
                    >
                        🔎
                    </div>

                    <h3
                        class="mt-4 font-semibold"
                    >
                        Nenhuma oportunidade encontrada
                    </h3>

                    <p
                        class="mt-2 text-sm text-slate-500"
                    >
                        Tente alterar os termos da pesquisa
                        ou remover os filtros.
                    </p>

                </div>

            @endforelse

        </div>



        {{-- Paginação --}}
        @if(
            $opportunities->hasPages()
        )

            <div class="mt-10">

                {{ $opportunities->links() }}

            </div>

        @endif

    </div>
</main>
</body>
</html>