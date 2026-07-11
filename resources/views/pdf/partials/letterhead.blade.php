{{-- Boxed letterhead matching the controlled paper forms: logo left, form title right. --}}
@php
    $logoPath = public_path('logo.png');
    $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
@endphp
<table class="letterhead" cellspacing="0" cellpadding="0">
    <tr>
        <td class="lh-logo">
            @if ($logoData)
                <img src="{{ $logoData }}" alt="PhilSouth Builders Inc." height="52">
            @endif
        </td>
        <td class="lh-title">{{ $title }}</td>
    </tr>
</table>
