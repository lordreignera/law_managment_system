<x-guest-layout>
    <main class="jf-auth-stage">
        <section class="jf-auth-card jf-register-card">
            <aside class="jf-brand-panel jf-register-brand">
                <div class="jf-product-lockup">
                    <x-company-logo mark-class="jf-product-mark" image-class="jf-product-logo" />
                </div>

                <div class="jf-brand-copy">
                    <h1>{{ $companySetting->company_name }}</h1>
                    <h2>{{ $companySetting->tagline }}</h2>
                    <p>{{ $companySetting->login_subheading }}</p>
                </div>

                <div class="jf-feature-list">
                    <div>
                        <i class="mdi mdi-shield-check-outline"></i>
                        <span>
                            <strong>Secure & Confidential</strong>
                            <small>Bank-level security to keep client data protected.</small>
                        </span>
                    </div>
                    <div>
                        <i class="mdi mdi-account-group-outline"></i>
                        <span>
                            <strong>Streamlined Workflows</strong>
                            <small>Automate tasks and manage practice with ease.</small>
                        </span>
                    </div>
                    <div>
                        <i class="mdi mdi-chart-line"></i>
                        <span>
                            <strong>Insightful Reports</strong>
                            <small>Make informed decisions with powerful analytics.</small>
                        </span>
                    </div>
                </div>

                <div class="jf-help-box">
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

            <section class="jf-register-panel">
                <div class="jf-register-top-link">
                    Already have an account?
                    <a href="{{ route('login') }}">Sign in</a>
                </div>

                <div class="jf-register-heading">
                    <span><i class="mdi mdi-account-plus-outline"></i></span>
                    <div>
                        <h2>Request Staff Access</h2>
                        <p>Fill in the form below to request access to {{ $companySetting->company_name }}.</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="jf-alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <div class="jf-register-section">Personal Information</div>
                    <div class="jf-register-grid">
                        <label class="jf-field" for="name">
                            <span>Full Name <b>*</b></span>
                            <div>
                                <i class="mdi mdi-account-outline"></i>
                                <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Enter your full name" required autofocus autocomplete="name">
                            </div>
                        </label>

                        <label class="jf-field" for="email">
                            <span>Email Address <b>*</b></span>
                            <div>
                                <i class="mdi mdi-email-outline"></i>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email" required autocomplete="username">
                            </div>
                        </label>

                        <label class="jf-field" for="phone">
                            <span>Phone Number <b>*</b></span>
                            <div>
                                <i class="mdi mdi-phone-outline"></i>
                                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" placeholder="e.g. +256 700 123456" required>
                            </div>
                        </label>

                        <label class="jf-field" for="job_title">
                            <span>Position / Job Title <b>*</b></span>
                            <div>
                                <i class="mdi mdi-briefcase-outline"></i>
                                <input id="job_title" type="text" name="job_title" value="{{ old('job_title') }}" placeholder="e.g. Associate, Legal Officer, Secretary" required>
                            </div>
                        </label>

                        <label class="jf-field" for="department_id">
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

                        <label class="jf-field" for="branch_id">
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

                        <label class="jf-field" for="requested_role">
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

                        <label class="jf-field" for="password">
                            <span>Password <b>*</b></span>
                            <div>
                                <i class="mdi mdi-lock-outline"></i>
                                <input id="password" type="password" name="password" placeholder="Create your password" required autocomplete="new-password">
                            </div>
                        </label>

                        <label class="jf-field" for="password_confirmation">
                            <span>Confirm Password <b>*</b></span>
                            <div>
                                <i class="mdi mdi-lock-check-outline"></i>
                                <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Confirm your password" required autocomplete="new-password">
                            </div>
                        </label>
                    </div>

                    @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                        <label class="jf-terms" for="terms">
                            <input type="checkbox" name="terms" id="terms" required>
                            <span>
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                    'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'">'.__('Terms of Service').'</a>',
                                    'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </span>
                        </label>
                    @endif

                    <div class="jf-register-note">
                        <i class="mdi mdi-information-outline"></i>
                        <span>Your request will be reviewed by the firm administrator. You will be notified by email once access is approved.</span>
                    </div>

                    <button class="jf-primary-button" type="submit">
                        <i class="mdi mdi-send-outline"></i>
                        Submit Request
                    </button>
                </form>
            </section>
        </section>
    </main>
</x-guest-layout>
