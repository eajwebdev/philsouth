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
        <td class="val">{{ $card['variant']['item']['description'] }}@if($card['variant']['label']) — {{ $card['variant']['label'] }}@endif</td>
        <td class="lbl">Min:</td>
        <td class="val">{{ rtrim(rtrim(number_format($card['header']['min_qty'], 2), '0'), '.') }}</td>
    </tr>
    <tr>
        <td class="lbl">Location:</td>
        <td class="val">{{ $card['header']['location'] ?? '' }}&nbsp;</td>
        <td class="lbl">Max:</td>
        <td class="val">{{ $card['header']['max_qty'] !== null ? rtrim(rtrim(number_format($card['header']['max_qty'], 2), '0'), '.') : '' }}&nbsp;</td>
    </tr>
</table>

<table class="grid">
    <thead>
        <tr>
            <th rowspan="2" style="width:9%">Date</th>
            <th rowspan="2" style="width:9%">DR/WS<br>No.</th>
            <th colspan="1">INCOMING</th>
            <th colspan="2">ISSUANCE</th>
            <th colspan="2">QUANTITY</th>
            <th rowspan="2" style="width:9%">Balance<br>On-Hand</th>
            <th rowspan="2" style="width:14%">Remarks</th>
        </tr>
        <tr>
            <th style="width:18%">Supplier / Other Projects</th>
            <th style="width:9%">WS No.</th>
            <th style="width:15%">Issued To</th>
            <th style="width:8%">In</th>
            <th style="width:8%">Out</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($card['rows'] as $r)
            <tr>
                <td class="ctr">{{ \Illuminate\Support\Carbon::parse($r['date'])->format('m/d/Y') }}</td>
                <td class="ctr">{{ $r['in'] !== null ? ($r['dr_ws_no'] ?? '') : '' }}</td>
                <td>{{ $r['in'] !== null ? $r['source_label'] : '' }}</td>
                <td class="ctr">{{ $r['out'] !== null ? ($r['dr_ws_no'] ?? '') : '' }}</td>
                <td>{{ $r['issued_to'] ?? '' }}</td>
                <td class="num">{{ $r['in'] !== null ? rtrim(rtrim(number_format($r['in'], 2), '0'), '.') : '' }}</td>
                <td class="num">{{ $r['out'] !== null ? rtrim(rtrim(number_format($r['out'], 2), '0'), '.') : '' }}</td>
                <td class="num">{{ rtrim(rtrim(number_format($r['balance'], 2), '0'), '.') }}</td>
                <td>{{ $r['remarks'] ?? '' }}</td>
            </tr>
        @endforeach
        @for ($i = 0; $i < max(0, 14 - count($card['rows'])); $i++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
    </tbody>
</table>

<table class="footer">
    <tr>
        <td class="form-no">F-INV-002<br>Rev. 2 01/05/21</td>
    </tr>
</table>

</body>
</html>
