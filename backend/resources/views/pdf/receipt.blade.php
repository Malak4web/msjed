@php
    use App\Support\Money;
    $thermal = $format === 'thermal';
@endphp
<html dir="rtl" lang="ar">
<head>
<style>
    body { font-family: xbriyaz; color: #1E2922; font-size: {{ $thermal ? '9pt' : '11pt' }}; }
    .head { text-align: center; border-bottom: 2px solid #1F6F50; padding-bottom: 6px; margin-bottom: 8px; }
    .mosque { font-size: {{ $thermal ? '12pt' : '16pt' }}; font-weight: bold; color: #1F6F50; }
    .muted { color: #4B5563; font-size: {{ $thermal ? '8pt' : '9pt' }}; }
    .title { text-align: center; font-size: {{ $thermal ? '11pt' : '14pt' }}; font-weight: bold; margin: 6px 0; }
    .gold { color: #755b00; }
    table.info { width: 100%; border-collapse: collapse; }
    table.info td { padding: 5px 4px; border-bottom: 1px solid #EAEBE4; vertical-align: top; }
    table.info td.k { color: #4B5563; width: 32%; }
    table.info td.v { font-weight: bold; }
    .amount { background: #F6F8F6; border: 1px solid #C9A227; text-align: center; padding: 8px; margin: 8px 0; }
    .amount .n { font-size: {{ $thermal ? '16pt' : '22pt' }}; font-weight: bold; color: #1F6F50; }
    .words { font-size: {{ $thermal ? '8.5pt' : '10pt' }}; }
    .reversed { color: #DC2626; font-weight: bold; text-align: center; border: 2px solid #DC2626; padding: 4px; margin: 6px 0; }
    .foot { margin-top: 10px; }
    .sign td { width: 50%; text-align: center; padding-top: 18px; }
</style>
</head>
<body>
<div class="head">
    @if ($logo)
        <img src="{{ $logo }}" style="height: {{ $thermal ? '12mm' : '18mm' }};" /><br>
    @endif
    <div class="mosque">{{ $settings['mosque_name'] }}</div>
    @if ($settings['mosque_address'])<div class="muted">{{ $settings['mosque_address'] }}</div>@endif
    @if ($settings['mosque_phone'])<div class="muted">ت: {{ $settings['mosque_phone'] }}</div>@endif
</div>

<div class="title">إيصال استلام نقدية</div>

@if ($income->isReversed())
    <div class="reversed">ملغى بقيد عكسي — {{ $income->reversal_reason }}</div>
@endif

<table class="info">
    <tr><td class="k">رقم الإيصال</td><td class="v gold">{{ $income->receipt_no }}</td></tr>
    <tr><td class="k">التاريخ</td><td class="v">{{ $income->date->format('Y/m/d') }}</td></tr>
    @if ($income->payer_name)
        <tr><td class="k">استلمنا من</td><td class="v">{{ $income->payer_name }}</td></tr>
    @endif
    <tr><td class="k">المصدر</td><td class="v">{{ $income->source->name }}</td></tr>
    <tr><td class="k">طريقة الدفع</td><td class="v">{{ $income->paymentMethod->name }}</td></tr>
    <tr><td class="k">المستلم (العهدة)</td><td class="v">{{ $income->custodian->name }}</td></tr>
    @if ($income->notes)
        <tr><td class="k">ملاحظات</td><td>{{ $income->notes }}</td></tr>
    @endif
</table>

<div class="amount">
    <div class="n">{{ Money::format($income->amount) }}</div>
    <div class="words">{{ $words }}</div>
</div>

<table width="100%" class="foot">
    <tr>
        <td style="vertical-align: middle;">
            <div class="muted">سجّله: {{ $income->creator->name }}</div>
            <div class="muted">جزاكم الله خيرًا وتقبّل منكم</div>
        </td>
        <td style="text-align: left; width: {{ $thermal ? '22mm' : '28mm' }};">
            <barcode code="{{ $income->receipt_no }}" type="QR" size="{{ $thermal ? '0.7' : '0.9' }}" error="M" disableborder="1" />
        </td>
    </tr>
</table>

@unless ($thermal)
<table width="100%" class="sign">
    <tr><td>توقيع المستلم: ..................</td><td>ختم المسجد</td></tr>
</table>
@endunless
</body>
</html>
