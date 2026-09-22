<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>KPFC Fleet Management</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                body { font-family: ui-sans-serif, system-ui, sans-serif; }
            </style>
        @endif
    </head>
    <body class="min-h-screen bg-[#f3f4f6] font-sans text-[#172033]">
        <div class="relative isolate min-h-screen overflow-hidden">

            <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="flex items-center gap-3" aria-label="KPFC Fleet Management home">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#2563eb] text-white shadow-lg shadow-blue-500/20">
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 17h14M7 17V8.5A1.5 1.5 0 0 1 8.5 7h7A1.5 1.5 0 0 1 17 8.5V17M5 17a2 2 0 1 0 4 0m10 0a2 2 0 1 0 4 0M7 11h10M9 7V5h6v2" />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-xs font-semibold uppercase tracking-[0.24em] text-[#174a8b]">KPFC</span>
                        <span class="block text-base font-semibold tracking-tight text-[#172033]">Fleet Management</span>
                    </span>
                </a>

                @if (Route::has('login'))
                    <nav>
                        @auth
                            <div class="flex items-center gap-3">
                                <div class="hidden text-right sm:block">
                                    <p class="text-sm font-medium text-[#172033]">{{ Auth::user()->name }}</p>
                                    <p class="text-xs capitalize text-slate-500">{{ Auth::user()->role }}</p>
                                </div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-white">
                                        Log out
                                    </button>
                                </form>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg bg-[#2563eb] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1d4ed8]">
                                Sign in
                            </a>
                        @endauth
                    </nav>
                @endif
            </header>

            <main class="mx-auto grid w-full max-w-6xl gap-8 px-4 pb-10 pt-8 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:px-8 lg:pt-12">
                <section>
                    <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-[#174a8b]">Fleet operations hub</p>
                    <h1 class="max-w-3xl text-3xl font-semibold tracking-tight text-[#172033] sm:text-5xl">Know where every vehicle is, and what it is doing.</h1>
                    <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">Monitor live vehicle locations, manage home bases, and coordinate deployments from one clear operational view.</p>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <a href="{{ url('/test-dashboard') }}" class="inline-flex items-center gap-2 rounded-lg bg-[#2563eb] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/10 transition hover:bg-[#1d4ed8]">
                            Open fleet dashboard
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 10h12m-5-5 5 5-5 5" />
                            </svg>
                        </a>
                        <span class="flex items-center gap-2 text-sm text-slate-500"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Fleet systems ready</span>
                    </div>
                </section>

                <section aria-label="Fleet management capabilities" class="grid items-stretch gap-4 sm:grid-cols-2">
                    <article class="h-full rounded-xl border border-slate-200 bg-white p-5 shadow-lg shadow-slate-200/70">
                        <span class="mb-5 flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-[#2563eb]">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-4.35 7-10a7 7 0 1 0-14 0c0 5.65 7 10 7 10Z" /><circle cx="12" cy="11" r="2.5" /></svg>
                        </span>
                        <h2 class="text-base font-semibold text-[#172033]">Live tracking</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">See current locations and movement across the full fleet.</p>
                    </article>
                    <article class="h-full rounded-xl border border-slate-200 bg-white p-5 shadow-lg shadow-slate-200/70">
                        <span class="mb-5 flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h10" /></svg>
                        </span>
                        <h2 class="text-base font-semibold text-[#172033]">Vehicle status</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Keep availability, assignments, and activity in view.</p>
                    </article>
                    <article class="h-full rounded-xl border border-slate-200 bg-white p-5 shadow-lg shadow-slate-200/70 sm:col-span-2">
                        <span class="mb-5 flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /><path stroke-linecap="round" d="M5 6v12" /></svg>
                        </span>
                        <h2 class="text-base font-semibold text-[#172033]">Deployment control</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Dispatch vehicles to the right shop or destination and follow each active mission.</p>
                    </article>
                </section>
            </main>
        </div>
    </body>
</html>
