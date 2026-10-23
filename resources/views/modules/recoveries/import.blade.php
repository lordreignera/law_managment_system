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
                    <span>Choose a client and portfolio, download its template, then upload matching account data.</span>
                </div>
                <a class="kfms-link-btn" href="{{ route('recoveries.dashboard') }}"><i class="mdi mdi-arrow-left"></i> Dashboard</a>
                <a class="kfms-link-btn" href="{{ route('imports.templates.download', 'recoveries') }}">Standard CSV</a>
            </div>

            <form class="kfms-form" method="POST" action="{{ route('recoveries.import.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_preview" value="1">
                <div class="kfms-form-grid">
                    <label>
                        <span>1. Recovery Client</span>
                        <select name="recovery_client_id" required>
                            <option value="">Select client</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" data-portfolios='@json($client->portfolio_types ?: $defaultPortfolioTypes)' @selected(old('recovery_client_id', request('recovery_client_id')) == $client->id)>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('recovery_client_id') <small>{{ $message }}</small> @enderror
                    </label>

                    <label>
                        <span>2. Portfolio Type</span>
                        <select name="portfolio_type" required data-recovery-portfolio-select>
                            <option value="">Select portfolio type</option>
                            @foreach ($defaultPortfolioTypes as $type)
                                <option value="{{ $type }}" @selected(old('portfolio_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('portfolio_type') <small>{{ $message }}</small> @enderror
                    </label>

                    <div class="kfms-span-2 kfms-recovery-template-choice">
                        <span data-recovery-template-status>Choose a client and portfolio to see its template.</span>
                        <a class="kfms-link-btn" data-recovery-template-download href="{{ route('imports.templates.download', 'recoveries') }}">Download Standard CSV</a>
                    </div>

                    <label class="kfms-span-2">
                        <span>3. Excel / CSV File</span>
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

                    <p class="kfms-muted-text kfms-span-2">For a workbook with several sheets, the selected portfolio or configured sheet name must match one sheet. A client template accepts its mapped headings; the standard CSV works for every portfolio. Collector names must match staff names exactly. Source branch names and prior lender recoveries are kept as context; record the firm's new payments on each account.</p>

                    <label class="kfms-span-2">
                        <span>Import Notes</span>
                        <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
                        @error('notes') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit"><i class="mdi mdi-file-search-outline"></i> Preview Portfolio</button>
                </div>
            </form>
        </section>

        <div class="kfms-recovery-setup-stack">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Client Import Templates</h2>
                    <span>Map a lender's column headings once for each portfolio.</span>
                </div>
            </div>
            <div class="kfms-form-actions">
                @can('recoveries.templates.store')
                    <button class="kfms-btn" type="button" data-recovery-template-open data-bs-toggle="modal" data-bs-target="#recovery-template-modal" disabled>Create / Edit Template</button>
                @endcan
            </div>
            <p class="kfms-muted-text">Select a client and portfolio on the left. If its file already uses the standard CSV headings, no custom template is needed.</p>
            @if ($templates->isNotEmpty())
                <details class="kfms-recovery-template-list">
                    <summary>Configured templates ({{ $templates->count() }})</summary>
                    <div class="kfms-table-wrap">
                        <table class="kfms-table">
                            <thead><tr><th>Client</th><th>Portfolio</th><th>Template</th></tr></thead>
                            <tbody>
                                @foreach ($templates as $template)
                                    <tr>
                                        <td>{{ $template->client?->name }}</td>
                                        <td>{{ $template->portfolio_type }}</td>
                                        <td>
                                            <a href="{{ route('recoveries.templates.download', $template) }}">CSV</a>
                                            @can('recoveries.templates.destroy')
                                                <form class="kfms-inline-form" method="POST" action="{{ route('recoveries.templates.destroy', $template) }}" onsubmit="return confirm('Remove this import template?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="kfms-text-action kfms-danger" type="submit">Remove</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
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
                <input type="hidden" name="return_to" value="import">
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
                        <textarea name="portfolio_types" rows="3" placeholder="One type per line, for example NPL and Write Off" required>{{ old('portfolio_types') }}</textarea>
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
        <section class="kfms-panel">
            <div class="kfms-panel-header"><div><h2>Add Portfolio</h2><span>Use this when an existing client sends a new category of work.</span></div></div>
            <form class="kfms-form" method="POST" data-recovery-add-portfolio-form>
                @csrf
                <label><span>New Portfolio Name</span><input type="text" name="portfolio_type" maxlength="100" placeholder="For example, SME Write Off" required></label>
                @error('portfolio_type') <small>{{ $message }}</small> @enderror
                <div class="kfms-form-actions"><button class="kfms-link-btn" type="submit" data-recovery-add-portfolio-button disabled>Add to Selected Client</button></div>
            </form>
        </section>
        </div>
    </div>
@endsection

@can('recoveries.templates.store')
    @push('modals')
        <div class="modal fade kfms-modal" id="recovery-template-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog kfms-setting-modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">Client Import Template</h5>
                            <span data-recovery-template-heading>Choose a client and portfolio first.</span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="kfms-form" method="POST" action="{{ route('recoveries.templates.store') }}" data-recovery-template-form>
                        @csrf
                        <input type="hidden" name="_template_form" value="1">
                        <input type="hidden" name="recovery_client_id" value="{{ old('recovery_client_id') }}" data-template-client>
                        <input type="hidden" name="portfolio_type" value="{{ old('portfolio_type') }}" data-template-portfolio>
                        <div class="modal-body">
                            <p class="kfms-muted-text">Enter the exact column heading used by the lender for each field. Debtor name and outstanding amount are required. Leave optional fields blank when the source file has no matching column.</p>
                            <div class="kfms-form-grid">
                                <label class="kfms-span-2">
                                    <span>Worksheet Name (optional)</span>
                                    <input type="text" name="sheet_name" maxlength="31" value="{{ old('sheet_name') }}" data-template-sheet placeholder="Leave blank to match the portfolio name">
                                    @error('sheet_name') <small>{{ $message }}</small> @enderror
                                </label>
                                @foreach (['debtor_name', 'outstanding_amount'] as $field)
                                    <label>
                                        <span>{{ $templateFields[$field] }} *</span>
                                        <input type="text" name="column_map[{{ $field }}]" value="{{ old('column_map.'.$field) }}" data-template-field="{{ $field }}" required>
                                        @error('column_map.'.$field) <small>{{ $message }}</small> @enderror
                                    </label>
                                @endforeach
                            </div>
                            @error('column_map') <small>{{ $message }}</small> @enderror
                            <details class="kfms-recovery-template-optional">
                                <summary>Optional source columns</summary>
                                <div class="kfms-form-grid">
                                    @foreach ($templateFields as $field => $label)
                                        @continue(in_array($field, ['debtor_name', 'outstanding_amount'], true))
                                        <label>
                                            <span>{{ $label }}</span>
                                            <input type="text" name="column_map[{{ $field }}]" value="{{ old('column_map.'.$field) }}" data-template-field="{{ $field }}">
                                            @error('column_map.'.$field) <small>{{ $message }}</small> @enderror
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                            <div class="kfms-form-actions">
                                <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                                <button class="kfms-btn" type="submit">Save Template</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endpush
@endcan

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clientSelect = document.querySelector('select[name="recovery_client_id"]');
            const portfolioSelect = document.querySelector('[data-recovery-portfolio-select]');
            const selectedValue = @json(old('portfolio_type', request('portfolio_type')));
            const templates = @json($templateOptions);
            const standardUrl = @json(route('imports.templates.download', 'recoveries'));
            const portfolioAction = @json(route('recoveries.clients.portfolios.store', ['client' => '__CLIENT__']));
            const templateStatus = document.querySelector('[data-recovery-template-status]');
            const templateDownload = document.querySelector('[data-recovery-template-download]');
            const templateButton = document.querySelector('[data-recovery-template-open]');
            const templateForm = document.querySelector('[data-recovery-template-form]');
            const portfolioForm = document.querySelector('[data-recovery-add-portfolio-form]');
            const portfolioButton = document.querySelector('[data-recovery-add-portfolio-button]');

            function currentTemplate() {
                return templates.find(function (template) {
                    return String(template.client_id) === clientSelect.value && template.portfolio_type === portfolioSelect.value;
                });
            }

            function refreshTemplate() {
                const ready = !!(clientSelect.value && portfolioSelect.value);
                const template = ready ? currentTemplate() : null;
                if (templateButton) {
                    templateButton.disabled = !ready;
                    templateButton.textContent = template ? 'Edit Client Template' : 'Create Client Template';
                }
                if (portfolioButton) portfolioButton.disabled = !clientSelect.value;
                if (portfolioForm && clientSelect.value) {
                    portfolioForm.action = portfolioAction.replace('__CLIENT__', clientSelect.value);
                }
                templateDownload.href = template ? template.download_url : standardUrl;
                templateDownload.textContent = template ? 'Download Client CSV' : 'Download Standard CSV';
                templateStatus.textContent = !ready
                    ? 'Choose a client and portfolio to see its template.'
                    : (template ? 'Client template configured for this portfolio.' : 'No client template yet. The standard CSV is ready to use.');
            }

            function refreshPortfolios(preferred) {
                const option = clientSelect.options[clientSelect.selectedIndex];
                const portfolios = option && option.dataset.portfolios ? JSON.parse(option.dataset.portfolios) : @json($defaultPortfolioTypes);
                portfolioSelect.innerHTML = '<option value="">Select portfolio type</option>';
                portfolios.forEach(function (portfolio) {
                    const item = document.createElement('option');
                    item.value = portfolio;
                    item.textContent = portfolio;
                    item.selected = preferred === portfolio;
                    portfolioSelect.appendChild(item);
                });
                refreshTemplate();
            }

            if (clientSelect && portfolioSelect) {
                clientSelect.addEventListener('change', function () { refreshPortfolios(null); });
                portfolioSelect.addEventListener('change', refreshTemplate);
                refreshPortfolios(selectedValue);
            }

            if (templateButton && templateForm) {
                templateButton.addEventListener('click', function () {
                    const template = currentTemplate();
                    templateForm.querySelector('[data-template-client]').value = clientSelect.value;
                    templateForm.querySelector('[data-template-portfolio]').value = portfolioSelect.value;
                    templateForm.querySelector('[data-template-sheet]').value = template ? (template.sheet_name || '') : '';
                    document.querySelector('[data-recovery-template-heading]').textContent = clientSelect.options[clientSelect.selectedIndex].text + ' / ' + portfolioSelect.value;
                    templateForm.querySelectorAll('[data-template-field]').forEach(function (input) {
                        const field = input.dataset.templateField;
                        input.value = template ? (template.column_map[field] || '') : (['debtor_name', 'outstanding_amount'].includes(field) ? field : '');
                    });
                });
            }

            if (templateForm && @json(old('_template_form') === '1') && window.bootstrap?.Modal) {
                document.querySelector('[data-recovery-template-heading]').textContent = clientSelect.options[clientSelect.selectedIndex].text + ' / ' + portfolioSelect.value;
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('recovery-template-modal')).show();
            }
        });
    </script>
@endpush
