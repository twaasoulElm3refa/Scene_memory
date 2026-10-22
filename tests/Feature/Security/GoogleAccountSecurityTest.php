<?php

namespace Tests\Feature\Security;

use App\Exceptions\GoogleAuthException;
use App\Http\Controllers\api\auth\AuthController;
use App\Http\Requests\loginRequest;
use App\Models\LicenceType;
use App\Models\User;
use App\Services\GoogleAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class GoogleAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function identity(string $email): array
    {
        Mail::fake();
        LicenceType::firstOrCreate(['id' => 1], ['name' => 'basic', 'price' => 0]);

        return ['sub' => 'security-fixture-sub', 'email' => $email, 'name' => 'Local OAuth Fixture', 'avatar' => null];
    }

    public function test_new_google_accounts_never_receive_the_shared_placeholder_password(): void
    {
        app(GoogleAccountService::class)->complete($this->identity('new-oauth@example.test'), ['country' => 'Egypt', 'date_of_birth' => '2000-01-01']);
        $user = User::where('email', 'new-oauth@example.test')->sole();
        $this->assertNull($user->password);
    }

    public function test_google_sign_in_cannot_reactivate_a_suspended_account(): void
    {
        $user = User::factory()->create(['email' => 'suspended-oauth@example.test', 'google_id' => 'security-fixture-sub', 'is_active' => false]);
        try {
            app(GoogleAccountService::class)->complete($this->identity($user->email), []);
            $this->fail('Suspended accounts must not authenticate.');
        } catch (GoogleAuthException $e) {
            $this->assertSame('disabled', $e->errorCode);
        }
        $this->assertFalse($user->fresh()->is_active);
        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_legacy_placeholder_credentials_are_rejected_without_modifying_the_account(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password'), 'email_verified_at' => now()]);
        // Isolate credential logic from DNS validation; no external DNS or OAuth call.
        $request = Mockery::mock(loginRequest::class)->makePartial();
        $request->shouldReceive('validated')->andReturn([]);
        $request->initialize();
        $request->merge(['email' => $user->email, 'password' => 'password']);
        $response = app(AuthController::class)->login($request);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertCount(0, $user->tokens()->get());
    }
}
