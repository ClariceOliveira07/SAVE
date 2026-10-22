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
