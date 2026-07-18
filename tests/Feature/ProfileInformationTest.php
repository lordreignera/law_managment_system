<?php

namespace Tests\Feature;

use App\Models\User;
use App\Livewire\Profile\UpdateProfileInformationForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_profile_photo_and_signature_can_be_uploaded(): void
    {
        config(['jetstream.profile_photo_disk' => 'public']);

        Storage::fake('public');

        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('photo', UploadedFile::fake()->image('avatar.png'))
            ->set('signature', UploadedFile::fake()->image('signature.png'))
            ->call('updateProfileInformation')
            ->assertRedirect(route('profile.show'));

        $user->refresh();

        $this->assertNotNull($user->profile_photo_path);
        $this->assertNotNull($user->signature_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->signature_path);
        $this->assertStringContainsString('/profile-media/users/'.$user->id.'/photo', $user->profile_photo_url);
        $this->assertStringContainsString('/profile-media/users/'.$user->id.'/signature', $user->signature_url);
    }

    public function test_livewire_temporary_uploads_do_not_use_default_document_disk(): void
    {
        config(['filesystems.default' => 's3']);

        $this->assertSame('local', config('livewire.temporary_file_upload.disk'));
    }

    public function test_profile_media_urls_use_configured_profile_disk(): void
    {
        config(['jetstream.profile_photo_disk' => 'public']);

        Storage::fake('public');

        $user = User::factory()->create([
            'profile_photo_path' => 'profile-photos/avatar.png',
            'signature_path' => 'signatures/profile-signature.png',
        ]);
        Storage::disk('public')->put('profile-photos/avatar.png', 'avatar');
        Storage::disk('public')->put('signatures/profile-signature.png', 'signature');

        $this->assertStringContainsString('/profile-media/users/'.$user->id.'/photo', $user->profile_photo_url);
        $this->assertStringContainsString('/profile-media/users/'.$user->id.'/signature', $user->signature_url);

        $this->actingAs($user)
            ->get(route('profile-media.photo', $user))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('profile-media.signature', $user))
            ->assertOk();
    }

    public function test_profile_media_routes_only_allow_owner(): void
    {
        config(['jetstream.profile_photo_disk' => 'public']);

        Storage::fake('public');

        $owner = User::factory()->create([
            'profile_photo_path' => 'profile-photos/avatar.png',
            'signature_path' => 'signatures/profile-signature.png',
        ]);
        $otherUser = User::factory()->create();

        Storage::disk('public')->put('profile-photos/avatar.png', 'avatar');
        Storage::disk('public')->put('signatures/profile-signature.png', 'signature');

        $this->actingAs($otherUser)
            ->get(route('profile-media.photo', $owner))
            ->assertForbidden();
    }

    public function test_navbar_uses_saved_profile_photo_when_available(): void
    {
        config(['jetstream.profile_photo_disk' => 'public']);

        Storage::fake('public');

        $user = User::factory()->create([
            'profile_photo_path' => 'profile-photos/avatar.png',
        ]);

        Storage::disk('public')->put('profile-photos/avatar.png', 'avatar');

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('kfms-navbar-avatar', false)
            ->assertSee('/profile-media/users/'.$user->id.'/photo', false)
            ->assertDontSee('kfms-navbar-avatar-fallback', false);
    }
}
