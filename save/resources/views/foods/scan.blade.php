@extends('layouts.app')

@section('title', 'Escanear alimentos')

@section('content')
    <section class="page-content scanner-page" data-scanner
        data-analyze-url="{{ route('foods.analyze') }}"
        data-save-url="{{ route('foods.store') }}">
        <div class="page-heading">
            <div>
                <p class="eyebrow">DESPENSA / ADICIONAR</p>
                <h1>Escanear alimentos</h1>
                <p class="page-intro">Fotografe suas compras e revise cada item antes de guardar na despensa.</p>
            </div>
            <a class="text-link" href="{{ route('recipes.index') }}">Ver receitas <span aria-hidden="true">↗</span></a>
        </div>

        <div class="scanner-grid">
            <section class="panel capture-panel" aria-labelledby="capture-heading">
                <div class="panel-heading">
                    <div>
                        <span class="step-index">01</span>
                        <h2 id="capture-heading">Sua foto</h2>
                    </div>
                    <span class="privacy-label"><span aria-hidden="true">●</span> Privada por padrão</span>
                </div>

                <div class="capture-stage" data-capture-stage>
                    <div class="capture-empty" data-preview-empty>
                        <span class="camera-illustration" aria-hidden="true">
                            <svg viewBox="0 0 64 52" fill="none"><path d="M8 13.5h10l4-6h20l4 6h10a4 4 0 0 1 4 4v25a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-25a4 4 0 0 1 4-4Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><circle cx="32" cy="29" r="10" stroke="currentColor" stroke-width="2.5"/><circle cx="50" cy="20" r="2" fill="currentColor"/></svg>
                        </span>
                        <strong>Mostre o que chegou</strong>
                        <span>Use a câmera ou escolha uma imagem para começar.</span>
                    </div>
                    <video class="capture-media" data-camera autoplay playsinline muted hidden></video>
                    <img class="capture-media" data-preview alt="Prévia dos alimentos selecionados" hidden>
                    <div class="camera-live-tag" data-live-tag hidden><span></span> Câmera ativa</div>
                </div>

                <div class="capture-controls">
                    <button class="button button-primary" type="button" data-open-camera>
                        <span aria-hidden="true">◉</span> Abrir câmera
                    </button>
                    <button class="button button-capture" type="button" data-take-photo disabled>
                        <span class="shutter-icon" aria-hidden="true"></span> Tirar foto
                    </button>
                    <button class="button button-quiet" type="button" data-switch-camera disabled aria-label="Trocar câmera" title="Trocar câmera">↻</button>
                    <label class="button button-outline upload-button">
                        <span aria-hidden="true">↑</span> Enviar imagem
                        <input data-file-input type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" hidden>
                    </label>
                </div>
                <p class="camera-message" data-camera-message role="status" aria-live="polite">JPG, PNG ou WEBP · até 10 MB</p>
                <div class="privacy-note">A imagem é processada localmente pelo OpenCV, armazenada temporariamente em área privada e removida após a análise.</div>
                <button class="button button-identify" type="button" data-analyze disabled>
                    <span aria-hidden="true">✳</span> Processar imagem com OpenCV
                </button>
            </section>

            <section class="panel results-panel" aria-labelledby="results-heading">
                <div class="panel-heading results-heading">
                    <div>
                        <span class="step-index">02</span>
                        <h2 id="results-heading">Revise os itens</h2>
                    </div>
                    <button class="text-button" type="button" data-add-item>+ Adicionar manualmente</button>
                </div>
                <p class="results-hint">O OpenCV valida a imagem. Até integrar um modelo de reconhecimento, cadastre os alimentos manualmente.</p>
                <div class="processing-state" data-processing hidden><span class="spinner"></span><span>Processando com OpenCV…</span></div>
                <div class="notice notice-error" data-error role="alert" hidden></div>
                <div class="notice notice-success" data-success role="status" hidden></div>
                <div class="empty-results" data-empty-results>
                    <span class="empty-results-mark" aria-hidden="true">＋</span>
                    <span>Após processar a foto, adicione os alimentos manualmente.</span>
                </div>
                <div class="item-editor" data-item-editor></div>
                <button class="button button-save" type="button" data-save-items hidden>Confirmar e salvar na despensa <span aria-hidden="true">→</span></button>
            </section>
        </div>

        <section class="pantry-section" aria-labelledby="pantry-heading">
            <div class="section-title-row">
                <div><p class="eyebrow">SEU ESTOQUE</p><h2 id="pantry-heading">Minha despensa <span class="count-badge">{{ $foods->count() }}</span></h2></div>
                <a class="text-link" href="{{ route('recipes.index') }}">Encontrar receitas <span aria-hidden="true">↗</span></a>
            </div>
            @if ($foods->isEmpty())
                <div class="pantry-empty">Sua despensa ainda está vazia. Escaneie ou adicione seu primeiro alimento.</div>
            @else
                <div class="pantry-table-wrap">
                    <table class="pantry-table">
                        <thead><tr><th>Alimento</th><th>Categoria</th><th>Quantidade</th><th>Validade informada</th></tr></thead>
                        <tbody>
                            @foreach ($foods as $food)
                                <tr>
                                    <td><strong>{{ $food->nome }}</strong>@if ($food->embalagem)<span class="table-subtext">{{ $food->embalagem }}</span>@endif</td>
                                    <td>{{ $food->categoria }}</td>
                                    <td>{{ $food->quantidade }} {{ $food->unidade }}</td>
                                    <td>{{ $food->validade?->format('d/m/Y') ?? 'Não informada' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </section>
@endsection