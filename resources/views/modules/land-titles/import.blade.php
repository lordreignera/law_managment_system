@extends('layouts.admin')

@section('title', 'Import Securities')
@section('page-title', 'Securities')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Import Securities</h2>
                <span>Upload Excel or CSV records into the securities register.</span>
            </div>
            <div class="kfms-row-actions">
                <a class="kfms-link-btn" href="{{ route('land-titles.dashboard') }}">
                    <i class="mdi mdi-view-dashboard-outline"></i>
                    Dashboard
                </a>
                <a class="kfms-link-btn" href="{{ route('land-titles.index') }}">
                    <i class="mdi mdi-format-list-bulleted"></i>
                    Register
                </a>
            </div>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('land-titles.import.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="kfms-form-grid">
                <label class="kfms-span-2">
                    <span>Workbook</span>
                    <input type="file" name="file" required accept=".xlsx,.xls,.csv">
                    @error('file') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-import-help">
                <h3>Accepted columns</h3>
                <p>Use the headings below. Bank, bank branch, MZO, handler, and matter are matched against existing records by name, email, or reference.</p>
                <div class="kfms-table-wrap">
                    <table class="kfms-table">
                        <thead><tr><th>Required</th><th>Optional</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>borrower_name</td>
                                <td>reference_no, bank, bank_branch, mzo, matter_reference, handler, instruction_type, instruction_date, received_from, returned_to, received_at, dispatched_at, returned_at, status, notes</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('land-titles.index') }}">Cancel</a>
                <button type="submit"><i class="mdi mdi-upload"></i> Import Securities</button>
            </div>
        </form>
    </section>
@endsection
