<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Site Warehouse Stock Card</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 24px 28px; }

    .letterhead { width: 100%; border: 2px solid #111; border-collapse: collapse; margin-bottom: 10px; }
    .letterhead td { border: 1px solid #111; padding: 6px 10px; vertical-align: middle; }
    .lh-logo { width: 45%; text-align: center; }
    .lh-company { font-size: 8px; letter-spacing: 1px; font-weight: bold; margin-top: 2px; }
    .lh-title { font-size: 15px; font-weight: bold; text-align: center; }

    .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .meta td { padding: 2px 4px; font-size: 9.5px; }
    .meta .lbl { font-weight: bold; white-space: nowrap; }
    .meta .val { border-bottom: 1px solid #111; min-width: 90px; }

    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #111; padding: 3px 4px; }
    table.grid th { font-size: 8px; font-weight: bold; text-align: center; background: #f0f0f0; }
    table.grid td { font-size: 8.5px; }
    .num { text-align: right; }
    .ctr { text-align: center; }
    .bf td { font-style: italic; background: #fafafa; }
    tfoot td { font-weight: bold; background: #f0f0f0; }

    .footer { margin-top: 10px; width: 100%; }
    .form-no { font-size: 8px; }
    .generated { font-size: 7.5px; color: #555; text-align: right; }
</style>
</head>
<body>

@include('pdf.partials.letterhead', ['title' => 'Site Warehouse Stock Card'])

<table class="meta">
    <tr>
        <td class="lbl">Item Description:</td>
        <td class="val">{{ $card['variant']['item']['description'] }}@if($card['variant']['label']) — {{ $card['variant']['label'] }}@endif ({{ $card['variant']['sku'] }})</td>
        <td class="lbl">Min:</td>
        <td class="val">{{ rtrim(rtrim(number_format($card['header']['min_qty'], 2), '0'), '.') }}</td>
        <td class="lbl">U.O.M.:</td>
        <td class="val">{{ $card['variant']['uom'] }}</td>
    </tr>
    <tr>
        <td class="lbl">Location:</td>
        <td class="val">{{ $card['header']['location'] ?? '' }}&nbsp;</td>
        <td class="lbl">Max:</td>
        <td class="val">{{ $card['header']['max_qty'] !== null ? rtrim(rtrim(number_format($card['header']['max_qty'], 2), '0'), '.') : '' }}&nbsp;</td>
        <td class="lbl">Project:</td>
        <td class="val">{{ $card['site']['name'] }} ({{ $card['site']['code'] }})</td>
    </tr>
    @if (!empty($range['label']))
    <tr>
        <td class="lbl">Period:</td>
        <td class="val" colspan="5">{{ $range['label'] }}</td>
    </tr>
    @endif
</table>

<table class="grid">
    <thead>
        <tr>
            <th rowspan="2" style="width:9%">Date</th>
            <th rowspan="2" style="width:9%">DR/WS<br>No.</th>
            <th colspan="1">INCOMING</th>
            <th colspan="1">ISSUANCE</th>
            <th colspan="2">QUANTITY</th>
            <th rowspan="2" style="width:9%">Balance<br>On-Hand</th>
            <th rowspan="2" style="width:14%">Remarks</th>
        </tr>
        <tr>
            <th style="width:20%">Supplier / Other Projects</th>
            <th style="width:20%">Issued To</th>
            <th style="width:8%">In</th>
            <th style="width:8%">Out</th>
        </tr>
    </thead>
    <tbody>
        @if (!empty($card['broughtForward']))
            <tr class="bf">
                <td class="ctr">{{ \Illuminate\Support\Carbon::parse($card['broughtForward']['date'])->format('m/d/Y') }}</td>
                <td class="ctr">—</td>
                <td colspan="2">Balance brought forward</td>
                <td></td>
                <td></td>
                <td class="num">{{ rtrim(rtrim(number_format($card['broughtForward']['balance'], 2), '0'), '.') }}</td>
                <td></td>
            </tr>
        @endif
        @forelse ($card['rows'] as $r)
            <tr>
                <td class="ctr">{{ \Illuminate\Support\Carbon::parse($r['date'])->format('m/d/Y') }}</td>
                <td class="ctr">{{ $r['dr_ws_no'] ?? '' }}</td>
                <td>{{ $r['in'] !== null ? $r['source_label'] : '' }}</td>
                <td>{{ $r['issued_to'] ?? ($r['out'] !== null ? $r['source_label'] : '') }}</td>
                <td class="num">{{ $r['in'] !== null ? rtrim(rtrim(number_format($r['in'], 2), '0'), '.') : '' }}</td>
                <td class="num">{{ $r['out'] !== null ? rtrim(rtrim(number_format($r['out'], 2), '0'), '.') : '' }}</td>
                <td class="num">{{ rtrim(rtrim(number_format($r['balance'], 2), '0'), '.') }}</td>
                <td>{{ $r['remarks'] ?? '' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="ctr" style="padding:12px">No movements recorded for this period.</td></tr>
        @endforelse
        @for ($i = 0; $i < max(0, 8 - count($card['rows'])); $i++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="num">TOTALS</td>
            <td class="num">{{ rtrim(rtrim(number_format($card['totals']['in'], 2), '0'), '.') }}</td>
            <td class="num">{{ rtrim(rtrim(number_format($card['totals']['out'], 2), '0'), '.') }}</td>
            <td class="num">{{ rtrim(rtrim(number_format($card['header']['balance'], 2), '0'), '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<table class="footer">
    <tr>
        <td class="form-no">F-INV-002<br>Rev. 2 01/05/21</td>
        <td class="generated">System generated · {{ now()->format('m/d/Y h:i A') }}</td>
    </tr>
</table>

</body>
</html>
