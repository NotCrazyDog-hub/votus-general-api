<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        {{ $offering->name }} — {{ $offering->campus->university->name }}
    </title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

@php
    $campus = $offering->campus;
    $university = $campus->university;

    $activeCurriculum = $offering->curricula
        ->where('active', true)
        ->sortByDesc('version_year')
        ->first();

    $curricula = $offering->curricula
        ->sortByDesc('version_year');
@endphp

<main class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">

    <nav class="mb-7 flex flex-wrap items-center gap-2 text-sm">

        <a
            href="{{ route('universities.index') }}"
            class="font-semibold text-violet-700 hover:underline"
        >
            Universidades
        </a>

        <span class="text-slate-400">
            /
        </span>

        <a
            href="{{ route(
                'universities.show',
                $university
            ) }}"
            class="font-semibold text-violet-700 hover:underline"
        >
            {{ $university->name }}
        </a>

        <span class="text-slate-400">
            /
        </span>

        <span class="text-slate-600">
            {{ $offering->name }}
        </span>

    </nav>


    {{-- Informações principais --}}
    <header
        class="rounded-3xl border border-slate-200 bg-white
               p-7 shadow-sm sm:p-9"
    >

        <div
            class="flex flex-col gap-6 lg:flex-row
                   lg:items-start lg:justify-between"
        >

            <div class="max-w-3xl">

                <div class="flex flex-wrap gap-2">

                    @if ($university->sector === 'public')

                        <span
                            class="rounded-full bg-emerald-100
                                   px-3 py-1 text-xs font-bold
                                   text-emerald-800"
                        >
                            Instituição pública
                        </span>

                    @elseif ($university->sector === 'private')

                        <span
                            class="rounded-full bg-amber-100
                                   px-3 py-1 text-xs font-bold
                                   text-amber-800"
                        >
                            Instituição privada
                        </span>

                    @endif

                    @if ($offering->modality)

                        <span
                            class="rounded-full bg-violet-100
                                   px-3 py-1 text-xs font-bold
                                   text-violet-800"
                        >
                            {{ $offering->modality }}
                        </span>

                    @endif

                    @if ($offering->degree)

                        <span
                            class="rounded-full bg-slate-100
                                   px-3 py-1 text-xs font-bold
                                   text-slate-700"
                        >
                            {{ $offering->degree }}
                        </span>

                    @endif

                </div>


                <p class="mt-5 text-sm font-bold text-violet-700">
                    {{ $university->name }}
                </p>

                <h1
                    class="mt-2 text-3xl font-bold tracking-tight
                           sm:text-4xl"
                >
                    {{ $offering->name }}
                </h1>

                <p class="mt-4 text-slate-600">
                    {{ $campus->city }}/{{ $campus->state }}

                    @if ($campus->name)
                        · {{ $campus->name }}
                    @endif
                </p>

            </div>


            <a
                href="{{ route(
                    'universities.show',
                    $university
                ) }}"
                class="inline-flex w-fit items-center justify-center
                       rounded-xl border border-slate-300 px-5 py-3
                       text-sm font-semibold text-slate-700
                       transition hover:bg-slate-100"
            >
                Ver universidade
            </a>

        </div>

    </header>


    {{-- Dados do curso --}}
    <section class="mt-8">

        <div class="mb-5">

            <p
                class="text-sm font-bold uppercase
                       tracking-wider text-violet-700"
            >
                Informações do curso
            </p>

            <h2 class="mt-1 text-2xl font-bold">
                Dados gerais
            </h2>

        </div>


        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-5"
            >

                <dt class="text-sm text-slate-500">
                    Carga horária
                </dt>

                <dd class="mt-2 text-lg font-bold">

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
                class="rounded-2xl border border-slate-200
                       bg-white p-5"
            >

                <dt class="text-sm text-slate-500">
                    Vagas autorizadas
                </dt>

                <dd class="mt-2 text-lg font-bold">
                    {{ $offering->authorized_vacancies
                        ?? 'Não informado' }}
                </dd>

            </div>


            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-5"
            >

                <dt class="text-sm text-slate-500">
                    Modalidade
                </dt>

                <dd class="mt-2 text-lg font-bold">
                    {{ $offering->modality
                        ?: 'Não informada' }}
                </dd>

            </div>


            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-5"
            >

                <dt class="text-sm text-slate-500">
                    Grau
                </dt>

                <dd class="mt-2 text-lg font-bold">
                    {{ $offering->degree
                        ?: 'Não informado' }}
                </dd>

            </div>


            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-5"
            >

                <dt class="text-sm text-slate-500">
                    Área
                </dt>

                <dd class="mt-2 font-bold">
                    {{ $offering->area
                        ?: 'Não informada' }}
                </dd>

            </div>


            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-5"
            >

                <dt class="text-sm text-slate-500">
                    Situação
                </dt>

                <dd class="mt-2 font-bold">
                    {{ $offering->status
                        ?: 'Não informada' }}
                </dd>

            </div>


            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-5"
            >

                <dt class="text-sm text-slate-500">
                    Município
                </dt>

                <dd class="mt-2 font-bold">
                    {{ $campus->city }}/{{ $campus->state }}
                </dd>

            </div>


            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-5"
            >

                <dt class="text-sm text-slate-500">
                    Código do curso no MEC
                </dt>

                <dd class="mt-2 font-bold">
                    {{ $offering->mec_course_code }}
                </dd>

            </div>

        </dl>

    </section>


    {{-- Formas de ingresso --}}
    <section class="mt-10">

        <div class="mb-5">

            <p
                class="text-sm font-bold uppercase
                       tracking-wider text-violet-700"
            >
                Como entrar
            </p>

            <h2 class="mt-1 text-2xl font-bold">
                Formas de ingresso
            </h2>

        </div>


        @if (
            $university->admissionMethods &&
            $university->admissionMethods->isNotEmpty()
        )

            <div class="grid gap-4 md:grid-cols-2">

                @foreach (
                    $university->admissionMethods
                    as $method
                )

                    <article
                        class="rounded-2xl border border-slate-200
                               bg-white p-5"
                    >

                        <div
                            class="flex items-start
                                   justify-between gap-4"
                        >

                            <div>

                                <h3 class="font-bold">
                                    {{ $method->name }}
                                </h3>

                                @if ($method->description)

                                    <p
                                        class="mt-2 text-sm
                                               leading-6 text-slate-600"
                                    >
                                        {{ $method->description }}
                                    </p>

                                @endif

                            </div>


                            <span
                                class="rounded-full bg-violet-100
                                       px-3 py-1 text-xs font-bold
                                       text-violet-800"
                            >
                                {{ $method->type }}
                            </span>

                        </div>


                        @if ($method->official_url)

                            <a
                                href="{{ $method->official_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-flex font-semibold
                                       text-violet-700 hover:underline"
                            >
                                Acessar página oficial
                            </a>

                        @endif


                        @if ($method->verified_at)

                            <p class="mt-3 text-xs text-slate-400">
                                Informação verificada em
                                {{ $method->verified_at->format(
                                    'd/m/Y'
                                ) }}
                            </p>

                        @endif

                    </article>

                @endforeach

            </div>

        @else

            <div
                class="rounded-2xl border border-slate-200
                       bg-white p-6 text-slate-600"
            >
                As formas de ingresso desta instituição ainda
                não foram cadastradas ou verificadas.
            </div>

        @endif

    </section>


    {{-- Matriz curricular --}}
    <section class="mt-10">

        <div class="mb-5">

            <p
                class="text-sm font-bold uppercase
                       tracking-wider text-violet-700"
            >
                Formação
            </p>

            <h2 class="mt-1 text-2xl font-bold">
                Matriz curricular e disciplinas
            </h2>

            <p class="mt-2 text-sm text-slate-600">
                As disciplinas podem variar conforme o ano
                e a versão da matriz curricular.
            </p>

        </div>


        @if ($curricula->isNotEmpty())

            <div class="space-y-6">

                @foreach ($curricula as $curriculum)

                    <article
                        class="overflow-hidden rounded-3xl border
                               border-slate-200 bg-white"
                    >

                        <header
                            class="border-b border-slate-200
                                   bg-slate-50 p-6"
                        >

                            <div
                                class="flex flex-col gap-4
                                       sm:flex-row sm:items-start
                                       sm:justify-between"
                            >

                                <div>

                                    <div class="flex flex-wrap gap-2">

                                        @if ($curriculum->active)

                                            <span
                                                class="rounded-full
                                                       bg-emerald-100
                                                       px-3 py-1 text-xs
                                                       font-bold
                                                       text-emerald-800"
                                            >
                                                Matriz atual
                                            </span>

                                        @endif

                                        @if ($curriculum->version_year)

                                            <span
                                                class="rounded-full
                                                       bg-slate-200
                                                       px-3 py-1 text-xs
                                                       font-bold
                                                       text-slate-700"
                                            >
                                                Versão
                                                {{ $curriculum->version_year }}
                                            </span>

                                        @endif

                                    </div>

                                    <h3 class="mt-3 text-xl font-bold">
                                        {{ $curriculum->name }}
                                    </h3>

                                </div>


                                <div
                                    class="flex flex-wrap gap-3
                                           text-sm text-slate-600"
                                >

                                    @if ($curriculum->total_hours)

                                        <span
                                            class="rounded-xl bg-white
                                                   px-3 py-2"
                                        >
                                            {{ number_format(
                                                $curriculum->total_hours,
                                                0,
                                                ',',
                                                '.'
                                            ) }}
                                            horas
                                        </span>

                                    @endif

                                    @if ($curriculum->duration_semesters)

                                        <span
                                            class="rounded-xl bg-white
                                                   px-3 py-2"
                                        >
                                            {{ $curriculum
                                                ->duration_semesters }}
                                            semestres
                                        </span>

                                    @endif

                                </div>

                            </div>


                            @if ($curriculum->official_url)

                                <a
                                    href="{{ $curriculum->official_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-4 inline-flex text-sm
                                           font-semibold text-violet-700
                                           hover:underline"
                                >
                                    Consultar matriz oficial
                                </a>

                            @endif

                        </header>


                        <div class="p-6">

                            @if ($curriculum->subjects->isNotEmpty())

                                @php
                                    $subjectsBySemester =
                                        $curriculum
                                            ->subjects
                                            ->groupBy(
                                                fn ($subject) =>
                                                    $subject->semester
                                                    ?? 'sem_semestre'
                                            );
                                @endphp

                                <div class="space-y-7">

                                    @foreach (
                                        $subjectsBySemester
                                        as $semester => $subjects
                                    )

                                        <div>

                                            <h4
                                                class="mb-3 text-lg
                                                       font-bold"
                                            >

                                                @if (
                                                    $semester ===
                                                    'sem_semestre'
                                                )

                                                    Outras disciplinas

                                                @else

                                                    {{ $semester }}º semestre

                                                @endif

                                            </h4>


                                            <div
                                                class="divide-y
                                                       divide-slate-100
                                                       rounded-2xl border
                                                       border-slate-200"
                                            >

                                                @foreach (
                                                    $subjects
                                                    as $subject
                                                )

                                                    <div
                                                        class="flex flex-col
                                                               gap-2 p-4
                                                               sm:flex-row
                                                               sm:items-center
                                                               sm:justify-between"
                                                    >

                                                        <div>

                                                            <p
                                                                class="font-semibold"
                                                            >
                                                                {{ $subject->name }}
                                                            </p>

                                                            @if ($subject->type)

                                                                <p
                                                                    class="mt-1
                                                                           text-xs
                                                                           text-slate-500"
                                                                >
                                                                    {{ ucfirst(
                                                                        str_replace(
                                                                            '_',
                                                                            ' ',
                                                                            $subject->type
                                                                        )
                                                                    ) }}
                                                                </p>

                                                            @endif

                                                        </div>


                                                        @if (
                                                            $subject
                                                                ->workload_hours
                                                        )

                                                            <span
                                                                class="w-fit
                                                                       rounded-full
                                                                       bg-slate-100
                                                                       px-3 py-1
                                                                       text-sm
                                                                       font-semibold
                                                                       text-slate-700"
                                                            >
                                                                {{ $subject
                                                                    ->workload_hours }}
                                                                horas
                                                            </span>

                                                        @endif

                                                    </div>

                                                @endforeach

                                            </div>

                                        </div>

                                    @endforeach

                                </div>

                            @else

                                <div
                                    class="rounded-2xl bg-slate-50
                                           p-6 text-center"
                                >

                                    <h4 class="font-bold">
                                        Disciplinas ainda não cadastradas
                                    </h4>

                                    <p
                                        class="mt-2 text-sm
                                               text-slate-600"
                                    >
                                        A matriz curricular foi cadastrada,
                                        mas as disciplinas ainda não foram
                                        adicionadas ao sistema.
                                    </p>

                                </div>

                            @endif

                        </div>

                    </article>

                @endforeach

            </div>

        @else

            <div
                class="rounded-3xl border border-slate-200
                       bg-white p-8 text-center"
            >

                <div
                    class="mx-auto flex h-14 w-14
                           items-center justify-center
                           rounded-2xl bg-violet-100 text-2xl"
                >
                    📚
                </div>

                <h3 class="mt-5 text-lg font-bold">
                    Matriz curricular ainda não cadastrada
                </h3>

                <p
                    class="mx-auto mt-2 max-w-xl
                           text-sm leading-6 text-slate-600"
                >
                    As disciplinas deste curso não estão presentes
                    no arquivo do MEC. Elas deverão ser obtidas
                    na página oficial da instituição ou no Projeto
                    Pedagógico do Curso.
                </p>

            </div>

        @endif

    </section>


    {{-- Fonte --}}
    <footer
        class="mt-10 rounded-2xl border border-slate-200
               bg-white p-5 text-sm text-slate-500"
    >

        <p>
            Fonte principal:
            <strong class="text-slate-700">
                {{ $offering->source_name ?: 'MEC' }}
            </strong>.
        </p>

        @if ($offering->source_updated_at)

            <p class="mt-1">
                Data de referência:
                {{ $offering->source_updated_at->format(
                    'd/m/Y'
                ) }}.
            </p>

        @endif

        <p class="mt-1">
            Consulte sempre a instituição para confirmar
            informações atuais sobre duração, vagas,
            currículo e formas de ingresso.
        </p>

    </footer>

</main>

</body>
</html>