<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FoodImageRecognitionService
{
    /**
     * @return array<int, array{name: string, category: string, estimated_quantity: ?float, packaging: ?string, confidence: ?float, expiration_date: ?string, barcode: ?string, label_text: ?string}>
     */
    public function analyze(UploadedFile $image): array
    {
        $mimeType = $image->getMimeType() ?: 'image/jpeg';
        $imageData = base64_encode(file_get_contents($image->getRealPath()));

        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(60)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'input' => [[
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => 'Identifique somente alimentos visíveis. Estime unidades apenas quando possível; nunca invente preço, quantidade exata ou validade. Leia validade apenas se o rótulo mostrar claramente uma data de validade; ignore fabricação. Extraia código de barras e texto visível somente quando legíveis. Retorne JSON conforme o schema.',
                            ],
                            [
                                'type' => 'input_image',
                                'image_url' => "data:{$mimeType};base64,{$imageData}",
                                'detail' => 'high',
                            ],
                        ],
                    ]],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'food_identification',
                            'strict' => true,
                            'schema' => $this->schema(),
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
            throw new RuntimeException('O serviço de IA não conseguiu analisar a imagem. Tente novamente.');
        }

        $text = collect($response->json('output', []))
            ->where('type', 'message')
            ->flatMap(fn (array $message): array => $message['content'] ?? [])
            ->first(fn (array $content): bool => ($content['type'] ?? null) === 'output_text')['text'] ?? null;

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('A IA retornou uma resposta sem conteúdo estruturado. Tente outra imagem.');
        }

        $result = json_decode($text, true);

        if (! is_array($result) || ! is_array($result['items'] ?? null)) {
            throw new RuntimeException('A IA retornou dados inválidos. Tente novamente.');
        }

        return collect($result['items'])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['name'] ?? null))
            ->map(fn (array $item): array => [
                'name' => Str::limit(trim((string) $item['name']), 120, ''),
                'category' => Str::limit(trim((string) ($item['category'] ?? 'Outros')), 80, ''),
                'estimated_quantity' => is_numeric($item['estimated_quantity'] ?? null)
                    ? max(0.01, min(99999, (float) $item['estimated_quantity']))
                    : null,
                'packaging' => filled($item['packaging'] ?? null) ? Str::limit((string) $item['packaging'], 120, '') : null,
                'confidence' => is_numeric($item['confidence'] ?? null)
                    ? max(0, min(1, (float) $item['confidence']))
                    : null,
                'expiration_date' => $this->validDate($item['expiration_date'] ?? null),
                'barcode' => filled($item['barcode'] ?? null) ? Str::limit((string) $item['barcode'], 64, '') : null,
                'label_text' => filled($item['label_text'] ?? null) ? Str::limit((string) $item['label_text'], 500, '') : null,
            ])
            ->take(30)
            ->values()
            ->all();
    }

    private function validDate(mixed $date): ?string
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year) ? $date : null;
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'category' => ['type' => 'string'],
                            'estimated_quantity' => ['type' => ['number', 'null']],
                            'packaging' => $nullableString,
                            'confidence' => ['type' => ['number', 'null']],
                            'expiration_date' => $nullableString,
                            'barcode' => $nullableString,
                            'label_text' => $nullableString,
                        ],
                        'required' => ['name', 'category', 'estimated_quantity', 'packaging', 'confidence', 'expiration_date', 'barcode', 'label_text'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['items'],
            'additionalProperties' => false,
        ];
    }
}
