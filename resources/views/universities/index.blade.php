<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Buscar universidades</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

@php
    $hasSearch = request()->hasAny([
        'state',
        'city_code',
        'course',
        'sector',
        'modality',
    ]);

    $selectedState = $filters['state'] ?? '';
    $selectedCityCode = $filters['city_code'] ?? '';
    $selectedCourse = $filters['course'] ?? '';
    $selectedSector = $filters['sector'] ?? '';
    $selectedModality = $filters['modality'] ?? '';
@endphp

<main class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">

    {{-- Cabeçalho --}}
    <header class="mb-9">

        <div class="max-w-3xl">

            <p class="mb-2 text-sm font-bold uppercase tracking-wider text-violet-700">
                Ensino superior
            </p>

            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                Encontre universidades e cursos
            </h1>

            <p class="mt-4 text-base leading-7 text-slate-600">
                Escolha um estado, um município e o curso desejado
                para descobrir quais instituições oferecem essa formação.
            </p>

        </div>

    </header>


    {{-- Formulário de busca --}}
    <section
        class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
    >

        <form
            method="GET"
            action="{{ route('universities.index') }}"
            id="university-search-form"
        >

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-5">

                {{-- Estado --}}
                <div>

                    <label
                        for="state"
                        class="mb-2 block text-sm font-semibold text-slate-800"
                    >
                        Estado
                    </label>

                    <select
                        id="state"
                        name="state"
                        class="w-full rounded-xl border border-slate-300 bg-white
                               px-4 py-3 text-sm outline-none transition
                               focus:border-violet-500 focus:ring-4
                               focus:ring-violet-100"
                    >

                        <option value="">
                            Selecione um estado
                        </option>

                        @foreach ($states as $state)

                            <option
                                value="{{ $state }}"
                                @selected($selectedState === $state)
                            >
                                {{ $state }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- Município --}}
                <div>

                    <label
                        for="city_code"
                        class="mb-2 block text-sm font-semibold text-slate-800"
                    >
                        Município
                    </label>

                    <select
                        id="city_code"
                        name="city_code"
                        class="w-full rounded-xl border border-slate-300 bg-white
                               px-4 py-3 text-sm outline-none transition
                               focus:border-violet-500 focus:ring-4
                               focus:ring-violet-100
                               disabled:cursor-not-allowed
                               disabled:bg-slate-100
                               disabled:text-slate-400"
                        @disabled(empty($selectedState))
                    >

                        <option value="">
                            {{ $selectedState
                                ? 'Todos os municípios'
                                : 'Selecione primeiro um estado' }}
                        </option>

                        @foreach ($municipalities as $municipality)

                            <option
                                value="{{ $municipality->ibge_city_code }}"
                                @selected(
                                    $selectedCityCode ===
                                    $municipality->ibge_city_code
                                )
                            >
                                {{ $municipality->city }}
                            </option>

                        @endforeach

                    </select>

                    <p
                        id="city-status"
                        class="mt-2 hidden text-xs text-slate-500"
                        aria-live="polite"
                    ></p>

                </div>


                {{-- Curso --}}
                <div>

                    <label
                        for="course"
                        class="mb-2 block text-sm font-semibold text-slate-800"
                    >
                        Curso
                    </label>

                    <select
                        id="course"
                        name="course"
                        class="w-full rounded-xl border border-slate-300 bg-white
                               px-4 py-3 text-sm outline-none transition
                               focus:border-violet-500 focus:ring-4
                               focus:ring-violet-100
                               disabled:cursor-not-allowed
                               disabled:bg-slate-100
                               disabled:text-slate-400"
                        @disabled(empty($selectedCityCode))
                    >

                        <option value="">
                            {{ $selectedCityCode
                                ? 'Todos os cursos'
                                : 'Selecione primeiro um município' }}
                        </option>

                        @foreach ($courses as $course)

                            <option
                                value="{{ $course->normalized_name }}"
                                @selected(
                                    $selectedCourse ===
                                    $course->normalized_name
                                )
                            >
                                {{ $course->name }}
                            </option>

                        @endforeach

                    </select>

                    <p
                        id="course-status"
                        class="mt-2 hidden text-xs text-slate-500"
                        aria-live="polite"
                    ></p>

                </div>


                {{-- Tipo de instituição --}}
                <div>

                    <label
                        for="sector"
                        class="mb-2 block text-sm font-semibold text-slate-800"
                    >
                        Tipo de instituição
                    </label>

                    <select
                        id="sector"
                        name="sector"
                        class="w-full rounded-xl border border-slate-300 bg-white
                               px-4 py-3 text-sm outline-none transition
                               focus:border-violet-500 focus:ring-4
                               focus:ring-violet-100"
                    >

                        <option value="">
                            Públicas e privadas
                        </option>

                        <option
                            value="public"
                            @selected($selectedSector === 'public')
                        >
                            Somente públicas
                        </option>

                        <option
                            value="private"
                            @selected($selectedSector === 'private')
                        >
                            Somente privadas
                        </option>

                    </select>

                </div>


                {{-- Modalidade --}}
                <div>

                    <label
                        for="modality"
                        class="mb-2 block text-sm font-semibold text-slate-800"
                    >
                        Modalidade
                    </label>

                    <select
                        id="modality"
                        name="modality"
                        class="w-full rounded-xl border border-slate-300 bg-white
                               px-4 py-3 text-sm outline-none transition
                               focus:border-violet-500 focus:ring-4
                               focus:ring-violet-100"
                    >

                        <option value="">
                            Todas as modalidades
                        </option>

                        @foreach ($modalities as $modality)

                            <option
                                value="{{ $modality }}"
                                @selected(
                                    $selectedModality === $modality
                                )
                            >
                                {{ $modality }}
                            </option>

                        @endforeach

                    </select>

                </div>

            </div>


            {{-- Erros --}}
            @if ($errors->any())

                <div
                    class="mt-6 rounded-2xl border border-red-200
                           bg-red-50 p-4 text-sm text-red-700"
                >

                    <p class="font-semibold">
                        Verifique os filtros informados:
                    </p>

                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>

                </div>

            @endif


            {{-- Botões --}}
            <div class="mt-7 flex flex-wrap gap-3">

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl
                           bg-violet-700 px-6 py-3 text-sm font-semibold
                           text-white transition hover:bg-violet-800
                           focus:outline-none focus:ring-4
                           focus:ring-violet-200"
                >
                    Encontrar universidades
                </button>

                <a
                    href="{{ route('universities.index') }}"
                    class="inline-flex items-center justify-center rounded-xl
                           border border-slate-300 bg-white px-6 py-3
                           text-sm font-semibold text-slate-700 transition
                           hover:bg-slate-100"
                >
                    Limpar filtros
                </a>

            </div>

        </form>

    </section>


    {{-- Estado inicial --}}
    @if (! $hasSearch)

        <section
            class="mt-8 rounded-3xl border border-dashed border-slate-300
                   bg-white px-6 py-14 text-center"
        >

            <div
                class="mx-auto flex h-14 w-14 items-center justify-center
                       rounded-2xl bg-violet-100 text-2xl"
            >
                🎓
            </div>

            <h2 class="mt-5 text-xl font-bold">
                Comece selecionando uma região
            </h2>

            <p class="mx-auto mt-2 max-w-xl text-slate-600">
                Escolha o estado e o município para visualizar somente
                os cursos realmente disponíveis naquela localidade.
            </p>

        </section>

    @else

        @php
            $publicTotal = $publicOfferings
                ? $publicOfferings->total()
                : 0;

            $privateTotal = $privateOfferings
                ? $privateOfferings->total()
                : 0;

            $totalResults = $publicTotal + $privateTotal;
        @endphp


        {{-- Resumo da pesquisa --}}
        <section
            class="mt-8 flex flex-col gap-3 rounded-2xl border
                   border-slate-200 bg-white p-5 sm:flex-row
                   sm:items-center sm:justify-between"
        >

            <div>

                <p class="text-sm font-semibold text-violet-700">
                    Resultado da pesquisa
                </p>

                <h2 class="mt-1 text-xl font-bold">
                    {{ $totalResults }}
                    {{ $totalResults === 1 ? 'oferta encontrada' : 'ofertas encontradas' }}
                </h2>

            </div>

            <div class="flex flex-wrap gap-2 text-xs font-semibold">

                @if ($selectedState)

                    <span
                        class="rounded-full bg-slate-100 px-3 py-1.5
                               text-slate-700"
                    >
                        Estado: {{ $selectedState }}
                    </span>

                @endif

                @if ($selectedCityCode)

                    @php
                        $selectedMunicipality = $municipalities
                            ->firstWhere(
                                'ibge_city_code',
                                $selectedCityCode
                            );
                    @endphp

                    @if ($selectedMunicipality)

                        <span
                            class="rounded-full bg-slate-100 px-3 py-1.5
                                   text-slate-700"
                        >
                            Município: {{ $selectedMunicipality->city }}
                        </span>

                    @endif

                @endif

                @if ($selectedCourse)

                    @php
                        $selectedCourseObject = $courses
                            ->firstWhere(
                                'normalized_name',
                                $selectedCourse
                            );
                    @endphp

                    @if ($selectedCourseObject)

                        <span
                            class="rounded-full bg-slate-100 px-3 py-1.5
                                   text-slate-700"
                        >
                            Curso: {{ $selectedCourseObject->name }}
                        </span>

                    @endif

                @endif

            </div>

        </section>


        {{-- Nenhum resultado --}}
        @if ($totalResults === 0)

            <section
                class="mt-8 rounded-3xl border border-slate-200
                       bg-white px-6 py-14 text-center"
            >

                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center
                           rounded-2xl bg-slate-100 text-2xl"
                >
                    🔎
                </div>

                <h2 class="mt-5 text-xl font-bold">
                    Nenhuma oferta encontrada
                </h2>

                <p class="mx-auto mt-2 max-w-xl text-slate-600">
                    Tente remover o filtro de modalidade ou pesquisar
                    outro município e curso.
                </p>

                <a
                    href="{{ route('universities.index') }}"
                    class="mt-6 inline-flex rounded-xl bg-violet-700
                           px-5 py-3 text-sm font-semibold text-white
                           hover:bg-violet-800"
                >
                    Fazer nova pesquisa
                </a>

            </section>

        @endif


        {{-- Universidades públicas --}}
        @if ($publicOfferings && $publicOfferings->total() > 0)

            <section class="mt-10">

                <div
                    class="mb-5 flex flex-col gap-2 sm:flex-row
                           sm:items-end sm:justify-between"
                >

                    <div>

                        <p
                            class="text-sm font-bold uppercase
                                   tracking-wider text-emerald-700"
                        >
                            Ensino público
                        </p>

                        <h2 class="mt-1 text-2xl font-bold">
                            Universidades públicas
                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Instituições federais, estaduais ou municipais.
                        </p>

                    </div>

                    <span
                        class="w-fit rounded-full bg-emerald-100
                               px-3 py-1.5 text-sm font-semibold
                               text-emerald-800"
                    >
                        {{ $publicOfferings->total() }}
                        {{ $publicOfferings->total() === 1
                            ? 'oferta'
                            : 'ofertas' }}
                    </span>

                </div>


                <div class="grid gap-5 lg:grid-cols-2">

                    @foreach ($publicOfferings as $offering)

                        @php
                            $campus = $offering->campus;
                            $university = $campus->university;
                        @endphp

                        <article
                            class="flex h-full flex-col rounded-3xl border
                                   border-slate-200 bg-white p-6 shadow-sm
                                   transition hover:-translate-y-0.5
                                   hover:shadow-md"
                        >

                            <div class="flex items-start justify-between gap-4">

                                <div>

                                    <span
                                        class="inline-flex rounded-full
                                               bg-emerald-100 px-3 py-1
                                               text-xs font-bold
                                               text-emerald-800"
                                    >
                                        Pública
                                    </span>

                                    <p
                                        class="mt-3 text-sm font-semibold
                                               text-violet-700"
                                    >
                                        {{ $university->name }}
                                    </p>

                                    <h3
                                        class="mt-1 text-xl font-bold
                                               text-slate-900"
                                    >
                                        {{ $offering->name }}
                                    </h3>

                                </div>

                            </div>


                            <p class="mt-3 text-sm text-slate-600">
                                {{ $campus->city }}/{{ $campus->state }}

                                @if ($campus->name)
                                    · {{ $campus->name }}
                                @endif
                            </p>


                            <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">

                                <div
                                    class="rounded-2xl bg-slate-50 p-4"
                                >
                                    <dt class="text-xs text-slate-500">
                                        Grau
                                    </dt>

                                    <dd class="mt-1 font-semibold">
                                        {{ $offering->degree
                                            ?: 'Não informado' }}
                                    </dd>
                                </div>

                                <div
                                    class="rounded-2xl bg-slate-50 p-4"
                                >
                                    <dt class="text-xs text-slate-500">
                                        Modalidade
                                    </dt>

                                    <dd class="mt-1 font-semibold">
                                        {{ $offering->modality
                                            ?: 'Não informada' }}
                                    </dd>
                                </div>

                                <div
                                    class="rounded-2xl bg-slate-50 p-4"
                                >
                                    <dt class="text-xs text-slate-500">
                                        Carga horária
                                    </dt>

                                    <dd class="mt-1 font-semibold">
                                        @if ($offering->workload_hours)
                                            {{ number_format(
                                                $offering->workload_hours,
                                                0,
                                                ',',
                                                '.'
                                            ) }}
                                            horas
                                        @else
                                            Não informada
                                        @endif
                                    </dd>
                                </div>

                                <div
                                    class="rounded-2xl bg-slate-50 p-4"
                                >
                                    <dt class="text-xs text-slate-500">
                                        Vagas autorizadas
                                    </dt>

                                    <dd class="mt-1 font-semibold">
                                        {{ $offering->authorized_vacancies
                                            ?? 'Não informado' }}
                                    </dd>
                                </div>

                            </dl>


                            @if (
                                $university->admissionMethods &&
                                $university->admissionMethods->isNotEmpty()
                            )

                                <div class="mt-5">

                                    <p class="text-xs font-semibold text-slate-500">
                                        Formas de ingresso
                                    </p>

                                    <div class="mt-2 flex flex-wrap gap-2">

                                        @foreach (
                                            $university->admissionMethods
                                            as $method
                                        )

                                            <span
                                                class="rounded-full
                                                       bg-violet-50 px-3 py-1
                                                       text-xs font-semibold
                                                       text-violet-700"
                                            >
                                                {{ $method->name }}
                                            </span>

                                        @endforeach

                                    </div>

                                </div>

                            @endif


                            <div
                                class="mt-auto flex flex-wrap gap-3
                                       border-t border-slate-100 pt-5"
                            >

                                @if (Route::has('course-offerings.show'))

                                    <a
                                        href="{{ route(
                                            'course-offerings.show',
                                            $offering
                                        ) }}"
                                        class="rounded-xl bg-violet-700
                                               px-4 py-2.5 text-sm
                                               font-semibold text-white
                                               hover:bg-violet-800"
                                    >
                                        Ver detalhes do curso
                                    </a>

                                @endif

                                <a
                                    href="{{ route(
                                        'universities.show',
                                        $university
                                    ) }}"
                                    class="rounded-xl border
                                           border-slate-300 px-4 py-2.5
                                           text-sm font-semibold
                                           text-slate-700
                                           hover:bg-slate-100"
                                >
                                    Ver universidade
                                </a>

                            </div>


                            <p class="mt-4 text-xs text-slate-400">
                                Fonte:
                                {{ $offering->source_name
                                    ?: 'MEC' }}

                                @if ($offering->source_updated_at)
                                    · referência de
                                    {{ $offering
                                        ->source_updated_at
                                        ->format('d/m/Y') }}
                                @endif
                            </p>

                        </article>

                    @endforeach

                </div>


                <div class="mt-7">
                    {{ $publicOfferings->links() }}
                </div>

            </section>

        @endif


        {{-- Universidades privadas --}}
        @if ($privateOfferings && $privateOfferings->total() > 0)

            <section class="mt-14">

                <div
                    class="mb-5 flex flex-col gap-2 sm:flex-row
                           sm:items-end sm:justify-between"
                >

                    <div>

                        <p
                            class="text-sm font-bold uppercase
                                   tracking-wider text-amber-700"
                        >
                            Ensino privado
                        </p>

                        <h2 class="mt-1 text-2xl font-bold">
                            Universidades privadas
                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Consulte bolsas, financiamento e formas
                            de ingresso disponíveis.
                        </p>

                    </div>

                    <span
                        class="w-fit rounded-full bg-amber-100
                               px-3 py-1.5 text-sm font-semibold
                               text-amber-800"
                    >
                        {{ $privateOfferings->total() }}
                        {{ $privateOfferings->total() === 1
                            ? 'oferta'
                            : 'ofertas' }}
                    </span>

                </div>


                <div class="grid gap-5 lg:grid-cols-2">

                    @foreach ($privateOfferings as $offering)

                        @php
                            $campus = $offering->campus;
                            $university = $campus->university;
                        @endphp

                        <article
                            class="flex h-full flex-col rounded-3xl border
                                   border-slate-200 bg-white p-6 shadow-sm
                                   transition hover:-translate-y-0.5
                                   hover:shadow-md"
                        >

                            <div>

                                <span
                                    class="inline-flex rounded-full
                                           bg-amber-100 px-3 py-1
                                           text-xs font-bold
                                           text-amber-800"
                                >
                                    Privada
                                </span>

                                <p
                                    class="mt-3 text-sm font-semibold
                                           text-violet-700"
                                >
                                    {{ $university->name }}
                                </p>

                                <h3
                                    class="mt-1 text-xl font-bold
                                           text-slate-900"
                                >
                                    {{ $offering->name }}
                                </h3>

                            </div>


                            <p class="mt-3 text-sm text-slate-600">
                                {{ $campus->city }}/{{ $campus->state }}

                                @if ($campus->name)
                                    · {{ $campus->name }}
                                @endif
                            </p>


                            <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">

                                <div
                                    class="rounded-2xl bg-slate-50 p-4"
                                >
                                    <dt class="text-xs text-slate-500">
                                        Grau
                                    </dt>

                                    <dd class="mt-1 font-semibold">
                                        {{ $offering->degree
                                            ?: 'Não informado' }}
                                    </dd>
                                </div>

                                <div
                                    class="rounded-2xl bg-slate-50 p-4"
                                >
                                    <dt class="text-xs text-slate-500">
                                        Modalidade
                                    </dt>

                                    <dd class="mt-1 font-semibold">
                                        {{ $offering->modality
                                            ?: 'Não informada' }}
                                    </dd>
                                </div>

                                <div
                                    class="rounded-2xl bg-slate-50 p-4"
                                >
                                    <dt class="text-xs text-slate-500">
                                        Carga horária
                                    </dt>

                                    <dd class="mt-1 font-semibold">
                                        @if ($offering->workload_hours)
                                            {{ number_format(
                                                $offering->workload_hours,
                                                0,
                                                ',',
                                                '.'
                                            ) }}
                                            horas
                                        @else
                                            Não informada
                                        @endif
                                    </dd>
                                </div>

                                <div
                                    class="rounded-2xl bg-slate-50 p-4"
                                >
                                    <dt class="text-xs text-slate-500">
                                        Vagas autorizadas
                                    </dt>

                                    <dd class="mt-1 font-semibold">
                                        {{ $offering->authorized_vacancies
                                            ?? 'Não informado' }}
                                    </dd>
                                </div>

                            </dl>


                            @if (
                                $university->admissionMethods &&
                                $university->admissionMethods->isNotEmpty()
                            )

                                <div class="mt-5">

                                    <p class="text-xs font-semibold text-slate-500">
                                        Formas de ingresso
                                    </p>

                                    <div class="mt-2 flex flex-wrap gap-2">

                                        @foreach (
                                            $university->admissionMethods
                                            as $method
                                        )

                                            <span
                                                class="rounded-full
                                                       bg-violet-50 px-3 py-1
                                                       text-xs font-semibold
                                                       text-violet-700"
                                            >
                                                {{ $method->name }}
                                            </span>

                                        @endforeach

                                    </div>

                                </div>

                            @endif


                            <div
                                class="mt-auto flex flex-wrap gap-3
                                       border-t border-slate-100 pt-5"
                            >

                                @if (Route::has('course-offerings.show'))

                                    <a
                                        href="{{ route(
                                            'course-offerings.show',
                                            $offering
                                        ) }}"
                                        class="rounded-xl bg-violet-700
                                               px-4 py-2.5 text-sm
                                               font-semibold text-white
                                               hover:bg-violet-800"
                                    >
                                        Ver detalhes do curso
                                    </a>

                                @endif

                                <a
                                    href="{{ route(
                                        'universities.show',
                                        $university
                                    ) }}"
                                    class="rounded-xl border
                                           border-slate-300 px-4 py-2.5
                                           text-sm font-semibold
                                           text-slate-700
                                           hover:bg-slate-100"
                                >
                                    Ver universidade
                                </a>

                            </div>


                            <p class="mt-4 text-xs text-slate-400">
                                Fonte:
                                {{ $offering->source_name
                                    ?: 'MEC' }}

                                @if ($offering->source_updated_at)
                                    · referência de
                                    {{ $offering
                                        ->source_updated_at
                                        ->format('d/m/Y') }}
                                @endif
                            </p>

                        </article>

                    @endforeach

                </div>


                <div class="mt-7">
                    {{ $privateOfferings->links() }}
                </div>

            </section>

        @endif

    @endif

</main>


<script>
    const stateSelect = document.getElementById('state');
    const citySelect = document.getElementById('city_code');
    const courseSelect = document.getElementById('course');

    const cityStatus = document.getElementById('city-status');
    const courseStatus = document.getElementById('course-status');

    const municipalitiesUrl = @json(
        route('universities.options.municipalities')
    );

    const coursesUrl = @json(
        route('universities.options.courses')
    );


    function setStatus(element, message, visible = true) {
        element.textContent = message;
        element.classList.toggle('hidden', !visible);
    }


    function resetCitySelect(message) {
        citySelect.innerHTML = '';

        const option = document.createElement('option');

        option.value = '';
        option.textContent = message;

        citySelect.appendChild(option);
    }


    function resetCourseSelect(message) {
        courseSelect.innerHTML = '';

        const option = document.createElement('option');

        option.value = '';
        option.textContent = message;

        courseSelect.appendChild(option);
    }


    stateSelect.addEventListener('change', async function () {
        const state = this.value;

        resetCitySelect(
            state
                ? 'Carregando municípios...'
                : 'Selecione primeiro um estado'
        );

        resetCourseSelect(
            'Selecione primeiro um município'
        );

        citySelect.disabled = true;
        courseSelect.disabled = true;

        setStatus(cityStatus, '', false);
        setStatus(courseStatus, '', false);

        if (!state) {
            return;
        }

        setStatus(
            cityStatus,
            'Buscando municípios disponíveis...'
        );

        try {
            const url = new URL(
                municipalitiesUrl,
                window.location.origin
            );

            url.searchParams.set('state', state);

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(
                    'Não foi possível carregar os municípios.'
                );
            }

            const municipalities = await response.json();

            resetCitySelect('Todos os municípios');

            municipalities.forEach(function (municipality) {
                const option = document.createElement('option');

                option.value = municipality.ibge_city_code;
                option.textContent = municipality.city;

                citySelect.appendChild(option);
            });

            citySelect.disabled = false;

            setStatus(
                cityStatus,
                `${municipalities.length} município(s) disponível(is).`
            );
        } catch (error) {
            console.error(error);

            resetCitySelect('Erro ao carregar municípios');

            setStatus(
                cityStatus,
                'Não foi possível carregar os municípios.'
            );
        }
    });


    citySelect.addEventListener('change', async function () {
        const state = stateSelect.value;
        const cityCode = this.value;

        resetCourseSelect(
            cityCode
                ? 'Carregando cursos...'
                : 'Selecione primeiro um município'
        );

        courseSelect.disabled = true;

        setStatus(courseStatus, '', false);

        if (!state || !cityCode) {
            return;
        }

        setStatus(
            courseStatus,
            'Buscando cursos disponíveis...'
        );

        try {
            const url = new URL(
                coursesUrl,
                window.location.origin
            );

            url.searchParams.set('state', state);
            url.searchParams.set('city_code', cityCode);

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(
                    'Não foi possível carregar os cursos.'
                );
            }

            const courses = await response.json();

            resetCourseSelect('Todos os cursos');

            courses.forEach(function (course) {
                const option = document.createElement('option');

                option.value = course.normalized_name;
                option.textContent = course.name;

                courseSelect.appendChild(option);
            });

            courseSelect.disabled = false;

            setStatus(
                courseStatus,
                `${courses.length} curso(s) disponível(is).`
            );
        } catch (error) {
            console.error(error);

            resetCourseSelect('Erro ao carregar cursos');

            setStatus(
                courseStatus,
                'Não foi possível carregar os cursos.'
            );
        }
    });
</script>

</body>
</html>