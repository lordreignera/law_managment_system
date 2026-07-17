<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_profile_information_is_available(): void
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test(UpdateProfileInformationForm::class);

        $this->assertEquals($user->name, $component->state['name']);
        $this->assertEquals($user->email, $component->state['email']);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => 'Test Name', 'email' => 'test@example.com'])
            ->call('updateProfileInformation');

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_profile_media_urls_use_configured_profile_disk(): void
    {
        config(['jetstream.profile_photo_disk' => 'public']);

        Storage::fake('public');

        $user = User::factory()->create([
            'profile_photo_path' => 'profile-photos/avatar.png',
            'signature_path' => 'signatures/profile-signature.png',
        ]);

        $this->assertStringContainsString('/storage/profile-photos/avatar.png', $user->profile_photo_url);
        $this->assertStringContainsString('/storage/signatures/profile-signature.png', $user->signature_url);
    }
}
