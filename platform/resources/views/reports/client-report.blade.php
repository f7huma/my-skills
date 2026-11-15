@php
  // يعزل الأرقام والتواريخ داخل النص العربي حتى لا ينقلب ترتيبها
  $n = fn ($v) => preg_replace('/([0-9A-Z][0-9A-Z\-:\.\/,]*(?: [0-9][0-9\-:\.\/,]*)*)/u', '<span class="num">$1</span>', e((string) $v));
  $stages = ['intake', 'reconciliation', 'filing', 'court', 'judgment', 'objection', 'execution', 'closed'];
  $stageShort = ['intake' => 'الإعداد', 'reconciliation' => 'الصلح', 'filing' => 'القيد', 'court' => 'المحكمة', 'judgment' => 'الحكم', 'objection' => 'الاعتراض', 'execution' => 'التنفيذ', 'closed' => 'منتهية'];
  $visible = collect($r['matters'])->filter(fn ($m) => count($m['items']) || true);
  $amounts = fn ($list) => collect($list)->map(fn ($a) => '<span class="num">'.number_format((float) $a['amount'], 2).'</span> '.($a['currency'] === 'SAR' ? 'ريال' : e($a['currency'])))->implode(' + ');
  $collected = $r['stats']['collected'] ?? [];
  $ink = '#303741'; $mist = '#e1e5e6'; $off = '#f4f5f4'; $line = '#d5dadc'; $muted = '#5b6570';
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ $r['title'] }}</title>
<style>
  body { font-family: plex, sans-serif; color: #1d232a; font-size: 10.5pt; line-height: 1.65; direction: rtl; }
  .num { direction: ltr; unicode-bidi: embed; }
  .muted { color: {{ $muted }}; }
  .small { font-size: 9pt; }
  table { border-collapse: collapse; width: 100%; }
  td, th { vertical-align: top; text-align: right; }
  /* الغلاف */
  .cover { background: {{ $mist }}; border-radius: 4mm; padding: 7mm 8mm 6mm; }
  .eyebrow { font-size: 9.5pt; color: {{ $muted }}; letter-spacing: 0; }
  .title { font-size: 21pt; font-weight: 700; color: {{ $ink }}; line-height: 1.3; margin: 1mm 0 1.5mm; }
  .period { font-size: 10.5pt; color: #2a3038; }
  .client-box { background: #ffffff; border-radius: 3mm; padding: 3mm 4mm; }
  .client-box .label { font-size: 8.5pt; color: {{ $muted }}; }
  .client-box .name { font-size: 12.5pt; font-weight: 700; color: {{ $ink }}; }
  /* الأرقام */
  .stats td { background: #ffffff; border: 0.6pt solid {{ $line }}; border-radius: 2.5mm; padding: 3mm 3.5mm; width: 25%; }
  .stats .v { font-size: 20pt; font-weight: 700; color: {{ $ink }}; line-height: 1.1; }
  .stats .k { font-size: 8.5pt; color: {{ $muted }}; }
  /* الأقسام */
  h2 { font-size: 13pt; font-weight: 700; color: {{ $ink }}; margin: 7mm 0 2.5mm; padding-bottom: 1.5mm; border-bottom: 1.2pt solid {{ $ink }}; }
  .note { background: {{ $off }}; border-right: 2.4pt solid {{ $ink }}; padding: 3mm 4mm; border-radius: 1.5mm; white-space: pre-line; }
  .matter { margin: 0 0 6mm; page-break-inside: avoid; }
  .mhead { background: {{ $ink }}; color: #ffffff; border-radius: 2.5mm 2.5mm 0 0; padding: 2.6mm 4mm; }
  .mhead .t { font-size: 11.5pt; font-weight: 700; }
  .mhead .s { font-size: 8.8pt; color: #d6dbdf; }
  .mbody { border: 0.6pt solid {{ $line }}; border-top: none; border-radius: 0 0 2.5mm 2.5mm; padding: 2.5mm 4mm 3mm; }
  .stagebar td { padding: 0 0.6mm; text-align: center; font-size: 7.5pt; color: {{ $muted }}; }
  .dot { height: 2.2mm; border-radius: 1.1mm; }
  .tl td { padding: 2mm 0; border-bottom: 0.4pt solid #e8ebec; }
  .tl tr:last-child td { border-bottom: none; }
  .tl .date { width: 40mm; padding-left: 3mm; }
  .tl .date .g { font-weight: 700; color: {{ $ink }}; font-size: 9.5pt; }
  .tl .date .h { font-size: 8.3pt; color: {{ $muted }}; }
  .tl .mark { width: 5mm; text-align: center; color: {{ $ink }}; font-size: 8pt; }
  .tl .what .a { font-weight: 700; color: #1d232a; }
  .tl .what .d { font-size: 9.3pt; color: #3d4650; }
  .empty { color: {{ $muted }}; font-size: 9.5pt; padding: 2mm 0; }
  .pill { font-size: 8.5pt; padding: 0.6mm 2.5mm; border-radius: 2mm; background: #ffffff; color: {{ $ink }}; }
  .sign { margin-top: 9mm; border-top: 0.6pt solid {{ $line }}; padding-top: 4mm; }
</style>
</head>
<body>
<htmlpageheader name="hdr">
  <table><tr>
    <td style="width:14mm;"><img src="{{ $r['logo'] }}" style="height:12mm;"></td>
    <td style="padding:0 3mm;">
      <div style="font-weight:700; font-size:10.5pt; color:{{ $ink }};">{{ config('office.name') }}</div>
      <div class="small muted">ترخيص رقم <span class="num">{{ config('office.license_number') }}</span></div>
    </td>
    <td style="text-align:left;" class="small muted">{{ $r['client']['name'] }}<br>{{ $r['periodLabel'] }} · {!! $n($r['start']) !!} — {!! $n($r['end']) !!}</td>
  </tr></table>
  <div style="border-bottom:1.2pt solid {{ $ink }}; margin-top:1.5mm;"></div>
</htmlpageheader>
<htmlpagefooter name="ftr">
  <table class="small muted"><tr>
    <td>صدر في {!! $n($r['issuedAt']) !!} — الموافق {!! $n($r['issuedHijri']) !!} · سري، معدّ للعميل فقط</td>
    <td style="text-align:left;">صفحة <span class="num">{PAGENO}</span> من <span class="num">{nbpg}</span></td>
  </tr></table>
</htmlpagefooter>
<sethtmlpageheader name="hdr" value="on" show-this-page="1" />
<sethtmlpagefooter name="ftr" value="on" />

{{-- الغلاف --}}
<div class="cover">
  <table><tr>
    <td>
      <div class="eyebrow">تقرير {{ $r['periodLabel'] }} عن سير الأعمال</div>
      <div class="title">{{ $r['title'] }}</div>
      <div class="period">الفترة من {!! $n($r['start']) !!} إلى {!! $n($r['end']) !!}</div>
      <div class="small muted">الموافق {!! $n($r['startHijri']) !!} — {!! $n($r['endHijri']) !!}</div>
    </td>
    <td style="width:64mm; background:#ffffff; border:0.6pt solid {{ $line }}; padding:3mm 4mm;">
      <div class="small muted">مقدَّم إلى</div>
      <div style="font-size:12.5pt; font-weight:700; color:{{ $ink }};">{{ $r['client']['name'] }}</div>
      <div class="small muted" style="margin-top:1.5mm;">من</div>
      <div style="font-size:9.5pt; font-weight:700; color:{{ $ink }};">{{ config('office.name') }}</div>
    </td>
  </tr></table>
  <table class="stats" style="margin-top:5mm; border-spacing:2mm; border-collapse:separate;"><tr>
    <td><div class="v num">{{ $r['stats']['cases'] }}</div><div class="k">قضايا مشمولة</div></td>
    <td><div class="v num">{{ $r['stats']['executions'] }}</div><div class="k">طلبات تنفيذ</div></td>
    <td><div class="v num">{{ $r['stats']['actions'] }}</div><div class="k">أعمال خلال الفترة</div></td>
    <td><div class="v num">{{ $r['stats']['hearings'] }}</div><div class="k">جلسات</div></td>
  </tr></table>
  @if(count($collected))
  <table style="margin-top:2mm;"><tr><td style="background:#ffffff; border:0.6pt solid #b9dccb; padding:3mm 4mm;">
    <span class="small muted">المبالغ المحصَّلة خلال الفترة</span>&nbsp;&nbsp;
    <span style="font-size:14pt; font-weight:700; color:#136a43;">{!! $amounts($collected) !!}</span>
  </td></tr></table>
  @endif
</div>

@if($r['intro'])
<h2>ملخص الفترة</h2>
<div class="note">{!! nl2br(e($r['intro'])) !!}</div>
@endif

@php $cases = collect($r['matters'])->where('kind', 'case'); $execs = collect($r['matters'])->where('kind', 'execution'); @endphp
@foreach([['القضايا', $cases], ['طلبات التنفيذ', $execs]] as [$heading, $list])
  @if($list->count())
  <h2>{{ $heading }}</h2>
  @foreach($list as $m)
  <div class="matter">
    <table><tr>
      <td style="background:{{ $ink }}; color:#ffffff; padding:2.6mm 4mm;">
        <div style="color:#ffffff; font-size:11.5pt; font-weight:700;">{{ $m['title'] }}</div>
        <div style="color:#d6dbdf; font-size:8.8pt;">@if($m['number'])رقم {{ $m['kind'] === 'case' ? 'القضية' : 'الطلب' }} {!! $n($m['number']) !!} · @endif @if($m['court']){{ $m['court'] }} · @endif{{ $m['stageLabel'] ?? $m['status'] }}</div>
      </td>
      <td style="background:{{ $ink }}; padding:2.6mm 4mm; width:34mm; text-align:left; vertical-align:middle;">
        <span style="background:#ffffff; color:{{ $ink }}; font-size:8.5pt; padding:0.6mm 2.5mm;">&nbsp;{{ $m['status'] }}&nbsp;</span>
      </td>
    </tr></table>
    <div class="mbody" style="border:0.6pt solid {{ $line }}; border-top:none; padding:2.5mm 4mm 3mm;">
      @if($m['kind'] === 'case' && $m['stage'])
        @php $idx = array_search($m['stage'], $stages); @endphp
        <table class="stagebar" style="margin:1mm 0 2.5mm;"><tr>
          @foreach($stages as $i => $s)
            <td><table><tr><td style="height:2.2mm; background: {{ $i < $idx ? '#8b95a1' : ($i === $idx ? $ink : $mist) }};"></td></tr></table>
              <div style="margin-top:0.8mm; {{ $i === $idx ? 'color:'.$ink.'; font-weight:700;' : '' }}">{{ $stageShort[$s] }}</div></td>
          @endforeach
        </tr></table>
      @endif
      <table class="tl">
          @foreach($m['items'] as $it)
          <tr>
            <td class="mark"><table style="width:2mm; margin:1.6mm auto 0;"><tr><td style="height:2mm; background:{{ $ink }};"></td></tr></table></td>
            <td class="what"><div class="a">{!! $n($it['title']) !!}</div>@if($it['details'])<div class="d">{!! $n($it['details']) !!}</div>@endif</td>
          </tr>
          @endforeach
        </table>
      @if(count($m['collected'] ?? []))
        <div style="margin-top:2mm; color:#136a43; font-size:9.5pt;">المحصَّل خلال الفترة: <b>{!! $amounts($m['collected']) !!}</b></div>
      @endif
    </div>
  </div>
  @endforeach
  @endif
@endforeach

@if(count($r['general']))
<h2>أعمال أخرى لصالحكم</h2>
<table class="tl">
  @foreach($r['general'] as $it)
  <tr>
    <td class="mark"><table style="width:2mm; margin:1.6mm auto 0;"><tr><td style="height:2mm; background:{{ $ink }};"></td></tr></table></td>
    <td class="what"><div class="a">{!! $n($it['title']) !!}</div>@if($it['details'])<div class="d">{!! $n($it['details']) !!}</div>@endif</td>
  </tr>
  @endforeach
</table>
@endif

@if($r['nextSteps'])
<h2>الخطوات القادمة</h2>
<div class="note">{!! nl2br(e($r['nextSteps'])) !!}</div>
@endif

<div class="sign">
  <table><tr>
    <td>
      <div style="font-weight:700; color:{{ $ink }};">{{ config('office.name') }}</div>
      <div class="small muted">نسعد بالرد على استفساراتكم حول أي إجراء ورد في هذا التقرير.</div>
    </td>
    <td style="text-align:left; width:20mm;"><img src="{{ $r['logo'] }}" style="height:16mm; opacity:0.85;"></td>
  </tr></table>
</div>
</body>
</html>
