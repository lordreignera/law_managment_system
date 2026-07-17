<x-guest-layout>
    <main class="kca-auth-stage kca-client-welcome-stage">
        <section class="kca-client-welcome">
            <div class="kca-client-welcome-brand">
                <x-company-logo mark-class="kca-client-welcome-mark" image-class="kca-client-welcome-logo" />
                <div>
                    <span>Client Portal</span>
                    <strong>{{ $companySetting->company_name }}</strong>
                </div>
            </div>

            <section class="kca-client-slider" aria-label="Client portal overview">
                <input id="client-slide-1" type="radio" name="client_slide" checked>
                <input id="client-slide-2" type="radio" name="client_slide">
                <input id="client-slide-3" type="radio" name="client_slide">

                <div class="kca-client-slides">
                    <article class="kca-client-slide">
                        <div class="kca-client-slide-icon" data-fallback="1">
                            <i class="mdi mdi-briefcase"></i>
                        </div>
                        <p>Welcome</p>
                        <h1>Access your matters securely.</h1>
                        <span>View the files, updates, documents, and messages connected to your work with the firm.</span>
                    </article>

                    <article class="kca-client-slide">
                        <div class="kca-client-slide-icon" data-fallback="2">
                            <i class="mdi mdi-file-document"></i>
                        </div>
                        <p>Your Workspace</p>
                        <h1>Follow each matter with clarity.</h1>
                        <span>See only your own matters and documents shared by the advocate handling your file.</span>
                    </article>

                    <article class="kca-client-slide">
                        <div class="kca-client-slide-icon" data-fallback="3">
                            <i class="mdi mdi-message-text"></i>
                        </div>
                        <p>Communication</p>
                        <h1>Keep the conversation in one place.</h1>
                        <span>Send updates to your assigned advocate and receive matter communication from the firm.</span>
                    </article>
                </div>

                <div class="kca-client-slide-nav" aria-label="Choose slide">
                    <label for="client-slide-1"><span>1</span></label>
                    <label for="client-slide-2"><span>2</span></label>
                    <label for="client-slide-3"><span>3</span></label>
                </div>
            </section>

            <aside class="kca-client-welcome-actions">
                <div>
                    <span>Private Client Access</span>
                    <h2>Continue to your portal</h2>
                    <p>Use your existing portal account, or create one with the email already registered in the firm's client records.</p>
                </div>

                <a class="kca-primary-button" href="{{ route('login', ['portal' => 'client']) }}">
                    <i class="mdi mdi-login"></i>
                    Sign In
                </a>

                <a class="kca-outline-button" href="{{ route('client.register') }}">
                    <i class="mdi mdi-account-plus-outline"></i>
                    <strong>Create Portal Account</strong>
                </a>

                <div class="kca-client-welcome-note">
                    <i class="mdi mdi-shield-check"></i>
                    <span>
                        Portal access is available only to clients already registered with the firm.
                        <a href="{{ route('help.client-guidelines') }}">User Guidelines</a>
                    </span>
                </div>
            </aside>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const slider = document.querySelector('.kca-client-slider');

            if (! slider || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            const slides = Array.from(slider.querySelectorAll('input[name="client_slide"]'));

            if (slides.length < 2) {
                return;
            }

            let index = slides.findIndex((slide) => slide.checked);
            let timer;

            const rotate = function () {
                index = (index + 1) % slides.length;
                slides[index].checked = true;
            };

            const restart = function () {
                window.clearInterval(timer);
                timer = window.setInterval(rotate, 5200);
            };

            slides.forEach(function (slide, slideIndex) {
                slide.addEventListener('change', function () {
                    if (slide.checked) {
                        index = slideIndex;
                        restart();
                    }
                });
            });

            restart();
        });
    </script>
</x-guest-layout>
