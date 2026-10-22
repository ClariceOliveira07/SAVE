@extends('layouts.app')

@section('title', 'Criar conta')

@section('content')
    <section class="auth-panel">
        <p class="eyebrow">COMECE POR AQUI</p>
        <h1>Cuide melhor do que você compra.</h1>
        <p class="auth-description">Crie sua conta para organizar os alimentos da sua casa.</p>
        @if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('register') }}" class="auth-form">
            @csrf
            <label for="name">Nome</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus>
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            <label for="password_confirmation">Confirme a senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <button class="button button-primary auth-submit" type="submit">Criar minha conta <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">Já tem conta? <a href="{{ route('login') }}">Entrar</a></p>
    </section>
@endsection