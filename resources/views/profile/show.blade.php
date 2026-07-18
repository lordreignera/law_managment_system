@extends('layouts.admin')

@section('title', 'Profile')
@section('page-title', 'Profile')

@section('content')
    @php
        $profileUser = auth()->user()->fresh();
    @endphp

    <section class="kfms-panel kfms-profile-shell">
        <div class="kfms-panel-header">
            <div>
                <h2>Account Profile</h2>
                <span>Manage your personal information, password, sessions, and account security.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('dashboard') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <div class="kfms-profile-summary">
            <img class="kfms-profile-avatar" src="{{ $profileUser->profile_photo_url }}" alt="{{ $profileUser->name }}">
            <div>
                <strong>{{ $profileUser->name }}</strong>
                <span>{{ $profileUser->email }}</span>
                @if ($profileUser->signature_url)
                    <em>
                        <img src="{{ $profileUser->signature_url }}" alt="Signature">
                        Signature on file
                    </em>
                @else
                    <em>No signature uploaded</em>
                @endif
            </div>
        </div>

        <div class="kfms-table-toolbar kfms-profile-filter" role="group" aria-label="Profile filters">
            <button class="kfms-link-btn is-active" type="button" data-profile-filter="all">All</button>
            <button class="kfms-link-btn" type="button" data-profile-filter="profile">Profile</button>
            <button class="kfms-link-btn" type="button" data-profile-filter="security">Security</button>
            <button class="kfms-link-btn" type="button" data-profile-filter="sessions">Sessions</button>
            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <button class="kfms-link-btn" type="button" data-profile-filter="danger">Danger Zone</button>
            @endif
        </div>

        <div class="kfms-profile-sections">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                <div class="kfms-profile-section" data-profile-section="profile">
                    @livewire('profile.update-profile-information-form')
                </div>
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div class="kfms-profile-section" data-profile-section="security">
                    @livewire('profile.update-password-form')
                </div>
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <div class="kfms-profile-section" data-profile-section="security">
                    @livewire('profile.two-factor-authentication-form')
                </div>
            @endif

            <div class="kfms-profile-section" data-profile-section="sessions">
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <div class="kfms-profile-section" data-profile-section="danger">
                    @livewire('profile.delete-user-form')
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('[data-profile-filter]');
            const sections = document.querySelectorAll('[data-profile-section]');

            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const filter = button.dataset.profileFilter;

                    buttons.forEach(function (item) {
                        item.classList.toggle('is-active', item === button);
                    });

                    sections.forEach(function (section) {
                        section.hidden = filter !== 'all' && section.dataset.profileSection !== filter;
                    });
                });
            });
        });
    </script>
@endpush
