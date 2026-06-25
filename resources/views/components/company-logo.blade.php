@props([
    'markClass' => 'kfms-brand-mark',
    'imageClass' => 'kfms-brand-image',
])

@if ($companySetting->logo_url)
    <img class="{{ $imageClass }}" src="{{ $companySetting->logo_url }}" alt="{{ $companySetting->company_name }} logo">
@else
    <span class="{{ $markClass }}">{{ $companySetting->initials }}</span>
@endif
