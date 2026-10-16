@extends('layouts.admin')
@section('title', 'Import Master Data')
@section('page-title', 'Import Master Data')
@section('content')
<section class="kfms-panel">
    <div class="kfms-panel-header">
        <div><h2>Populate Master Data</h2><span>Load the firm’s approved lists before opening operational records.</span></div>
        <a class="kfms-link-btn" href="{{ route('settings.system.overview') }}">System Settings</a>
    </div>
    @if (session('status')) <div class="kfms-alert">{{ session('status') }}</div> @endif
    <p class="kfms-muted-text">Import parent lists first: branches before departments and users; banks before bank branches; courts before judicial officers; Centenary regions before Centenary branches. Use exact parent names and currency ISO codes. Use 1 or 0 for checkboxes and separate portfolio types or role permissions with |.</p>
    <p class="kfms-muted-text">Reference lists update matching names within their parent. Users and roles are created only; existing emails or role names are rejected. An invalid row rolls back the file and reports its row number. Staff passwords are required for user imports and should be changed after the first sign-in.</p>
    <form class="kfms-form" method="POST" action="{{ route('settings.master-data.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="kfms-form-grid">
            <label><span>Reference List</span><select name="setting" required>
                <option value="">Select list</option>
                @foreach ($definitions as $slug => $definition)
                    <option value="{{ $slug }}" @selected(old('setting', request('setting')) === $slug)>{{ $definition['title'] }}</option>
                @endforeach
            </select>@error('setting') <small>{{ $message }}</small> @enderror</label>
            <label><span>Completed CSV or Excel File</span><input type="file" name="file" accept=".csv,.xlsx,.xls" required>@error('file') <small>{{ $message }}</small> @enderror</label>
        </div>
        <div class="kfms-form-actions"><button type="submit" class="kfms-btn">Import Approved Records</button></div>
    </form>
</section>
<section class="kfms-panel">
    <div class="kfms-panel-header"><h2>Import Templates</h2></div>
    <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Reference List</th><th>Template</th></tr></thead><tbody>
    @foreach ($definitions as $slug => $definition)
        <tr><td>{{ $definition['title'] }}</td><td><a class="kfms-link-btn" href="{{ route('settings.master-data.template', $slug) }}">Download CSV</a></td></tr>
    @endforeach
    </tbody></table></div>
</section>
@endsection
