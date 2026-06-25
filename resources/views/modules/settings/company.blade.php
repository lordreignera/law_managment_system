@extends('layouts.admin')

@section('title', 'Company Settings')
@section('page-title', 'Company Settings')

@section('content')
    <section class="kfms-panel kfms-settings-panel">
        <div class="kfms-panel-header">
            <h2>Branding</h2>
            <span>Login, registration, sidebar, and header</span>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-form" method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="kfms-form-grid">
                <div class="kfms-logo-field kfms-span-2">
                    <div>
                        <span>Company Logo</span>
                        <p>Shown on the login page, registration page, sidebar, and mobile header.</p>
                    </div>
                    <div class="kfms-logo-upload">
                        <div class="kfms-logo-preview">
                            <x-company-logo mark-class="kfms-settings-logo-mark" image-class="kfms-settings-logo-image" />
                        </div>
                        <label>
                            <span>Upload Logo</span>
                            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml">
                            @error('logo') <small>{{ $message }}</small> @enderror
                        </label>
                        @if ($setting->logo_url)
                            <label class="kfms-check-row">
                                <input type="checkbox" name="remove_logo" value="1">
                                <span>Remove current logo</span>
                            </label>
                        @endif
                    </div>
                </div>

                <label>
                    <span>Company Name</span>
                    <input type="text" name="company_name" value="{{ old('company_name', $setting->company_name) }}" required>
                    @error('company_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Short Name</span>
                    <input type="text" name="short_name" value="{{ old('short_name', $setting->short_name) }}" required>
                    @error('short_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Initials</span>
                    <input type="text" name="initials" value="{{ old('initials', $setting->initials) }}" maxlength="8" required>
                    @error('initials') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Tagline</span>
                    <input type="text" name="tagline" value="{{ old('tagline', $setting->tagline) }}">
                    @error('tagline') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Login Heading</span>
                    <input type="text" name="login_heading" value="{{ old('login_heading', $setting->login_heading) }}">
                    @error('login_heading') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Login Supporting Text</span>
                    <textarea name="login_subheading" rows="3">{{ old('login_subheading', $setting->login_subheading) }}</textarea>
                    @error('login_subheading') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Primary Color</span>
                    <input type="color" name="primary_color" value="{{ old('primary_color', $setting->primary_color) }}" required>
                    @error('primary_color') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Secondary Color</span>
                    <input type="color" name="secondary_color" value="{{ old('secondary_color', $setting->secondary_color) }}" required>
                    @error('secondary_color') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Contact Email</span>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $setting->contact_email) }}">
                    @error('contact_email') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Contact Phone</span>
                    <input type="text" name="contact_phone" value="{{ old('contact_phone', $setting->contact_phone) }}">
                    @error('contact_phone') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <button type="submit">Save Settings</button>
            </div>
        </form>
    </section>
@endsection
