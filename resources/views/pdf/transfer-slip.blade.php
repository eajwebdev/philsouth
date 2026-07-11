<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Transfer Slip {{ $transfer->ts_no }}</title>
@php
    $logoPath = public_path('logo.png');
    $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    $fmtQty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 16px 18px; }
    table { border-collapse: collapse; }

    .head { width: 100%; border: 2px solid #111; }
    .head td { padding: 8px 10px; vertical-align: middle; }
    .head .logo { width: 58%; text-align: center; border-right: 2px solid #111; }
    .head .company { font-size: 8px; font-weight: bold; letter-spacing: 1px; }
    .head .title { font-size: 16px; font-weight: bold; text-align: center; }

    .tsno { text-align: right; margin: 8px 4px 4px; }
    .tsno .lbl { font-size: 11px; font-weight: bold; }
    .tsno .no { font-size: 17px; font-weight: bold; color: #c00; letter-spacing: 3px; }

    table.info { width: 100%; border: 1.5px solid #111; }
    table.info td { border: 1px solid #111; padding: 5px 8px; font-size: 10.5px; }
    .info .lbl { font-weight: bold; }

    table.grid { width: 100%; border: 1.5px solid #111; border-top: 0; }
    table.grid th { border: 1px solid #111; padding: 5px; font-size: 10.5px; font-weight: bold; text-align: center; background: #9a9a9a; }
    table.grid td { border: 1px solid #111; padding: 6px 6px; font-size: 10px; height: 22px; }
    .qty { width: 15%; text-align: center; }
    .unit { width: 17%; text-align: center; }

    table.recv { width: 100%; border: 1.5px solid #111; margin-top: 16px; }
    table.recv td { border: 1px solid #111; padding: 6px 8px; font-size: 10.5px; }
    .recv .lbl { font-weight: bold; }

    .footer { margin-top: 12px; width: 100%; }
    .form-no { font-size: 9px; }
    .dist { font-size: 8.5px; }
    .generated { font-size: 7.5px; color: #555; text-align: right; }
</style>
</head>
<body>

<table class="head" width="100%">
    <tr>
        <td class="logo">
            @if ($logoData)<img src="{{ $logoData }}" alt="PhilSouth" height="40"><br>@endif
            <span class="company">PHILSOUTH BUILDERS INC.</span>
        </td>
        <td class="title">Transfer Slip</td>
    </tr>
</table>

<div class="tsno"><span class="lbl">TS No.:</span> &nbsp; <span class="no">{{ $transfer->ts_no }}</span></div>

<table class="info" width="100%">
    <tr><td><span class="lbl">Date:</span> {{ \Illuminate\Support\Carbon::parse($transfer->date)->format('m/d/Y') }}</td></tr>
    <tr><td><span class="lbl">Time Delivered:</span> {{ $transfer->time_delivered }}</td></tr>
    <tr><td><span class="lbl">Delivered to:</span> {{ $transfer->delivered_to }}</td></tr>
    <tr><td><span class="lbl">Delivered by:</span> {{ $transfer->delivered_by }}</td></tr>
    <tr><td><span class="lbl">Vehicle Plate No.:</span> {{ $transfer->vehicle_plate }}</td></tr>
</table>

<table class="grid" width="100%">
    <thead>
        <tr>
            <th class="qty">Qty</th>
            <th class="unit">Unit</th>
            <th>Item Description</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($transfer->items as $line)
            <tr>
                <td class="qty">{{ $fmtQty($line->qty) }}</td>
                <td class="unit">{{ $line->unit ?: ($line->variant->uom ?: $line->variant->item->uom) }}</td>
                <td>{{ $line->variant->item->description }}@if($line->variant->label) — {{ $line->variant->label }}@endif</td>
            </tr>
        @endforeach
        @for ($i = 0; $i < max(0, 12 - $transfer->items->count()); $i++)
            <tr><td class="qty">&nbsp;</td><td></td><td></td></tr>
        @endfor
    </tbody>
</table>

<table class="recv" width="100%">
    <tr>
        <td style="width:34%"><span class="lbl">Date Received:</span></td>
        <td style="width:24%"><span class="lbl">Time:</span></td>
        <td><span class="lbl">Received by:</span></td>
    </tr>
    <tr>
        <td style="height:22px">{{ $transfer->date_received ? \Illuminate\Support\Carbon::parse($transfer->date_received)->format('m/d/Y') : '' }}&nbsp;</td>
        <td>{{ $transfer->time_received }}&nbsp;</td>
        <td>{{ $transfer->received_by }}&nbsp;</td>
    </tr>
</table>

<table class="footer">
    <tr>
        <td class="form-no">
            F-INV-004<br>Rev. 02 &nbsp; 01/05/21<br>
            <span class="dist">WHITE - Inventory Control Department &nbsp;&nbsp; YELLOW - Site Origin &nbsp;&nbsp; PINK - Site Destination</span>
        </td>
    </tr>
</table>

</body>
</html>
