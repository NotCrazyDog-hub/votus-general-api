<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Oportunidades públicas</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

<main class="mx-auto max-w-7xl px-6 py-10">

    {{-- Cabeçalho --}}
    <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">

        <div>
            <p class="mb-2 text-sm font-semibold text-indigo-600">
                Administração
            </p>

            <h1 class="text-3xl font-bold tracking-tight">
                Oportunidades públicas
            </h1>

            <p class="mt-2 max-w-2xl text-sm text-slate-500">
                Revise concursos e processos seletivos encontrados
                automaticamente antes de publicá-los.
            </p>
        </div>

    </div>


    {{-- Mensagem --}}
    @if(session('success'))

        <div
            class="mb-6 rounded-xl border border-emerald-200
                   bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ session('success') }}
        </div>

    @endif


    {{-- Filtros --}}
    <form
        method="GET"
        class="mb-8 grid gap-3 rounded-2xl border border-slate-200
               bg-white p-4 shadow-sm md:grid-cols-[1fr_220px_auto]"
    >

        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Pesquisar título, órgão, município ou edital..."
            class="h-11 rounded-xl border border-slate-300 px-4 text-sm
                   outline-none transition
                   focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
        >


        <select
            name="status"
            class="h-11 rounded-xl border border-slate-300 px-3 text-sm
                   outline-none transition
                   focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
        >

            <option value="">
                Todos
            </option>

            <option
                value="pending"
                @selected(request('status') === 'pending')
            >
                Pendentes
            </option>

            <option
                value="approved"
                @selected(request('status') === 'approved')
            >
                Publicados
            </option>

            <option
                value="rejected"
                @selected(request('status') === 'rejected')
            >
                Descartados
            </option>

        </select>


        <button
            class="h-11 rounded-xl bg-slate-900 px-6 text-sm
                   font-semibold text-white transition hover:bg-slate-700"
        >
            Filtrar
        </button>

    </form>


    {{-- Cards --}}
    <div class="space-y-4">

        @forelse($opportunities as $opportunity)

            <article
                class="rounded-2xl border border-slate-200 bg-white
                       p-6 shadow-sm transition hover:shadow-md"
            >

                <div
                    class="flex flex-col gap-6 lg:flex-row
                           lg:items-center lg:justify-between"
                >

                    <div class="min-w-0 flex-1">

                        {{-- Status revisão --}}
                        <div class="mb-3 flex flex-wrap items-center gap-2">

                            @if($opportunity->review_status === 'pending')

                                <span
                                    class="rounded-full bg-amber-100 px-3 py-1
                                           text-xs font-semibold text-amber-700"
                                >
                                    Pendente
                                </span>

                            @elseif($opportunity->review_status === 'approved')

                                <span
                                    class="rounded-full bg-emerald-100 px-3 py-1
                                           text-xs font-semibold text-emerald-700"
                                >
                                    Publicado
                                </span>

                            @else

                                <span
                                    class="rounded-full bg-slate-100 px-3 py-1
                                           text-xs font-semibold text-slate-600"
                                >
                                    Descartado
                                </span>

                            @endif


                            <span
                                class="rounded-full bg-indigo-50 px-3 py-1
                                       text-xs font-medium text-indigo-700"
                            >
                                {{
                                    match($opportunity->type) {
                                        'concurso_publico'
                                            => 'Concurso público',

                                        'processo_seletivo'
                                            => 'Processo seletivo',

                                        'processo_seletivo_simplificado'
                                            => 'Processo seletivo simplificado',

                                        default
                                            => 'Oportunidade pública',
                                    }
                                }}
                            </span>

                        </div>


                        <h2 class="truncate text-lg font-bold text-slate-900">
                            {{ $opportunity->title ?? 'Sem título' }}
                        </h2>


                        <p class="mt-1 text-sm text-slate-500">
                            {{ $opportunity->agency ?? 'Órgão não identificado' }}
                        </p>


                        <div
                            class="mt-4 flex flex-wrap gap-x-6 gap-y-2
                                   text-sm text-slate-600"
                        >

                            <span>
                                📍
                                {{ $opportunity->municipality ?? 'Município não identificado' }}

                                @if($opportunity->state)
                                    — {{ $opportunity->state }}
                                @endif
                            </span>

                            <span>
                                👥
                                {{ $opportunity->vacancies ?? '?' }}
                                vagas
                            </span>

                            <span>
                                📄
                                {{ $opportunity->publications_count }}
                                publicação(ões)
                            </span>

                            <span>
                                Situação:
                                <strong class="font-semibold text-slate-800">
                                    {{ $opportunity->status }}
                                </strong>
                            </span>

                        </div>

                    </div>


                    <a
                        href="{{ route(
                            'admin.public-opportunities.edit',
                            $opportunity
                        ) }}"
                        class="inline-flex h-11 shrink-0 items-center justify-center
                               rounded-xl border border-slate-300 px-5 text-sm
                               font-semibold text-slate-700 transition
                               hover:border-indigo-300 hover:bg-indigo-50
                               hover:text-indigo-700"
                    >
                        Revisar
                    </a>

                </div>

            </article>

        @empty

            <div
                class="rounded-2xl border border-dashed border-slate-300
                       bg-white px-6 py-16 text-center"
            >
                <h2 class="font-semibold">
                    Nenhuma oportunidade encontrada
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Novas oportunidades coletadas pelo n8n aparecerão aqui.
                </p>
            </div>

        @endforelse

    </div>


    <div class="mt-8">
        {{ $opportunities->links() }}
    </div>

</main>

</body>
</html>