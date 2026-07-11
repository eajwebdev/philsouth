<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Monthly Inventory Summary</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; margin: 22px 24px; }

    .letterhead { width: 100%; border: 2px solid #111; border-collapse: collapse; margin-bottom: 10px; }
    .letterhead td { border: 1px solid #111; padding: 6px 10px; vertical-align: middle; }
    .lh-logo { width: 40%; text-align: center; }
    .lh-company { font-size: 8px; letter-spacing: 1px; font-weight: bold; margin-top: 2px; }
    .lh-title { font-size: 14px; font-weight: bold; text-align: center; letter-spacing: 1px; }

    .meta { margin-bottom: 8px; font-size: 10px; }
    .meta .lbl { font-weight: bold; }
    .meta .val { border-bottom: 1px solid #111; padding: 0 40px 0 6px; }

    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #111; padding: 3px 3px; }
    table.grid th { font-size: 7px; font-weight: bold; text-align: center; background: #f0f0f0; }
    table.grid td { font-size: 8px; }
    .num { text-align: right; }
    .ctr { text-align: center; }

    .sig { width: 100%; margin-top: 28px; }
    .sig td { width: 50%; font-size: 9.5px; font-weight: bold; }
    .sig .line { display: inline-block; border-bottom: 1px solid #111; min-width: 220px; text-align: center; font-weight: normal; }

    .footer { margin-top: 14px; width: 100%; }
    .form-no { font-size: 8px; }
    .generated { font-size: 7.5px; color: #555; text-align: right; }
</style>
</head>
<body>

@include('pdf.partials.letterhead', ['title' => 'MONTHLY INVENTORY SUMMARY'])

<div class="meta">
    <span class="lbl">PROJECT:</span> <span class="val">{{ $summary['site']['name'] }} ({{ $summary['site']['code'] }})</span>
    &nbsp;&nbsp;&nbsp;
    <span class="lbl">PERIOD:</span> <span class="val">{{ strtoupper($summary['period_label']) }}</span>
</div>

<table class="grid">
    <thead>
        <tr>
            <th rowspan="2" style="width:19%">ITEM DESCRIPTION</th>
            <th rowspan="2" style="width:5%">U.O.M.</th>
            <th rowspan="2" style="width:7%">BEGINNING<br>INVENTORY</th>
            <th colspan="3">INVENTORY - IN</th>
            <th colspan="6">INVENTORY - OUT</th>
            <th rowspan="2" style="width:7%">ENDING<br>INVENTORY</th>
        </tr>
        <tr>
            <th style="width:7%">PURCHASES -<br>SUPPLIERS</th>
            <th style="width:8%">PAL &amp; MAIN<br>WAREHOUSE /<br>EMD /<br>PRODUCTION<br>STOCKING</th>
            <th style="width:6%">TRANSFER<br>IN</th>
            <th style="width:6%">USAGE</th>
            <th style="width:6%">TRANSFER<br>OUT</th>
            <th style="width:6%">LOSS &amp;<br>DAMAGES</th>
            <th style="width:6%">RETURN TO<br>SUPPLIER</th>
            <th style="width:8%">PAL &amp; MAIN<br>WAREHOUSE /<br>EMD /<br>PRODUCTION<br>STOCKING</th>
            <th style="width:6%">SALES OR<br>OTHERS</th>
        </tr>
    </thead>
    <tbody>
        @php
            $fmt = fn ($n) => $n ? rtrim(rtrim(number_format($n, 2), '0'), '.') : '';
        @endphp
        @foreach ($summary['rows'] as $r)
            <tr>
                <td>{{ $r['variant']['description'] }}@if($r['variant']['label']) — {{ $r['variant']['label'] }}@endif</td>
                <td class="ctr">{{ $r['variant']['uom'] }}</td>
                <td class="num">{{ $fmt($r['beginning']) }}</td>
                <td class="num">{{ $fmt($r['purchases']) }}</td>
                <td class="num">{{ $fmt($r['warehouse_in']) }}</td>
                <td class="num">{{ $fmt($r['transfer_in']) }}</td>
                <td class="num">{{ $fmt($r['usage']) }}</td>
                <td class="num">{{ $fmt($r['transfer_out']) }}</td>
                <td class="num">{{ $fmt($r['loss_damage']) }}</td>
                <td class="num">{{ $fmt($r['return_supplier']) }}</td>
                <td class="num">{{ $fmt($r['warehouse_out']) }}</td>
                <td class="num">{{ $fmt($r['sale_other']) }}</td>
                <td class="num"><b>{{ $fmt($r['ending']) ?: '0' }}</b></td>
            </tr>
        @endforeach
        @for ($i = 0; $i < max(0, 12 - count($summary['rows'])); $i++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
    </tbody>
</table>

<table class="sig">
    <tr>
        <td>Prepared by: <span class="line">{{ $preparedBy }}</span></td>
        <td style="text-align:right">Checked by: <span class="line">&nbsp;</span></td>
    </tr>
</table>

<table class="footer">
    <tr>
        <td class="form-no">F-INV-006<br>Rev. 0 6.4.2024</td>
    </tr>
</table>

</body>
</html>
