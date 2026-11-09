@php
    use App\Enums\IdeaStatus;
    use App\Models\Idea;
    use App\Models\Vote;

    $loginUrl = route('filament.app.auth.login');
    $boardUrl = filament()->getPanel('app')->getUrl() ?? $loginUrl;

    $stats = [
        ['value' => Idea::query()->count(), 'label' => 'ideas on the board'],
        ['value' => Idea::query()->where('status', IdeaStatus::Implemented)->count(), 'label' => 'implemented'],
        ['value' => Vote::query()->where('created_at', '>=', now()->startOfQuarter())->count(), 'label' => 'votes this quarter'],
    ];

    $heroRows = [
        ['votes' => 24, 'status' => 'Under review', 'badge' => 'bg-yellow-100 text-yellow-800', 'title' => 'w-4/5', 'meta' => 'w-1/2'],
        ['votes' => 17, 'status' => 'New', 'badge' => 'bg-slate-100 text-slate-700', 'title' => 'w-2/3', 'meta' => 'w-2/5'],
        ['votes' => 9, 'status' => 'Approved', 'badge' => 'bg-green-100 text-green-800', 'title' => 'w-[72%]', 'meta' => 'w-[45%]'],
    ];

    $steps = [
        ['Submit', 'Describe the problem, your proposal and the expected impact. Post under your name or anonymously.'],
        ['Vote & discuss', 'Colleagues upvote with one click and comment. The best ideas rise to the top of the board.'],
        ['Follow the outcome', 'Reviewers move ideas from New to Implemented, and every status change is recorded in a visible history.'],
    ];

    $sampleIdeas = [
        ['status' => 'Under review', 'badge' => 'bg-yellow-100 text-yellow-800', 'votes' => 24],
        ['status' => 'New', 'badge' => 'bg-slate-100 text-slate-700', 'votes' => 17],
        ['status' => 'Approved', 'badge' => 'bg-green-100 text-green-800', 'votes' => 12],
    ];

    $roles = [
        ['Employee', 'Submit ideas, vote, comment and track your own ideas.'],
        ['Reviewer', 'Change idea status and moderate comments.'],
        ['Admin', 'Manage users, roles and categories, and export results.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Idea Board') }} - {{ __('Suggestion box with voting') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white font-[Inter,ui-sans-serif,system-ui,sans-serif] text-slate-900 antialiased">
        {{-- NAV --}}
        <header class="landing-fade border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-green-700 font-extrabold text-white" aria-hidden="true">I</span>
                    <strong class="text-xl font-bold tracking-tight">Idea Board</strong>
                </a>
                <nav class="flex flex-wrap items-center gap-2 text-sm font-medium" aria-label="Primary">
                    <a href="#how" class="px-3 py-3 text-slate-600 hover:text-slate-900">How it works</a>
                    <a href="#top" class="px-3 py-3 text-slate-600 hover:text-slate-900">Top ideas</a>
                    <a href="#roles" class="px-3 py-3 text-slate-600 hover:text-slate-900">Roles</a>
                    <a href="{{ $loginUrl }}" class="landing-btn rounded-lg bg-green-700 px-4 py-2.5 font-semibold text-white shadow-xs hover:bg-green-800">Log in</a>
                </nav>
            </div>
        </header>

        <main>
            {{-- HERO --}}
            <section class="landing-fade bg-gradient-to-b from-green-50 to-white">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-16 px-6 py-24">
                    <div class="flex min-w-0 flex-[1_1_440px] flex-col items-start gap-6">
                        <span class="inline-block rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">Suggestion box with voting</span>
                        <h1 class="text-5xl leading-none font-extrabold tracking-tight sm:text-[56px]">
                            Got an idea? Put it on the <span class="text-green-700">board.</span>
                        </h1>
                        <p class="max-w-[520px] text-xl text-slate-600">Share improvement ideas, vote on the ones that matter, and see exactly what happens to each of them.</p>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ $loginUrl }}" class="landing-btn landing-pulse rounded-lg bg-green-700 px-6 py-3.5 font-semibold text-white shadow-xs hover:bg-green-800">Submit an idea</a>
                            <a href="{{ $boardUrl }}" class="landing-btn rounded-lg border border-slate-300 bg-white px-6 py-3.5 font-semibold text-slate-900 shadow-xs">Browse the board</a>
                        </div>
                        <dl class="mt-4 flex flex-wrap gap-10 text-sm text-slate-600">
                            @foreach ($stats as $stat)
                                <div>
                                    <dt class="sr-only">{{ $stat['label'] }}</dt>
                                    <dd><strong class="block text-3xl font-bold text-slate-900">{{ number_format($stat['value']) }}</strong>{{ $stat['label'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div class="landing-float min-w-0 flex-[1_1_400px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" aria-hidden="true">
                        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                            <span class="size-2.5 rounded-full bg-slate-300"></span>
                            <span class="size-2.5 rounded-full bg-slate-300"></span>
                            <span class="size-2.5 rounded-full bg-slate-300"></span>
                            <span class="ms-2 text-xs text-slate-500">Idea board</span>
                        </div>
                        <div class="flex flex-col gap-3 p-4">
                            @foreach ($heroRows as $row)
                                <div class="landing-row flex items-center gap-4 rounded-xl border border-slate-200 p-4">
                                    <div class="flex h-[60px] w-[52px] flex-col items-center justify-center rounded-lg bg-green-100 text-sm font-bold text-green-800">▲<span class="text-lg">{{ $row['votes'] }}</span></div>
                                    <div class="flex flex-1 flex-col gap-2">
                                        <div class="h-3 {{ $row['title'] }} rounded-full bg-slate-300"></div>
                                        <div class="h-2 {{ $row['meta'] }} rounded-full bg-slate-200"></div>
                                    </div>
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap {{ $row['badge'] }}">{{ $row['status'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            {{-- HOW IT WORKS --}}
            <section id="how" class="landing-fade bg-white">
                <div class="mx-auto flex max-w-6xl flex-col gap-12 px-6 py-20">
                    <div class="flex max-w-2xl flex-col gap-3">
                        <span class="text-sm font-semibold tracking-wider text-green-700 uppercase">How it works</span>
                        <h2 class="text-4xl leading-10 font-bold tracking-tight">From a spark to a shipped improvement</h2>
                    </div>
                    <div class="grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-6">
                        @foreach ($steps as [$title, $text])
                            <div class="landing-card flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-8 shadow-xs">
                                <div class="flex size-11 items-center justify-center rounded-xl bg-green-100 font-bold text-green-800">{{ $loop->iteration }}</div>
                                <h3 class="text-xl font-semibold">{{ $title }}</h3>
                                <p class="text-slate-600">{{ $text }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- TOP IDEAS --}}
            <section id="top" class="landing-fade border-y border-slate-200 bg-slate-50">
                <div class="mx-auto flex max-w-6xl flex-col gap-12 px-6 py-20">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="flex flex-col gap-3">
                            <span class="text-sm font-semibold tracking-wider text-green-700 uppercase">Leaderboard of ideas</span>
                            <h2 class="text-4xl leading-10 font-bold tracking-tight">Top ideas right now</h2>
                        </div>
                        <a href="{{ $boardUrl }}" class="py-3 font-semibold text-green-700 hover:text-green-800">See the full board →</a>
                    </div>
                    <div class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-6">
                        @foreach ($sampleIdeas as $idea)
                            <div class="landing-card flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                                <div class="flex items-center justify-between">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">Category</span>
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $idea['badge'] }}">{{ $idea['status'] }}</span>
                                </div>
                                <h3 class="text-lg font-semibold text-slate-900">Your idea title goes here</h3>
                                <div class="flex flex-col gap-2" aria-hidden="true">
                                    <div class="h-2 rounded-full bg-slate-200"></div>
                                    <div class="h-2 w-4/5 rounded-full bg-slate-200"></div>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-sm text-slate-500">by a colleague</span>
                                    <span class="rounded-lg bg-green-100 px-3 py-2 text-sm font-bold text-green-800">▲ {{ $idea['votes'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-sm text-slate-500">Example cards. Log in to see the real top ideas.</p>
                </div>
            </section>

            {{-- STATUS WORKFLOW --}}
            <section class="landing-fade bg-white">
                <div class="mx-auto flex max-w-6xl flex-col gap-8 px-6 py-20">
                    <div class="flex max-w-2xl flex-col gap-3">
                        <span class="text-sm font-semibold tracking-wider text-green-700 uppercase">Transparency</span>
                        <h2 class="text-4xl leading-10 font-bold tracking-tight">Every idea has a visible status</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-sm font-semibold">
                        <span class="rounded-full bg-slate-100 px-4 py-2 text-slate-700">New</span>
                        <span aria-hidden="true" class="text-slate-500">→</span>
                        <span class="rounded-full bg-yellow-100 px-4 py-2 text-yellow-800">Under review</span>
                        <span aria-hidden="true" class="text-slate-500">→</span>
                        <span class="rounded-full bg-green-100 px-4 py-2 text-green-800">Approved</span>
                        <span aria-hidden="true" class="text-slate-500">→</span>
                        <span class="rounded-full bg-green-700 px-4 py-2 text-white">Implemented</span>
                        <span class="ms-4 rounded-full bg-red-100 px-4 py-2 text-red-800">Rejected (with reason)</span>
                    </div>
                    <p class="max-w-3xl text-lg text-slate-600">Votes stay open while an idea is New or Under review. Reviewers always leave a note when rejecting or implementing.</p>
                </div>
            </section>

            {{-- ROLES --}}
            <section id="roles" class="landing-fade border-y border-green-200 bg-green-50">
                <div class="mx-auto flex max-w-6xl flex-col gap-12 px-6 py-20">
                    <div class="flex max-w-2xl flex-col gap-3">
                        <span class="text-sm font-semibold tracking-wider text-green-700 uppercase">Roles</span>
                        <h2 class="text-4xl leading-10 font-bold tracking-tight">Built for everyone in the company</h2>
                    </div>
                    <div class="grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-6">
                        @foreach ($roles as [$role, $text])
                            <div class="landing-card flex flex-col gap-2 rounded-2xl border border-green-200 bg-white p-8 shadow-xs">
                                <h3 class="text-xl font-semibold">{{ $role }}</h3>
                                <p class="text-slate-600">{{ $text }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- LEADERBOARD --}}
            <section class="landing-fade bg-white">
                <div class="mx-auto flex max-w-6xl flex-wrap gap-12 px-6 py-20">
                    <div class="flex min-w-0 flex-[1_1_380px] flex-col gap-3">
                        <span class="text-sm font-semibold tracking-wider text-green-700 uppercase">Recognition</span>
                        <h2 class="text-4xl leading-10 font-bold tracking-tight">Top contributors this quarter</h2>
                        <p class="text-lg text-slate-600">Ranked by Approved and Implemented ideas. Anonymous ideas never count.</p>
                    </div>
                    <ol class="flex min-w-0 flex-[1_1_380px] flex-col gap-3" aria-label="Example contributor ranking">
                        @foreach ([5, 3, 2] as $count)
                            <li class="landing-card flex items-center gap-4 rounded-xl border border-slate-200 px-5 py-4 shadow-xs">
                                <strong class="w-6 text-green-700">{{ $loop->iteration }}</strong>
                                <span class="size-10 rounded-full bg-green-200" aria-hidden="true"></span>
                                <span class="flex-1 text-slate-600">A top contributor</span>
                                <strong>{{ $count }} ideas</strong>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- FINAL CTA --}}
            <section class="landing-fade bg-green-900 text-white">
                <div class="mx-auto flex max-w-6xl flex-col items-center gap-6 px-6 py-20 text-center">
                    <h2 class="text-4xl leading-10 font-bold tracking-tight">Your next big improvement starts here.</h2>
                    <p class="text-lg text-green-200">Log in with your work account and post your first idea in a minute.</p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <a href="{{ $loginUrl }}" class="landing-btn rounded-lg bg-white px-6 py-3.5 font-semibold text-green-900">Log in to submit</a>
                        <a href="{{ $boardUrl }}" class="landing-btn rounded-lg border border-green-300 px-6 py-3.5 font-semibold text-white">Browse ideas</a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap justify-between gap-4 px-6 py-6 text-sm text-slate-600">
                <span>{{ config('app.name', 'Idea Board') }}</span>
                <span>&copy; {{ now()->year }}</span>
            </div>
        </footer>
    </body>
</html>
