<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Withdrawal Slip {{ $slip->ws_no }}</title>
@php
    $logoPath = public_path('logo.png');
    $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    $type = $slip->requested_by_type;
    $chk = fn ($v) => $type === $v ? '&#10004;' : '&nbsp;';
    $fmtQty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $time = $slip->time ? \Illuminate\Support\Carbon::parse($slip->time)->format('h:i') : '';
    $ampm = $slip->time ? \Illuminate\Support\Carbon::parse($slip->time)->format('A') : '';
@endphp
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 16px 18px; }
    table { border-collapse: collapse; }

    /* Everything lives inside one outer frame, like the printed pad. */
    .slip { border: 2px solid #111; padding: 0; }

    .head { width: 100%; }
    .head td { border-bottom: 2px solid #111; padding: 5px 8px; vertical-align: middle; }
    .head .logo { width: 50%; text-align: center; border-right: 2px solid #111; }
    .head .company { font-size: 8px; font-weight: bold; letter-spacing: 1px; }
    .head .title { font-size: 15px; font-weight: bold; }
    .head .no { font-size: 16px; font-weight: bold; color: #c00; letter-spacing: 2px; }

    .meta { width: 100%; }
    .meta td { padding: 4px 8px; font-size: 10px; vertical-align: bottom; }
    .lbl { font-weight: bold; white-space: nowrap; }
    .val { border-bottom: 1px solid #111; }
    .box { display: inline-block; width: 11px; height: 11px; border: 1.2px solid #111; text-align: center; line-height: 10px; font-size: 9px; margin: 0 4px 0 0; vertical-align: middle; }
    .ampm { font-size: 7px; line-height: 1.1; }

    table.grid { width: 100%; border-top: 2px solid #111; }
    table.grid th { border-bottom: 1.5px solid #111; padding: 5px; font-size: 10px; font-weight: bold; text-align: center; }
    table.grid td { border-bottom: 1px solid #111; padding: 5px; font-size: 10px; height: 14px; }
    .qtycol { width: 14%; text-align: center; border-right: 1.5px solid #111; }

    .sig { width: 100%; margin-top: 4px; }
    .sig td { width: 50%; padding: 10px 12px 2px; font-size: 10px; vertical-align: bottom; }
    .sig .line { border-bottom: 1px solid #111; min-height: 14px; text-align: center; }
    .sig .role { text-align: center; font-style: italic; font-size: 8px; }

    .note { text-align: center; font-size: 8px; font-weight: bold; font-style: italic; padding: 8px 10px 10px; line-height: 1.5; }

    .footer { margin-top: 6px; width: 100%; }
    .form-no { font-size: 8.5px; }
    .generated { font-size: 7.5px; color: #555; text-align: right; }
</style>
</head>
<body>

<div class="slip">
    <table class="head" width="100%">
        <tr>
            <td class="logo">
                @if ($logoData)<img src="{{ $logoData }}" alt="PhilSouth" height="36"><br>@endif
                <span class="company">PHILSOUTH BUILDERS INC.</span>
            </td>
            <td>
                <div class="title">WITHDRAWAL SLIP</div>
                <div><span style="font-weight:bold">N&ordm;</span> &nbsp; <span class="no">{{ $slip->ws_no }}</span></div>
            </td>
        </tr>
    </table>

    <table class="meta" width="100%">
        <tr>
            <td width="58%"><span class="lbl">Project Code:</span> <span class="val">&nbsp;&nbsp;{{ $slip->project_code }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></td>
            <td><span class="lbl">Date:</span> <span class="val">&nbsp;&nbsp;{{ \Illuminate\Support\Carbon::parse($slip->date)->format('m/d/Y') }}&nbsp;&nbsp;</span></td>
        </tr>
        <tr>
            <td>
                <span class="lbl">Requested by:</span>
                <span class="box">{!! $chk('subcon') !!}</span> Subcon
                &nbsp;&nbsp;&nbsp;
                <span class="box">{!! $chk('group_b') !!}</span> Group B
            </td>
            <td>
                <span class="lbl">Time:</span> <span class="val">&nbsp;&nbsp;{{ $time }}&nbsp;&nbsp;</span>
                <span class="ampm">{{ $ampm ?: 'AM / PM' }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding-left:86px">
                <span class="box">{!! $chk('group_a') !!}</span> Group A
                &nbsp;&nbsp;&nbsp;
                <span class="box">{!! $chk('others') !!}</span> Others: <span class="val">&nbsp;&nbsp;{{ $type === 'others' ? $slip->requested_by_other : '' }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
            </td>
        </tr>
        <tr>
            <td colspan="2"><span class="lbl">Delivered to:</span> <span class="val" style="display:inline-block;width:82%">&nbsp;{{ $slip->delivered_to }}</span></td>
        </tr>
        <tr>
            <td colspan="2" style="padding-bottom:6px"><span class="lbl">Remarks:</span> <span class="val" style="display:inline-block;width:85%">&nbsp;{{ $slip->remarks }}</span></td>
        </tr>
    </table>

    <table class="grid" width="100%">
        <thead>
            <tr>
                <th class="qtycol">QTY</th>
                <th>ITEM DESCRIPTION</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($slip->items as $line)
                <tr>
                    <td class="qtycol">{{ $fmtQty($line->qty) }} {{ $line->variant->uom ?: $line->variant->item->uom }}</td>
                    <td>{{ $line->variant->item->description }}@if($line->variant->label) — {{ $line->variant->label }}@endif</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < max(0, 14 - $slip->items->count()); $i++)
                <tr><td class="qtycol">&nbsp;</td><td></td></tr>
            @endfor
        </tbody>
    </table>

    <table class="sig" width="100%">
        <tr>
            <td>
                <span class="lbl">Prepared by:</span>
                <div class="line">{{ $slip->preparedBy?->name }}&nbsp;</div>
                <div class="role">ICS</div>
            </td>
            <td>
                <span class="lbl">Approved by:</span>
                <div class="line">{{ $slip->approvedBy?->name }}&nbsp;</div>
                <div class="role">Engineer In-charge</div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="lbl">Released by:</span>
                <div class="line">{{ $slip->releasedBy?->name }}&nbsp;</div>
                <div class="role">Signature Over Printed Name</div>
            </td>
            <td>
                <span class="lbl">Received by:</span>
                <div class="line">{{ $slip->received_by }}&nbsp;</div>
                <div class="role">Signature Over Printed Name</div>
            </td>
        </tr>
    </table>

    <div class="note">
        NO RELEASE OF MATERIAL WITHOUT PRIOR APPROVAL OF THE PROJECT<br>
        ENGINEER/SUPERVISOR FOR STRICT COMPLIANCE<br>
        DISTRIBUTION : White: ICD; Yellow: Site
    </div>
</div>

<table class="footer">
    <tr>
        <td class="form-no">F-INV-001<br>Rev. 3 &nbsp; 2/22/2023</td>
    </tr>
</table>

</body>
</html>
