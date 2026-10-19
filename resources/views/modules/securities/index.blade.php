@extends('layouts.admin')

@section('title', 'Securities')
@section('page-title', 'Securities')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Securities Registry</h2>
                <span>{{ $titles->total() }} records</span>
            </div>
            <div class="kfms-row-actions">
                @can('securities.dashboard')
                    <a class="kfms-link-btn" href="{{ route('securities.dashboard') }}">
                        <i class="mdi mdi-view-dashboard-outline"></i>
                        Dashboard
                    </a>
                @endcan
                @can('securities.export')
                <a class="kfms-link-btn" href="{{ route('securities.export', $filters) }}">
                    <i class="mdi mdi-download"></i>
                    Export
                </a>
                @endcan
                @can('securities.import')
                    <a class="kfms-link-btn" href="{{ route('securities.import') }}">
                        <i class="mdi mdi-upload"></i>
                        Import
                    </a>
                @endcan
                @can('securities.create')<a class="kfms-btn" href="{{ route('securities.create') }}">
                    <i class="mdi mdi-plus"></i>
                    Add Security
                </a>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('securities.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search reference, client, matter, borrower, or instruction">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>Type</span><select name="security_type"><option value="">All Types</option>@foreach($securityTypes as $value => $label)<option value="{{ $value }}" @selected(($filters['security_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Direction</span><select name="movement_direction"><option value="">All Directions</option><option value="incoming" @selected(($filters['movement_direction'] ?? '') === 'incoming')>Incoming</option><option value="outgoing" @selected(($filters['movement_direction'] ?? '') === 'outgoing')>Outgoing</option></select></label>
            <label><span>Institution</span><select name="bank_id"><option value="">All Institutions</option>@foreach($banks as $bank)<option value="{{ $bank->id }}" @selected((string)($filters['bank_id'] ?? '') === (string)$bank->id)>{{ $bank->name }}</option>@endforeach</select></label>
            <label><span>Institution branch</span><select name="bank_branch_id"><option value="">All Institution Branches</option>@foreach($bankBranches as $bankBranch)<option value="{{ $bankBranch->id }}" @selected((string)($filters['bank_branch_id'] ?? '') === (string)$bankBranch->id)>{{ $bankBranch->bank?->name }} — {{ $bankBranch->name }}</option>@endforeach</select></label>
            <label><span>MZO / Zonal office</span><select name="zonal_office_id"><option value="">All MZO / Zonal Offices</option>@foreach($zonalOffices as $zonalOffice)<option value="{{ $zonalOffice->id }}" @selected((string)($filters['zonal_office_id'] ?? '') === (string)$zonalOffice->id)>{{ $zonalOffice->name }}</option>@endforeach</select></label>
            <label><span>Handler</span><select name="handled_by"><option value="">All Handlers</option>@foreach($handlers as $handler)<option value="{{ $handler->id }}" @selected((string)($filters['handled_by'] ?? '') === (string)$handler->id)>{{ $handler->name }}</option>@endforeach</select></label>
            <label><span>Received from</span><input type="date" name="received_from" value="{{ $filters['received_from'] ?? '' }}"></label>
            <label><span>Received to</span><input type="date" name="received_to" value="{{ $filters['received_to'] ?? '' }}"></label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('securities.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Client / Matter</th>
                        <th>Borrower / Title</th>
                        <th>Institution / Branch</th>
                        <th>MZO</th>
                        <th>Instruction</th>
                        <th>Received</th>
                        <th>Returned</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($titles as $title)
                        <tr>
                            <td>{{ $title->reference_no }}</td>
                            <td>
                                {{ $title->client?->display_name ?: '-' }}<br>
                                <span class="kfms-muted">{{ $title->matter?->reference_no ?: 'No matter linked' }}</span>
                            </td>
                            <td>{{ $title->borrower_name }}<br><span class="kfms-muted">{{ $title->title_number ?: $title->land_reference ?: '-' }}</span></td>
                            <td>
                                {{ $title->bank?->name ?: '-' }}<br>
                                <span class="kfms-muted">{{ $title->bankBranch?->name ?: $title->received_from ?: '-' }}</span>
                            </td>
                            <td>{{ $title->zonalOffice?->name ?: '-' }}</td>
                            <td>{{ $title->instruction_type ?: '-' }}</td>
                            <td>{{ $title->received_at?->format('d M Y, H:i') ?: '-' }}</td>
                            <td>{{ $title->returned_at?->format('d M Y, H:i') ?: '-' }}</td>
                            <td>{{ $title->statusLabel() }}</td>
                            <td>
                                <div class="kfms-table-actions">
                                    @can('securities.show')<a href="{{ route('securities.show', $title) }}">View</a>@endcan
                                    @if ($title->status !== 'archived') @can('securities.edit')<a href="{{ route('securities.edit', $title) }}">Edit</a>@endcan @endif
                                    @if (! in_array($title->status, ['returned', 'closed', 'archived'], true))
                                        @can('securities.return.form')<a href="{{ route('securities.return.form', $title) }}">Return</a>@endcan
                                    @endif
                                    @if (in_array($title->status, ['returned', 'closed'], true)) @can('securities.archive')<form method="POST" action="{{ route('securities.archive', $title) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="is-danger" type="submit" onclick="return confirm('Archive this security? Its custody history will be retained.')">Archive</button>
                                    </form>@endcan @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="kfms-empty">No securities found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $titles->links() }}
    </section>
@endsection
