<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>{{ $university->name }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

    <main class="mx-auto max-w-6xl px-4 py-10">

        <a
            href="{{ route('universities.index') }}"
            class="font-semibold text-violet-700 hover:underline"
        >
            ← Voltar para a pesquisa
        </a>


        <header
            class="mt-6 rounded-2xl border border-slate-200
                   bg-white p-7 shadow-sm"
        >

            <p class="text-sm font-semibold text-violet-700">
                Instituição de ensino superior
            </p>

            <h1 class="mt-2 text-3xl font-bold">
                {{ $university->name }}
            </h1>

            <div class="mt-4 flex flex-wrap gap-2">

                @if ($university->administrative_category)

                    <span
                        class="rounded-full bg-slate-100
                               px-3 py-1 text-sm"
                    >
                        {{ $university->administrative_category }}
                    </span>

                @endif

                @if ($university->academic_organization)

                    <span
                        class="rounded-full bg-slate-100
                               px-3 py-1 text-sm"
                    >
                        {{ $university->academic_organization }}
                    </span>

                @endif

            </div>

        </header>


        <section class="mt-8">

            <h2 class="text-2xl font-bold">
                Formas de ingresso
            </h2>

            @forelse (
                $university->admissionMethods
                as $method
            )

                <article
                    class="mt-4 rounded-2xl border border-slate-200
                           bg-white p-5"
                >

                    <h3 class="font-bold">
                        {{ $method->name }}
                    </h3>

                    @if ($method->description)

                        <p class="mt-2 text-slate-600">
                            {{ $method->description }}
                        </p>

                    @endif

                    @if ($method->official_url)

                        <a
                            href="{{ $method->official_url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-3 inline-block font-semibold
                                   text-violet-700 hover:underline"
                        >
                            Acessar página oficial
                        </a>

                    @endif

                    @if ($method->verified_at)

                        <p class="mt-3 text-xs text-slate-400">
                            Verificado em
                            {{ $method->verified_at->format(
                                'd/m/Y'
                            ) }}
                        </p>

                    @endif

                </article>

            @empty

                <div
                    class="mt-4 rounded-2xl border border-slate-200
                           bg-white p-5 text-slate-600"
                >
                    As formas de ingresso ainda não foram
                    cadastradas ou verificadas.
                </div>

            @endforelse

        </section>


        <section class="mt-10">

            <h2 class="text-2xl font-bold">
                Cursos e locais de oferta
            </h2>

            @foreach ($university->campuses as $campus)

                <div
                    class="mt-5 rounded-2xl border border-slate-200
                           bg-white p-6"
                >

                    <h3 class="text-lg font-bold">
                        {{ $campus->name ?: 'Local de oferta' }}
                        — {{ $campus->city }}/{{ $campus->state }}
                    </h3>

                    <div class="mt-4 divide-y divide-slate-100">

                        @foreach (
                            $campus->courseOfferings
                            as $offering
                        )

                            <article class="py-4">

                                <h4 class="font-bold">
                                    {{ $offering->name }}
                                </h4>

                                <div
                                    class="mt-2 flex flex-wrap gap-x-5
                                           gap-y-2 text-sm text-slate-600"
                                >

                                    <span>
                                        {{ $offering->degree
                                            ?: 'Grau não informado' }}
                                    </span>

                                    <span>
                                        {{ $offering->modality
                                            ?: 'Modalidade não informada' }}
                                    </span>

                                    @if ($offering->workload_hours)

                                        <span>
                                            {{ number_format(
                                                $offering->workload_hours,
                                                0,
                                                ',',
                                                '.'
                                            ) }}
                                            horas
                                        </span>

                                    @endif

                                </div>

                            </article>

                        @endforeach

                    </div>

                </div>

            @endforeach

        </section>

    </main>

</body>
</html>