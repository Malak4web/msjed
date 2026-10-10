@php
    use App\Support\Money;
    $fmt = fn ($v, $type) => match ($type ?? 'text') {
        'money' => $v === null || $v === '' ? '' : Money::format($v, false),
        'number' => $v === null ? '' : number_format((float) $v),
        default => $v,
    };
@endphp
<html dir="rtl" lang="ar">
<head>
<style>
    body { font-family: xbriyaz; color: #1E2922; font-size: 10pt; }
    .head { border-bottom: 2px solid #1F6F50; padding-bottom: 6px; margin-bottom: 10px; }
    .mosque { font-size: 15pt; font-weight: bold; color: #1F6F50; }
    .title { font-size: 13pt; font-weight: bold; margin-top: 2px; }
    .muted { color: #4B5563; font-size: 9pt; }
    table.summary { width: 100%; margin-bottom: 10px; border-collapse: separate; border-spacing: 4px; }
    table.summary td { background: #F6F8F6; border: 1px solid #EAEBE4; padding: 6px; text-align: center; }
    table.summary .v { font-size: 13pt; font-weight: bold; color: #1F6F50; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #1F6F50; color: #fff; padding: 6px 5px; font-weight: bold; text-align: right; }
    table.data td { padding: 5px; border-bottom: 1px solid #EAEBE4; vertical-align: top; }
    table.data tr:nth-child(even) td { background: #FAF9F5; }
    table.data td.num, table.data th.num { text-align: left; }
    table.data tr.total td { background: #EAF7EC; font-weight: bold; border-top: 2px solid #1F6F50; }
    .in { color: #15803D; }
    .out { color: #B91C1C; }
    .struck { color: #9CA3AF; text-decoration: line-through; }
</style>
</head>
<body>
<htmlpagefooter name="footer">
    <table width="100%" class="muted"><tr>
        <td>طُبع بواسطة {{ $printedBy }} — {{ now()->format('Y/m/d H:i') }}</td>
        <td style="text-align: left;">صفحة {PAGENO} من {nbpg}</td>
    </tr></table>
</htmlpagefooter>
<sethtmlpagefooter name="footer" value="on" />

<div class="head">
    <div class="mosque">{{ $mosqueName }}</div>
    <div class="title">{{ $table['title'] }}</div>
    @if (! empty($table['subtitle']))<div class="muted">{{ $table['subtitle'] }}</div>@endif
</div>

@if (! empty($table['summary']))
<table class="summary"><tr>
    @foreach ($table['summary'] as $s)
        <td><div class="muted">{{ $s['label'] }}</div><div class="v">{{ ($s['type'] ?? 'money') === 'money' ? Money::format($s['value']) : $s['value'] }}</div></td>
    @endforeach
</tr></table>
@endif

<table class="data">
    <thead><tr>
        @foreach ($table['columns'] as $c)
            <th class="{{ in_array($c['type'] ?? '', ['money', 'number']) ? 'num' : '' }}">{{ $c['label'] }}</th>
        @endforeach
    </tr></thead>
    <tbody>
        @forelse ($table['rows'] as $row)
            <tr>
                @foreach ($table['columns'] as $c)
                    <td class="{{ in_array($c['type'] ?? '', ['money', 'number']) ? 'num' : '' }} {{ $c['class'] ?? '' }} {{ ! empty($row['_struck']) ? 'struck' : '' }}">{{ $fmt($row[$c['key']] ?? null, $c['type'] ?? 'text') }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($table['columns']) }}" style="text-align: center; padding: 16px;" class="muted">مفيش بيانات في الفترة دي</td></tr>
        @endforelse
        @if (! empty($table['totals']))
            <tr class="total">
                @foreach ($table['columns'] as $i => $c)
                    <td class="{{ in_array($c['type'] ?? '', ['money', 'number']) ? 'num' : '' }}">{{ $i === 0 ? 'الإجمالي' : (array_key_exists($c['key'], $table['totals']) ? $fmt($table['totals'][$c['key']], $c['type'] ?? 'text') : '') }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>
</body>
</html>
