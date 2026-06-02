<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GroceryShare</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-50 min-h-screen antialiased">

    <!-- Header -->
    <header class="bg-gradient-to-r from-indigo-600 to-violet-600 shadow-lg">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-5 flex items-center gap-3">
            <div class="bg-white/20 rounded-xl p-2">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-white text-xl font-bold tracking-tight">GroceryShare</h1>
                <p class="text-white/80 text-xs">Split grocery bills with your sisters</p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>
