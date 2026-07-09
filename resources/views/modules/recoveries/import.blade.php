@extends('layouts.admin')

@section('title', 'Import Recovery Portfolio')
@section('page-title', 'Import Recovery Portfolio')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <div class="kfms-grid-two kfms-recovery-import-grid">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Import Recovery Portfolio</h2>
                    <span>Select the client and workbook structure before upload.</span>
                </div>
                <a class="kfms-link-btn" href="{{ route('recoveries.dashboard') }}"><i class="mdi mdi-arrow-left"></i> Dashboard</a>
            </div>

            <form class="kfms-form" method="POST" action="{{ route('recoveries.import.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="kfms-form-grid">
                    <label>
                        <span>Recovery Client</span>
                        <select name="recovery_client_id" required>
                            <option value="">Select client</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" data-portfolios='@json($client->portfolio_types ?: $defaultPortfolioTypes)' @selected(old('recovery_client_id') == $client->id)>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('recovery_client_id') <small>{{ $message }}</small> @enderror
                    </label>

                    <label>
                        <span>Portfolio Type</span>
                        <select name="portfolio_type" required data-recovery-portfolio-select>
                            <option value="">Select portfolio type</option>
                            @foreach ($defaultPortfolioTypes as $type)
                                <option value="{{ $type }}" @selected(old('portfolio_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('portfolio_type') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-span-2">
                        <span>Excel / CSV File</span>
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                        @error('file') <small>{{ $message }}</small> @enderror
                    </label>

                    <label>
                        <span>Assign All To</span>
                        <select name="assigned_to">
                            <option value="">Leave unassigned for review</option>
                            @foreach ($officers as $officer)
                                <option value="{{ $officer->id }}" @selected(old('assigned_to') == $officer->id)>{{ $officer->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_to') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-check-row">
                        <input type="checkbox" name="match_collector" value="1" @checked(old('match_collector'))>
                        <span>Match Excel collector names to recovery officers</span>
                    </label>

                    <label class="kfms-span-2">
                        <span>Import Notes</span>
                        <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
                        @error('notes') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit"><i class="mdi mdi-file-upload-outline"></i> Import Portfolio</button>
                </div>
            </form>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Add Recovery Client</h2>
                    <span>Create the bank/client and its portfolio types.</span>
                </div>
            </div>
            <form class="kfms-form" method="POST" action="{{ route('recoveries.clients.store') }}">
                @csrf
                <div class="kfms-form-grid">
                    <label class="kfms-span-2">
                        <span>Client / Institution Name</span>
                        <input type="text" name="name" value="{{ old('name') }}" required>
                        @error('name') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Contact Person</span>
                        <input type="text" name="contact_person" value="{{ old('contact_person') }}">
                        @error('contact_person') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Phone</span>
                        <input type="text" name="phone" value="{{ old('phone') }}">
                        @error('phone') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="kfms-span-2">
                        <span>Email</span>
                        <input type="email" name="email" value="{{ old('email') }}">
                        @error('email') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="kfms-span-2">
                        <span>Portfolio Types</span>
                        <textarea name="portfolio_types" rows="4" placeholder="One type per line, for example Stanbic NPL">{{ old('portfolio_types') }}</textarea>
                        @error('portfolio_types') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="kfms-span-2">
                        <span>Description</span>
                        <textarea name="description" rows="3">{{ old('description') }}</textarea>
                        @error('description') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit"><i class="mdi mdi-bank-plus"></i> Save Client</button>
                </div>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clientSelect = document.querySelector('select[name="recovery_client_id"]');
            const portfolioSelect = document.querySelector('[data-recovery-portfolio-select]');
            const selectedValue = @json(old('portfolio_type'));

            function refreshPortfolios() {
                const option = clientSelect.options[clientSelect.selectedIndex];
                const portfolios = option && option.dataset.portfolios ? JSON.parse(option.dataset.portfolios) : @json($defaultPortfolioTypes);
                portfolioSelect.innerHTML = '<option value="">Select portfolio type</option>';
                portfolios.forEach(function (portfolio) {
                    const item = document.createElement('option');
                    item.value = portfolio;
                    item.textContent = portfolio;
                    item.selected = selectedValue === portfolio;
                    portfolioSelect.appendChild(item);
                });
            }

            if (clientSelect && portfolioSelect) {
                clientSelect.addEventListener('change', refreshPortfolios);
                refreshPortfolios();
            }
        });
    </script>
@endpush
