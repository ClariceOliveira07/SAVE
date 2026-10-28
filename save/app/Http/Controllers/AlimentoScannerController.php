<?php

namespace App\Http\Controllers;

use App\Models\Alimento;
use App\Services\OpenCvImageProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class AlimentoScannerController extends Controller
{
    public function index(): View
    {
        $foods = auth()->user()->alimentos()->latest()->get();

        return view('foods.scan', compact('foods'));
    }

    public function analyze(Request $request, OpenCvImageProcessingService $processor): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'image.required' => 'Selecione ou capture uma imagem.',
            'image.image' => 'O arquivo precisa ser uma imagem válida.',
            'image.mimes' => 'Use uma imagem JPG, PNG ou WEBP.',
            'image.max' => 'A imagem deve ter no máximo 10 MB.',
        ]);

        try {
            $result = $processor->process($validated['image']);

            return response()->json([
                'items' => [],
                'image' => $result['image'],
                'processing' => $result['processing'],
                'message' => 'Imagem processada com OpenCV. O reconhecimento automático de alimentos ainda não está disponível; adicione os itens manualmente.',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.category' => ['required', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'items.*.unit' => ['required', 'string', 'max:40'],
            'items.*.packaging' => ['nullable', 'string', 'max:120'],
            'items.*.expiration_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.barcode' => ['nullable', 'string', 'max:64'],
        ], [
            'items.required' => 'Adicione ao menos um alimento antes de salvar.',
            'items.*.quantity.gt' => 'A quantidade precisa ser maior que zero.',
        ]);

        $foods = DB::transaction(function () use ($validated): array {
            return collect($validated['items'])
                ->map(fn (array $item): Alimento => auth()->user()->alimentos()->create([
                    'nome' => trim($item['name']),
                    'categoria' => trim($item['category']),
                    'quantidade' => $item['quantity'],
                    'unidade' => trim($item['unit']),
                    'embalagem' => $item['packaging'] ?? null,
                    'validade' => $item['expiration_date'] ?? null,
                    'preco' => $item['price'] ?? null,
                    'codigo_barras' => $item['barcode'] ?? null,
                ]))
                ->all();
        });

        return response()->json([
            'message' => count($foods).' alimento(s) adicionado(s) à despensa.',
            'items' => $foods,
        ], 201);
    }
}
