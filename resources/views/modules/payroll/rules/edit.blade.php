@extends('layouts.admin')
@section('title', 'Edit Payroll Rule')
@section('page-title', 'Edit Payroll Rule')
@section('content')<section class="kfms-panel"><div class="kfms-panel-header"><div><h2>Edit Calculation Rule</h2><span>{{ $rule->code }} · {{ $rule->name }}</span></div><a class="kfms-link-btn" href="{{ route('payroll.rules.index') }}">Back to Rules</a></div><form class="kfms-form" method="POST" action="{{ route('payroll.rules.update', $rule) }}">@include('modules.payroll.rules._form')</form></section>@endsection
