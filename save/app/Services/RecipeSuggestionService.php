<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class RecipeSuggestionService
{
    /**
     * @return array<int, array{title: string, summary: string, ingredients: array<int, array{name: string, available: bool, expiring: bool}>, steps: array<int, string>}>
     */
    public function suggest(Collection $foods): array
    {
        $inventory = $this->inventoryNames($foods);
        $expiring = $this->expiringNames($foods);
        $today = now()->startOfDay();
        $recipes = [
            [
                'title' => 'Macarrão de tomate e alho',
                'summary' => 'Um molho rápido com ingredientes simples da despensa.',
                'ingredients' => ['macarrão', 'tomate', 'alho', 'azeite', 'cebola'],
                'steps' => ['Cozinhe o macarrão conforme a embalagem.', 'Refogue alho e cebola no azeite, junte o tomate e cozinhe até formar um molho.', 'Misture o molho ao macarrão e ajuste os temperos.'],
            ],
            [
                'title' => 'Omelete de tomate',
                'summary' => 'Uma refeição simples que aceita os legumes disponíveis.',
                'ingredients' => ['ovo', 'tomate', 'cebola', 'queijo'],
                'steps' => ['Bata os ovos e misture o tomate e a cebola picados.', 'Cozinhe em frigideira untada; acrescente queijo se desejar.', 'Dobre a omelete quando estiver firme.'],
            ],
            [
                'title' => 'Banana com aveia e canela',
                'summary' => 'Uma opção rápida para aproveitar frutas confirmadas no estoque.',
                'ingredients' => ['banana', 'aveia', 'canela', 'mel'],
                'steps' => ['Fatie a banana e aqueça por alguns minutos em uma frigideira.', 'Finalize com aveia e canela. Use mel se tiver.'],
            ],
            [
                'title' => 'Salada de grão-de-bico',
                'summary' => 'Uma salada fresca com itens fáceis de combinar.',
                'ingredients' => ['grão-de-bico', 'tomate', 'cebola', 'limão', 'azeite'],
                'steps' => ['Cozinhe o grão-de-bico se ainda não estiver pronto.', 'Misture com tomate e cebola picados.', 'Tempere com limão e azeite.'],
            ],
        ];

        return collect($recipes)
            ->map(function (array $recipe) use ($inventory, $expiring, $today): array {
                $ingredients = collect($recipe['ingredients'])
                    ->map(fn (string $name): array => [
                        'name' => $name,
                        'available' => $this->isAvailable($name, $inventory),
                        'expiring' => $this->isAvailable($name, $expiring),
                    ]);

                return [
                    'title' => $recipe['title'],
                    'summary' => $recipe['summary'],
                    'ingredients' => $ingredients->all(),
                    'steps' => $recipe['steps'],
                    'available_count' => $ingredients->where('available', true)->count(),
                    'expiring_count' => $ingredients->where('expiring', true)->count(),
                ];
            })
            ->sortByDesc(fn (array $recipe): array => [$recipe['expiring_count'], $recipe['available_count']])
            ->values()
            ->map(fn (array $recipe): array => collect($recipe)->except(['available_count', 'expiring_count'])->all())
            ->all();
    }

    /**
     * @return array<int, array{title: string, summary: string, ingredients: array<int, array{name: string, available: bool, expiring: bool}>, steps: array<int, string>}>
     */
    public function generateWithAi(Collection $foods): array
    {
        $confirmedFoods = $foods->map(fn ($food): array => [
            'name' => $food->nome,
            'category' => $food->categoria,
            'expiration_date' => $food->validade?->toDateString(),
        ])->values()->all();

        if ($confirmedFoods === []) {
            return [];
        }

        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(45)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'input' => [[
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => 'Crie até 4 receitas em português a partir da lista de alimentos CONFIRMADOS pelo usuário. Use a lista somente para decidir o que ele já possui. Não diga que outros ingredientes estão disponíveis; ingredientes fora da lista serão exibidos como faltantes. Priorize itens com validade cadastrada mais próxima, sem afirmar que estão próprios para consumo. Não invente validade. Dados confirmados: '.json_encode($confirmedFoods, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        ]],
                    ]],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'recipe_suggestions',
                            'strict' => true,
                            'schema' => $this->recipeSchema(),
                        ],
                    ],
                    'max_output_tokens' => 1800,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Não foi possível conectar ao serviço de IA. Tente novamente.', previous: $exception);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RuntimeException('A chave da API de IA foi recusada. Confira OPENAI_API_KEY no arquivo .env.');
        }

        if ($response->status() === 429) {
            throw new RuntimeException('O serviço de IA atingiu o limite de requisições. Tente novamente mais tarde.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('O serviço de IA não conseguiu gerar receitas. Tente novamente.');
        }

        $text = collect($response->json('output', []))
            ->where('type', 'message')
            ->flatMap(fn (array $message): array => $message['content'] ?? [])
            ->first(fn (array $content): bool => ($content['type'] ?? null) === 'output_text')['text'] ?? null;
        $result = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($result) || ! is_array($result['recipes'] ?? null)) {
            throw new RuntimeException('A IA retornou dados inválidos. Tente novamente.');
        }

        $inventory = $this->inventoryNames($foods);
        $expiring = $this->expiringNames($foods);

        return collect($result['recipes'])
            ->filter(fn (mixed $recipe): bool => is_array($recipe) && filled($recipe['title'] ?? null) && is_array($recipe['ingredients'] ?? null))
            ->take(4)
            ->map(function (array $recipe) use ($inventory, $expiring): array {
                $ingredients = collect($recipe['ingredients'])
                    ->filter(fn (mixed $name): bool => is_string($name) && filled($name))
                    ->take(16)
                    ->map(fn (string $name): array => [
                        'name' => Str::limit(trim($name), 80, ''),
                        'available' => $this->isAvailable($name, $inventory),
                        'expiring' => $this->isAvailable($name, $expiring),
                    ])
                    ->values();

                return [
                    'title' => Str::limit(trim((string) $recipe['title']), 100, ''),
                    'summary' => Str::limit(trim((string) ($recipe['summary'] ?? '')), 240, ''),
                    'ingredients' => $ingredients->all(),
                    'steps' => collect($recipe['steps'] ?? [])
                        ->filter(fn (mixed $step): bool => is_string($step) && filled($step))
                        ->take(8)
                        ->map(fn (string $step): string => Str::limit(trim($step), 300, ''))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function inventoryNames(Collection $foods): array
    {
        return $foods->pluck('nome')->all();
    }

    /** @return array<int, string> */
    private function expiringNames(Collection $foods): array
    {
        return $foods
            ->filter(fn ($food): bool => $food->validade !== null
                && $food->validade->greaterThanOrEqualTo(today())
                && $food->validade->lessThanOrEqualTo(today()->addDays(7)))
            ->pluck('nome')
            ->all();
    }

    /** @param array<int, string> $inventory */
    private function isAvailable(string $ingredient, array $inventory): bool
    {
        $normalizedIngredient = Str::ascii(Str::lower($ingredient));

        foreach ($inventory as $food) {
            $normalizedFood = Str::ascii(Str::lower($food));

            if (str_contains($normalizedFood, $normalizedIngredient) || str_contains($normalizedIngredient, $normalizedFood)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function recipeSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'recipes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'summary' => ['type' => 'string'],
                            'ingredients' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'steps' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['title', 'summary', 'ingredients', 'steps'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['recipes'],
            'additionalProperties' => false,
        ];
    }
}
