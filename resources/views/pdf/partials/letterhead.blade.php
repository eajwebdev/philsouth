{{-- Boxed letterhead matching the controlled paper forms: logo left, form title right. --}}
@php
    $logoPath = public_path('logo.jpg');
    $logoData = is_file($logoPath) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath)) : null;
@endphp
<table class="letterhead" cellspacing="0" cellpadding="0">
    <tr>
        <td class="lh-logo">
            @if ($logoData)
                <img src="{{ $logoData }}" alt="PhilSouth Builders Inc." height="52">
            @endif
            <div class="lh-company">PHILSOUTH BUILDERS INC.</div>
        </td>
        <td class="lh-title">{{ $title }}</td>
    </tr>
</table>
