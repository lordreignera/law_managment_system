<x-guest-layout>
    @php
        $registerHeading = $companySetting->login_heading ?: 'Request staff access';
        $registerCopy = $companySetting->login_subheading ?: 'Your request will be reviewed before access is granted.';
    @endphp

    <main class="kca-auth-stage kca-login-stage kca-register-stage">
        <section class="kca-auth-card kca-register-card kca-register-refined">
            <div class="kca-login-card-accent" aria-hidden="true"></div>
            <aside class="kca-brand-panel kca-register-brand">
                <div class="kca-product-lockup">
                    <x-company-logo mark-class="kca-product-mark" image-class="kca-product-logo" />
                </div>

                <div class="kca-brand-copy">
                    <h1>{{ $companySetting->company_name }}</h1>
                    <h2>{{ $companySetting->tagline ?: $companySetting->short_name }}</h2>
                    <p>{{ $registerCopy }}</p>
                </div>

                <div class="kca-feature-list">
                    <div>
                        <i class="mdi mdi-shield-check-outline"></i>
                        <span>
                            <strong>Reviewed Access</strong>
                            <small>Every account is checked by an administrator before activation.</small>
                        </span>
                    </div>
                    <div>
                        <i class="mdi mdi-shield-account-outline"></i>
                        <span>
                            <strong>Role Based Permissions</strong>
                            <small>Your department, branch, and role determine what you can access.</small>
                        </span>
                    </div>
                    <div>
                        <i class="mdi mdi-scale-balance"></i>
                        <span>
                            <strong>Client Confidentiality</strong>
                            <small>Matter, client, and finance records stay inside the approved workspace.</small>
                        </span>
                    </div>
                </div>

                <div class="kca-help-box">
                    <i class="mdi mdi-headset"></i>
                    <span>
                        <strong>Need Help?</strong>
                        <small>
                            @if ($companySetting->contact_email)
                                {{ $companySetting->contact_email }}
                            @elseif ($companySetting->contact_phone)
                                {{ $companySetting->contact_phone }}
                            @else
                                Contact your firm administrator for access support.
                            @endif
                        </small>
                    </span>
                </div>
            </aside>

            <section class="kca-register-panel">
                <div class="kca-register-top-link">
                    Already have an account?
                    <a href="{{ route('login') }}">Sign in</a>
                </div>

                <div class="kca-register-heading">
                    <span class="kca-register-heading-logo">
                        <x-company-logo mark-class="kca-register-logo-mark" image-class="kca-register-logo-image" />
                    </span>
                    <div>
                        <h2>Request Staff Access</h2>
                        <p>{{ $registerHeading }}</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="kca-alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <div class="kca-register-section">Personal Information</div>
                    <div class="kca-register-grid">
                        <label class="kca-field" for="name">
                            <span>Full Name <b>*</b></span>
                            <div>
                                <i class="mdi mdi-account-outline"></i>
                                <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Enter your full name" required autofocus autocomplete="name">
                            </div>
                        </label>

                        <label class="kca-field" for="email">
                            <span>Email Address <b>*</b></span>
                            <div>
                                <i class="mdi mdi-email-outline"></i>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email" required autocomplete="username">
                            </div>
                        </label>

                        <label class="kca-field" for="phone">
                            <span>Phone Number <b>*</b></span>
                            <div>
                                <i class="mdi mdi-phone-outline"></i>
                                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" placeholder="e.g. +256 700 123456" required>
                            </div>
                        </label>

                        <label class="kca-field" for="job_title">
                            <span>Position / Job Title <b>*</b></span>
                            <div>
                                <i class="mdi mdi-briefcase-outline"></i>
                                <input id="job_title" type="text" name="job_title" value="{{ old('job_title') }}" placeholder="e.g. Associate, Legal Officer, Secretary" required>
                            </div>
                        </label>

                        <label class="kca-field" for="department_id">
                            <span>Department <b>*</b></span>
                            <div>
                                <i class="mdi mdi-domain"></i>
                                <select id="department_id" name="department_id" required>
                                    <option value="">Select department</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </label>

                        <label class="kca-field" for="branch_id">
                            <span>Branch / Office <b>*</b></span>
                            <div>
                                <i class="mdi mdi-map-marker-outline"></i>
                                <select id="branch_id" name="branch_id" required>
                                    <option value="">Select branch or office</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </label>

                        <label class="kca-field" for="requested_role">
                            <span>Requested Role <b>*</b></span>
                            <div>
                                <i class="mdi mdi-shield-account-outline"></i>
                                <select id="requested_role" name="requested_role" required>
                                    <option value="">Select role</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->name }}" @selected(old('requested_role') === $role->name)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </label>

                        <label class="kca-field" for="password">
                            <span>Password <b>*</b></span>
                            <div>
                                <i class="mdi mdi-lock-outline"></i>
                                <input id="password" type="password" name="password" placeholder="Create your password" required autocomplete="new-password">
                                <button class="kca-password-toggle" type="button" data-password-toggle="password" aria-label="Show password">
                                    <i class="mdi mdi-eye-outline"></i>
                                </button>
                            </div>
                        </label>

                        <label class="kca-field" for="password_confirmation">
                            <span>Confirm Password <b>*</b></span>
                            <div>
                                <i class="mdi mdi-lock-check-outline"></i>
                                <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Confirm your password" required autocomplete="new-password">
                                <button class="kca-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password confirmation">
                                    <i class="mdi mdi-eye-outline"></i>
                                </button>
                            </div>
                        </label>
                    </div>

                    @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                        <label class="kca-terms" for="terms">
                            <input type="checkbox" name="terms" id="terms" required>
                            <span>
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                    'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'">'.__('Terms of Service').'</a>',
                                    'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </span>
                        </label>
                    @endif

                    <div class="kca-register-note">
                        <i class="mdi mdi-information-outline"></i>
                        <span>Your request will be reviewed by the firm administrator. You will be notified by email once access is approved.</span>
                    </div>

                    <button class="kca-primary-button" type="submit">
                        <i class="mdi mdi-send-outline"></i>
                        Submit Request
                    </button>
                </form>
            </section>
        </section>
    </main>
</x-guest-layout>
