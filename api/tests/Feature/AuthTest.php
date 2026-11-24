<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_logout_and_me(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Sara',
            'email' => 'sara@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_admin' => true, // Must be ignored (mass assignment protection)
        ])->assertCreated()
            ->assertJsonStructure(['user', 'token'])
            ->assertJsonPath('user.is_admin', false);

        $login = $this->postJson('/api/login', [
            'email' => 'sara@example.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['user', 'token']);

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', 'sara@example.com');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout')
            ->assertOk();

        // Register issued a separate token, while logout revoked only the current login token.
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_api_request_without_accept_header_returns_401_json(): void
    {
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'wrongpass'])
            ->assertUnauthorized();
    }

    public function test_non_admin_cannot_create_category(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->postJson('/api/categories', ['name' => 'Fiction'])
            ->assertForbidden();
    }

    public function test_admin_can_create_category_and_duplicate_slug_returns_422(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->postJson('/api/categories', ['name' => 'Sci-Fi'])
            ->assertCreated()
            ->assertJsonPath('slug', 'sci-fi');

        $this->actingAs($admin)->postJson('/api/categories', ['name' => 'Sci Fi'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_author_crud_requires_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $author = Author::factory()->create();

        $this->actingAs($user)->postJson('/api/authors', ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($user)->putJson('/api/authors/' . $author->id, ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($user)->deleteJson('/api/authors/' . $author->id)->assertForbidden();

        $created = $this->actingAs($admin)->postJson('/api/authors', ['name' => 'Martin Fowler'])
            ->assertCreated()->json();

        $this->actingAs($admin)->putJson('/api/authors/' . $created['id'], ['bio' => 'Author'])
            ->assertOk()->assertJsonPath('bio', 'Author');

        $this->actingAs($admin)->deleteJson('/api/authors/' . $created['id'])->assertNoContent();
    }

    public function test_admin_stats_requires_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)->getJson('/api/admin/stats')->assertForbidden();

        $this->actingAs($admin)->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonStructure(['books', 'orders', 'borrows', 'users', 'pending_orders', 'active_borrows']);
    }
}
