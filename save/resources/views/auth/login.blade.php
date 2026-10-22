@extends('layouts.app')

@section('title', 'Entrar')

@section('content')
    <section class="auth-panel">
        <p class="eyebrow">BEM-VINDA DE VOLTA</p>
        <h1>Entre na sua cozinha.</h1>
        <p class="auth-description">Acesse sua despensa e continue de onde parou.</p>
        @if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login') }}" class="auth-form">
            @csrf
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <label class="remember-option"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
            <button class="button button-primary auth-submit" type="submit">Entrar <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">Ainda não tem conta? <a href="{{ route('register') }}">Criar cadastro</a></p>
    </section>
@endsection