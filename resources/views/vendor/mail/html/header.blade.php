@props(['url'])
@php
    $companyName = \App\Support\Branding::companyName();
    $logoUrl = \App\Support\Branding::logoUrl();
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if ($logoUrl)
<img src="{{ $logoUrl }}" class="logo" alt="{{ $companyName }} logo" style="display: block; height: 76px; margin: 0 auto 10px; max-width: 180px; object-fit: contain;">
@endif
<span style="color: #061a2f; display: block; font-size: 18px; font-weight: 700; line-height: 1.35;">{{ $companyName }}</span>
</a>
</td>
</tr>
