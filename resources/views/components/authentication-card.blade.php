<main class="kfms-auth-stage">
    <section class="kfms-auth-showcase kfms-auth-simple">
        <div class="kfms-auth-blue">
            {{ $logo }}

            <div class="kfms-auth-welcome">
                <h1>{{ $companySetting->login_heading }}</h1>
                <h3>{{ $companySetting->company_name }}</h3>
                <p>{{ $companySetting->login_subheading }}</p>
            </div>

            <span class="kfms-orb kfms-orb-large"></span>
            <span class="kfms-orb kfms-orb-small"></span>
        </div>

        <div class="kfms-auth-white">
            <div class="kfms-auth-form-card">
                {{ $slot }}
            </div>
        </div>
    </section>
</main>
