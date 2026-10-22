@extends('layouts.app')

@section('title', 'Receitas')

@section('content')
    <section class="page-content recipe-page" data-recipes data-generate-url="{{ route('recipes.generate') }}">
        <div class="page-heading">
            <div>
                <p class="eyebrow">COZINHAR COM O QUE JÁ TEM</p>
                <h1>Receitas para sua despensa</h1>
                <p class="page-intro">Sugestões com os alimentos que você confirmou no SAVE.</p>
            </div>
            <a class="button button-outline" href="{{ route('foods.scan') }}">Escanear alimentos <span aria-hidden="true">↗</span></a>
        </div>

        <section class="inventory-strip" aria-labelledby="inventory-heading">
            <div class="inventory-strip-head">
                <div><p class="eyebrow">SEU ESTOQUE</p><h2 id="inventory-heading">{{ $foods->count() }} alimentos confirmados</h2></div>
                @if ($foods->isNotEmpty())<span class="inventory-caption">Validades próximas aparecem primeiro</span>@endif
            </div>
            @if ($foods->isEmpty())
                <p class="inventory-empty">Adicione alimentos à despensa para encontrar receitas compatíveis.</p>
            @else
                <div class="ingredient-chips">
                    @foreach ($foods as $food)
                        <span class="ingredient-chip">
                            {{ $food->nome }}
                            @if ($food->validade)<small>· {{ $food->validade->format('d/m') }}</small>@endif
                        </span>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="recipe-heading-row">
            <div><p class="eyebrow">IDEIAS PARA HOJE</p><h2>Compatíveis com sua cozinha</h2></div>
            @if ($aiConfigured)
                <button class="button button-primary" type="button" data-generate-recipes><span aria-hidden="true">✳</span> Criar sugestões com IA</button>
            @else
                <span class="ai-unavailable">IA indisponível · configure OPENAI_API_KEY</span>
            @endif
        </div>
        <div class="notice notice-error" data-recipe-error role="alert" hidden></div>
        <div class="recipe-loading" data-recipe-loading hidden><span class="spinner"></span> Preparando sugestões com IA…</div>
        <div class="recipe-grid" data-recipe-grid>
            @foreach ($recipes as $recipe)
                <article class="recipe-card">
                    <div class="recipe-card-top"><span class="recipe-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span class="recipe-match">{{ collect($recipe['ingredients'])->where('available', true)->count() }} itens disponíveis</span></div>
                    <h3>{{ $recipe['title'] }}</h3>
                    <p class="recipe-summary">{{ $recipe['summary'] }}</p>
                    <div class="recipe-ingredients">
                        <h4>Ingredientes</h4>
                        @foreach ($recipe['ingredients'] as $ingredient)
                            <div class="recipe-ingredient {{ $ingredient['available'] ? 'is-available' : 'is-missing' }}">
                                <span class="ingredient-state" aria-hidden="true">{{ $ingredient['available'] ? '✓' : '+' }}</span>
                                <span>{{ $ingredient['name'] }}</span>
                                @if ($ingredient['expiring'])<small>validade cadastrada próxima</small>@endif
                                @if (! $ingredient['available'])<em>falta</em>@endif
                            </div>
                        @endforeach
                    </div>
                    <details class="recipe-steps"><summary>Ver preparo</summary><ol>@foreach ($recipe['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol></details>
                </article>
            @endforeach
        </div>
        <p class="recipe-disclaimer">As receitas não avaliam se um alimento está próprio para consumo. Confira a validade cadastrada e as condições do produto antes de usar.</p>
    </section>
@endsection