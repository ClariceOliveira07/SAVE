<?php

namespace Tests\Feature;

use App\Models\Alimento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FoodScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_requires_authentication(): void
    {
        $this->get(route('foods.scan'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_render_scanner_and_recipe_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('foods.scan'))
            ->assertOk()
            ->assertSee('Identificar alimentos com IA')
            ->assertSee('Minha despensa');

        $this->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('Receitas para sua despensa')
            ->assertSee('OPENAI_API_KEY');
    }

    public function test_user_can_analyze_image_and_confirm_foods(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode(['items' => [[
                            'name' => 'Banana',
                            'category' => 'Frutas',
                            'estimated_quantity' => 3,
                            'packaging' => null,
                            'confidence' => 0.91,
                            'expiration_date' => null,
                            'barcode' => null,
                            'label_text' => null,
                        ]]], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('foods.analyze'), ['image' => UploadedFile::fake()->image('banana.jpg')])
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Banana');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.com/v1/responses');

        $this->postJson(route('foods.store'), ['items' => [[
            'name' => 'Banana prata',
            'category' => 'Frutas',
            'quantity' => 3,
            'unit' => 'unidades',
            'expiration_date' => null,
            'price' => null,
        ]]])
            ->assertCreated()
            ->assertJsonPath('items.0.nome', 'Banana prata');

        $this->assertDatabaseHas('alimentos', [
            'user_id' => $user->id,
            'nome' => 'Banana prata',
            'quantidade' => 3,
        ]);
        $this->assertSame(1, Alimento::whereBelongsTo($user)->count());
    }

    public function test_analysis_rejects_unsupported_files_and_files_over_ten_megabytes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('foods.analyze'), ['image' => UploadedFile::fake()->create('notes.txt', 2, 'text/plain')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->postJson(route('foods.analyze'), ['image' => UploadedFile::fake()->image('large.png')->size(10241)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_analysis_explains_when_the_api_key_is_missing(): void
    {
        config(['services.openai.key' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('foods.analyze'), ['image' => UploadedFile::fake()->image('food.png')])
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'ai_not_configured');
    }

    public function test_analysis_rejects_an_invalid_ai_response(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{invalid']]]],
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('foods.analyze'), ['image' => UploadedFile::fake()->image('food.png')])
            ->assertStatus(502)
            ->assertJsonPath('message', 'A IA retornou dados inválidos. Tente novamente.');
    }

    public function test_recipe_ai_endpoint_explains_when_the_api_key_is_missing(): void
    {
        config(['services.openai.key' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.generate'))
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'ai_not_configured');
    }

    public function test_recipe_ai_receives_only_foods_confirmed_by_the_authenticated_user(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode(['recipes' => [[
                            'title' => 'Salada de tomate',
                            'summary' => 'Uma salada simples.',
                            'ingredients' => ['Tomate', 'Queijo'],
                            'steps' => ['Corte e misture os ingredientes.'],
                        ]]], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        $user = User::factory()->create();
        $user->alimentos()->create([
            'nome' => 'Tomate',
            'categoria' => 'Legumes',
            'quantidade' => 2,
            'unidade' => 'unidades',
        ]);
        User::factory()->create()->alimentos()->create([
            'nome' => 'Queijo',
            'categoria' => 'Laticínios',
            'quantidade' => 1,
            'unidade' => 'unidade',
        ]);

        $this->actingAs($user)
            ->postJson(route('recipes.generate'))
            ->assertOk()
            ->assertJsonPath('recipes.0.ingredients.0.available', true)
            ->assertJsonPath('recipes.0.ingredients.1.available', false);

        Http::assertSent(fn ($request): bool => str_contains($request->body(), 'Tomate')
            && ! str_contains($request->body(), 'Queijo'));
    }

    public function test_user_cannot_save_foods_with_invalid_quantities(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('foods.store'), ['items' => [[
                'name' => 'Leite',
                'category' => 'Laticínios',
                'quantity' => 0,
                'unit' => 'unidades',
            ]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.quantity');

        $this->assertDatabaseCount('alimentos', 0);
    }
}
