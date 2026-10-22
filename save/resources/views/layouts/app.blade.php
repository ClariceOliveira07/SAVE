<!doctype html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'SAVE') · SAVE</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="{{ auth()->check() ? 'app-shell' : 'auth-shell' }}">
        @auth
            <div class="app-frame">
                <aside class="sidebar">
                    <a class="brand" href="{{ route('pantry.index') }}" aria-label="SAVE, início">
                        <span class="brand-mark">S</span>
                        <span class="brand-name">SAVE<span>.</span></span>
                    </a>
                    <div class="sidebar-label">Sua cozinha</div>
                    <nav class="sidebar-nav" aria-label="Navegação principal">
                        <a class="nav-link {{ request()->routeIs('pantry.index') ? 'is-active' : '' }}" href="{{ route('pantry.index') }}">
                            <span class="nav-icon" aria-hidden="true">▦</span><span>Minha despensa</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('foods.scan') ? 'is-active' : '' }}" href="{{ route('foods.scan') }}">
                            <span class="nav-icon" aria-hidden="true">◉</span><span>Escanear alimentos</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('recipes.*') ? 'is-active' : '' }}" href="{{ route('recipes.index') }}">
                            <span class="nav-icon" aria-hidden="true">⌁</span><span>Receitas</span>
                        </a>
                    </nav>
                    <div class="sidebar-bottom">
                        <div class="user-chip">
                            <span class="user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="user-name">{{ auth()->user()->name }}</span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="signout-button" type="submit">Sair da conta</button>
                        </form>
                    </div>
                </aside>
                <main class="main-area">
                    <header class="topbar">
                        <span class="topbar-kicker">SAVE · DESPENSA INTELIGENTE</span>
                        <span class="topbar-status"><span></span> Sua cozinha, em dia</span>
                    </header>
                    @yield('content')
                </main>
            </div>
        @else
            <main class="auth-page">
                <a class="brand auth-brand" href="{{ route('login') }}" aria-label="SAVE">
                    <span class="brand-mark">S</span><span class="brand-name">SAVE<span>.</span></span>
                </a>
                @yield('content')
                <p class="auth-footnote">Menos desperdício. Mais cuidado.</p>
            </main>
        @endauth
    </body>
</html>