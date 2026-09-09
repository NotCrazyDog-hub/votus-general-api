<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Revisar oportunidade</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

<main class="mx-auto max-w-7xl px-6 py-10">

    <a
        href="{{ route('admin.public-opportunities.index') }}"
        class="mb-6 inline-flex text-sm font-medium text-slate-500
               transition hover:text-indigo-600"
    >
        ← Voltar para oportunidades
    </a>


    <div class="mb-8">

        <p class="text-sm font-semibold text-indigo-600">
            Revisão
        </p>

        <h1 class="mt-1 text-3xl font-bold">
            {{ $opportunity->title ?? 'Oportunidade sem título' }}
        </h1>

        <p class="mt-2 text-slate-500">
            Confira os dados extraídos automaticamente antes de publicar.
        </p>

    </div>


    @if(session('success'))

        <div
            class="mb-6 rounded-xl border border-emerald-200
                   bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ session('success') }}
        </div>

    @endif


    @if($errors->any())

        <div
            class="mb-6 rounded-xl border border-red-200
                   bg-red-50 p-4 text-sm text-red-700"
        >

            <p class="font-semibold">
                Corrija os seguintes campos:
            </p>

            <ul class="mt-2 list-disc pl-5">

                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>

                @endforeach

            </ul>

        </div>

    @endif


    <div class="grid gap-8 lg:grid-cols-[1fr_380px]">

        {{-- Formulário --}}
        <form
            method="POST"
            action="{{ route(
                'admin.public-opportunities.update',
                $opportunity
            ) }}"
            class="space-y-8"
        >

            @csrf
            @method('PUT')


            <section
                class="rounded-2xl border border-slate-200
                       bg-white p-6 shadow-sm"
            >

                <h2 class="mb-6 text-lg font-bold">
                    Informações principais
                </h2>


                <div class="grid gap-5 md:grid-cols-2">

                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Tipo
                        </label>

                        <select
                            name="type"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm outline-none
                                   focus:border-indigo-500
                                   focus:ring-4 focus:ring-indigo-100"
                        >

                            <option
                                value="concurso_publico"
                                @selected(
                                    old('type', $opportunity->type)
                                    === 'concurso_publico'
                                )
                            >
                                Concurso público
                            </option>

                            <option
                                value="processo_seletivo"
                                @selected(
                                    old('type', $opportunity->type)
                                    === 'processo_seletivo'
                                )
                            >
                                Processo seletivo
                            </option>

                            <option
                                value="processo_seletivo_simplificado"
                                @selected(
                                    old('type', $opportunity->type)
                                    === 'processo_seletivo_simplificado'
                                )
                            >
                                Processo seletivo simplificado
                            </option>

                        </select>
                    </div>


                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Número do edital
                        </label>

                        <input
                            name="notice_number"
                            value="{{ old(
                                'notice_number',
                                $opportunity->notice_number
                            ) }}"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm outline-none
                                   focus:border-indigo-500
                                   focus:ring-4 focus:ring-indigo-100"
                        >
                    </div>

                </div>


                <div class="mt-5">

                    <label class="mb-2 block text-sm font-medium">
                        Título
                    </label>

                    <input
                        name="title"
                        value="{{ old(
                            'title',
                            $opportunity->title
                        ) }}"
                        class="w-full rounded-xl border border-slate-300
                               px-4 py-3 text-sm outline-none
                               focus:border-indigo-500
                               focus:ring-4 focus:ring-indigo-100"
                    >

                </div>


                <div class="mt-5">

                    <label class="mb-2 block text-sm font-medium">
                        Órgão
                    </label>

                    <input
                        name="agency"
                        value="{{ old(
                            'agency',
                            $opportunity->agency
                        ) }}"
                        class="w-full rounded-xl border border-slate-300
                               px-4 py-3 text-sm outline-none
                               focus:border-indigo-500
                               focus:ring-4 focus:ring-indigo-100"
                    >

                </div>


                <div class="mt-5 grid gap-5 md:grid-cols-[1fr_110px]">

                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Município
                        </label>

                        <input
                            name="municipality"
                            value="{{ old(
                                'municipality',
                                $opportunity->municipality
                            ) }}"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm outline-none
                                   focus:border-indigo-500
                                   focus:ring-4 focus:ring-indigo-100"
                        >
                    </div>


                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            UF
                        </label>

                        <input
                            name="state"
                            maxlength="2"
                            value="{{ old(
                                'state',
                                $opportunity->state
                            ) }}"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm uppercase outline-none
                                   focus:border-indigo-500
                                   focus:ring-4 focus:ring-indigo-100"
                        >
                    </div>

                </div>

            </section>


            {{-- Vagas --}}
            <section
                class="rounded-2xl border border-slate-200
                       bg-white p-6 shadow-sm"
            >

                <h2 class="mb-6 text-lg font-bold">
                    Vagas e requisitos
                </h2>


                <div class="grid gap-5 md:grid-cols-2">

                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Número de vagas
                        </label>

                        <input
                            type="number"
                            min="0"
                            name="vacancies"
                            value="{{ old(
                                'vacancies',
                                $opportunity->vacancies
                            ) }}"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm outline-none
                                   focus:border-indigo-500
                                   focus:ring-4 focus:ring-indigo-100"
                        >
                    </div>


                    <div></div>


                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Cargos
                        </label>

                        <textarea
                            name="positions_text"
                            rows="6"
                            placeholder="Um cargo por linha"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm outline-none
                                   focus:border-indigo-500
                                   focus:ring-4 focus:ring-indigo-100"
                        >{{ old(
                            'positions_text',
                            implode(
                                "\n",
                                $opportunity->positions ?? []
                            )
                        ) }}</textarea>
                    </div>


                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Escolaridade
                        </label>

                        <textarea
                            name="education_levels_text"
                            rows="6"
                            placeholder="Uma escolaridade por linha"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm outline-none
                                   focus:border-indigo-500
                                   focus:ring-4 focus:ring-indigo-100"
                        >{{ old(
                            'education_levels_text',
                            implode(
                                "\n",
                                $opportunity->education_levels ?? []
                            )
                        ) }}</textarea>
                    </div>

                </div>

            </section>


            {{-- Valores --}}
            <section
                class="rounded-2xl border border-slate-200
                       bg-white p-6 shadow-sm"
            >

                <h2 class="mb-6 text-lg font-bold">
                    Valores
                </h2>

                <div class="grid gap-5 md:grid-cols-2">

                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Salário mínimo
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            name="salary_min"
                            value="{{ old(
                                'salary_min',
                                $opportunity->salary_min
                            ) }}"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm"
                        >
                    </div>


                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Salário máximo
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            name="salary_max"
                            value="{{ old(
                                'salary_max',
                                $opportunity->salary_max
                            ) }}"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm"
                        >
                    </div>


                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Taxa mínima
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            name="fee_min"
                            value="{{ old(
                                'fee_min',
                                $opportunity->fee_min
                            ) }}"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm"
                        >
                    </div>


                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Taxa máxima
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            name="fee_max"
                            value="{{ old(
                                'fee_max',
                                $opportunity->fee_max
                            ) }}"
                            class="w-full rounded-xl border border-slate-300
                                   px-4 py-3 text-sm"
                        >
                    </div>

                </div>

            </section>


            {{-- Datas --}}
            <section
                class="rounded-2xl border border-slate-200
                       bg-white p-6 shadow-sm"
            >

                <h2 class="mb-6 text-lg font-bold">
                    Datas importantes
                </h2>

                <div class="grid gap-5 md:grid-cols-3">

                    @foreach([
                        [
                            'registration_start',
                            'Início das inscrições',
                            $opportunity->registration_start
                        ],
                        [
                            'registration_end',
                            'Fim das inscrições',
                            $opportunity->registration_end
                        ],
                        [
                            'exam_date',
                            'Data da prova',
                            $opportunity->exam_date
                        ]
                    ] as [$name, $label, $value])

                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                {{ $label }}
                            </label>

                            <input
                                type="date"
                                name="{{ $name }}"
                                value="{{ old(
                                    $name,
                                    $value?->format('Y-m-d')
                                ) }}"
                                class="w-full rounded-xl border border-slate-300
                                       px-4 py-3 text-sm"
                            >
                        </div>

                    @endforeach

                </div>

            </section>


            {{-- Resumo --}}
            <section
                class="rounded-2xl border border-slate-200
                       bg-white p-6 shadow-sm"
            >

                <h2 class="mb-6 text-lg font-bold">
                    Informações para publicação
                </h2>


                <label class="mb-2 block text-sm font-medium">
                    Site de inscrição
                </label>

                <input
                    type="url"
                    name="registration_url"
                    value="{{ old(
                        'registration_url',
                        $opportunity->registration_url
                    ) }}"
                    class="mb-5 w-full rounded-xl border border-slate-300
                           px-4 py-3 text-sm"
                >


                <label class="mb-2 block text-sm font-medium">
                    Resumo
                </label>

                <textarea
                    name="summary"
                    rows="7"
                    class="w-full rounded-xl border border-slate-300
                           px-4 py-3 text-sm"
                >{{ old(
                    'summary',
                    $opportunity->summary
                ) }}</textarea>

            </section>


            <button
                class="rounded-xl bg-slate-900 px-6 py-3 text-sm
                       font-semibold text-white transition hover:bg-slate-700"
            >
                Salvar alterações
            </button>

        </form>


        {{-- Barra lateral --}}
        <aside class="space-y-6">

            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-6 shadow-sm"
            >

                <p class="text-sm text-slate-500">
                    Situação atual
                </p>

                <p class="mt-1 text-xl font-bold">
                    {{ $opportunity->status }}
                </p>

                <p class="mt-4 text-sm text-slate-500">
                    Revisão
                </p>

                <p class="mt-1 font-semibold">
                    {{ $opportunity->review_status }}
                </p>

            </div>


            {{-- Fontes --}}
            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-6 shadow-sm"
            >

                <h2 class="mb-4 font-bold">
                    Fontes oficiais
                </h2>

                <div class="space-y-5">

                    @forelse($opportunity->publications as $publication)

                        <div class="border-b border-slate-100 pb-5 last:border-0">

                            <p class="font-medium">
                                {{
                                    $publication->publication_type
                                    ?? 'Publicação oficial'
                                }}
                            </p>

                            @if($publication->gazette_date)

                                <p class="mt-1 text-xs text-slate-500">
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
                                    class="mt-3 inline-flex text-sm
                                           font-semibold text-indigo-600
                                           hover:text-indigo-800"
                                >
                                    Abrir Diário Oficial ↗
                                </a>

                            @endif


                            @if($publication->source_excerpt)

                                <details class="mt-3">

                                    <summary
                                        class="cursor-pointer text-xs
                                               font-medium text-slate-500"
                                    >
                                        Ver trecho analisado
                                    </summary>

                                    <p
                                        class="mt-3 max-h-60 overflow-y-auto
                                               whitespace-pre-wrap rounded-lg
                                               bg-slate-50 p-3 text-xs
                                               leading-relaxed text-slate-600"
                                    >{{ $publication->source_excerpt }}</p>

                                </details>

                            @endif

                        </div>

                    @empty

                        <p class="text-sm text-slate-500">
                            Nenhuma fonte associada.
                        </p>

                    @endforelse

                </div>

            </div>


            {{-- Ações --}}
            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-6 shadow-sm"
            >

                <h2 class="mb-4 font-bold">
                    Revisão
                </h2>


                @if($opportunity->review_status !== 'approved')

                    <form
                        method="POST"
                        action="{{ route(
                            'admin.public-opportunities.approve',
                            $opportunity
                        ) }}"
                    >

                        @csrf

                        <button
                            class="w-full rounded-xl bg-emerald-600
                                   px-4 py-3 text-sm font-semibold
                                   text-white transition
                                   hover:bg-emerald-700"
                        >
                            Publicar oportunidade
                        </button>

                    </form>

                @endif

                @if($opportunity->review_status === 'approved')

                    <form
                        action="{{ route('admin.public-opportunities.toggle-published', $opportunity) }}"
                        method="POST"
                    >
                        @csrf
                        @method('PATCH')

                    
                        <button
                            type="submit"
                            class="w-full rounded-xl
                                   px-4 py-3 text-sm font-semibold
                                   text-white transition bg-red-700 hover:bg-red-200"
                        >
                            Despublicar
                        </button>
                    </form>
                @endif


                @if($opportunity->review_status !== 'rejected')

                    <form
                        method="POST"
                        action="{{ route(
                            'admin.public-opportunities.reject',
                            $opportunity
                        ) }}"
                        class="mt-3"
                    >

                        @csrf

                        <button
                            class="w-full rounded-xl border border-red-200
                                   px-4 py-3 text-sm font-semibold
                                   text-red-600 transition hover:bg-red-50"
                        >
                            Descartar oportunidade
                        </button>

                    </form>

                @endif

            </div>

        </aside>

    </div>

</main>

</body>
</html>