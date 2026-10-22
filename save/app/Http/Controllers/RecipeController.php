<?php

namespace App\Http\Controllers;

use App\Services\RecipeSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class RecipeController extends Controller
{
    public function index(RecipeSuggestionService $suggestions): View
    {
        $foods = auth()->user()->alimentos()->orderByRaw('validade IS NULL')->orderBy('validade')->get();

        return view('recipes.index', [
            'foods' => $foods,
            'recipes' => $suggestions->suggest($foods),
            'aiConfigured' => filled(config('services.openai.key')),
        ]);
    }

    public function generate(Request $request, RecipeSuggestionService $suggestions): JsonResponse
    {
        $foods = $request->user()->alimentos()->orderBy('validade')->get();

        if (! filled(config('services.openai.key'))) {
            return response()->json([
                'message' => 'Sugestões por IA não configuradas. Adicione OPENAI_API_KEY ao .env. As receitas compatíveis da SAVE continuam disponíveis.',
                'code' => 'ai_not_configured',
            ], 503);
        }

        try {
            return response()->json(['recipes' => $suggestions->generateWithAi($foods)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }
}
