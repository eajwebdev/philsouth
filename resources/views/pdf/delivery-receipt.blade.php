<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Delivery Receipt {{ $dr->dr_no }}</title>
@php
    $fmtQty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 22px 26px; }

    .letterhead { width: 100%; border: 2px solid #111; border-collapse: collapse; margin-bottom: 10px; }
    .letterhead td { border: 1px solid #111; padding: 6px 10px; vertical-align: middle; }
    .lh-logo { width: 45%; text-align: center; }
    .lh-company { font-size: 8px; letter-spacing: 1px; font-weight: bold; margin-top: 2px; }
    .lh-title { font-size: 15px; font-weight: bold; text-align: center; }

    .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .meta td { padding: 3px 4px; font-size: 10px; vertical-align: bottom; }
    .lbl { font-weight: bold; white-space: nowrap; }
    .val { border-bottom: 1px solid #111; }

    table.grid { width: 100%; border: 1.5px solid #111; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #111; padding: 4px 6px; }
    table.grid th { font-size: 9.5px; font-weight: bold; text-align: center; background: #eee; }
    table.grid td { font-size: 9.5px; }
    .num { text-align: right; }
    .ctr { text-align: center; }

    .sig { width: 100%; margin-top: 24px; }
    .sig td { width: 50%; padding: 12px 6px 2px; font-size: 10px; }
    .sig .line { border-bottom: 1px solid #111; min-height: 16px; }
    .sig .role { font-style: italic; font-size: 8px; color: #333; }

    .form-no { font-size: 8.5px; margin-top: 12px; }
</style>
</head>
<body>

@include('pdf.partials.letterhead', ['title' => 'DELIVERY RECEIPT'])

<table class="meta">
    <tr>
        <td class="lbl">DR No.:</td>
        <td class="val">{{ $dr->dr_no }}</td>
        <td class="lbl">Date Received:</td>
        <td class="val">{{ \Illuminate\Support\Carbon::parse($dr->received_date)->format('m/d/Y') }}</td>
    </tr>
    <tr>
        <td class="lbl">Project / Site:</td>
        <td class="val">{{ $dr->site->name }} ({{ $dr->site->code }})</td>
        <td class="lbl">Source:</td>
        <td class="val">{{ $dr->sourceLabel() }}</td>
    </tr>
</table>

<table class="grid">
    <thead>
        <tr>
            <th style="width:8%">Qty</th>
            <th style="width:10%">Unit</th>
            <th>Item Description</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($dr->items as $line)
            <tr>
                <td class="num">{{ $fmtQty($line->quantity) }}</td>
                <td class="ctr">{{ $line->variant->uom ?: $line->variant->item->uom }}</td>
                <td>{{ $line->variant->item->description }}@if($line->variant->label) — {{ $line->variant->label }}@endif</td>
            </tr>
        @endforeach
        @for ($i = 0; $i < max(0, 12 - $dr->items->count()); $i++)
            <tr><td>&nbsp;</td><td></td><td></td></tr>
        @endfor
    </tbody>
</table>

@if ($dr->remarks)
    <p style="margin-top:8px;font-size:9.5px"><span class="lbl">Remarks:</span> {{ $dr->remarks }}</p>
@endif

<table class="sig">
    <tr>
        <td>
            <span class="lbl">Prepared by:</span>
            <div class="line">{{ $dr->creator?->name }}&nbsp;</div>
            <div class="role">ICS</div>
        </td>
        <td>
            <span class="lbl">Received by:</span>
            <div class="line">{{ $dr->received_by }}&nbsp;</div>
            <div class="role">Signature Over Printed Name</div>
        </td>
    </tr>
</table>

</body>
</html>
