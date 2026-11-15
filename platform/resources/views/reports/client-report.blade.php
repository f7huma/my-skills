@php
  // يعزل الأرقام والتواريخ داخل النص العربي حتى لا ينقلب ترتيبها
  $n = fn ($v) => preg_replace('/([0-9A-Z][0-9A-Z\-:\.\/,]*(?: [0-9][0-9\-:\.\/,]*)*)/u', '<span class="num">$1</span>', e((string) $v));
  $amounts = fn ($list) => collect($list)->map(fn ($a) => '<span class="num">'.number_format((float) $a['amount'], 2).'</span> '.($a['currency'] === 'SAR' ? 'ريال' : e($a['currency'])))->implode(' + ');
  $stages = ['intake', 'reconciliation', 'filing', 'court', 'judgment', 'objection', 'execution', 'closed'];
  $stageShort = ['intake' => 'الإعداد', 'reconciliation' => 'الصلح', 'filing' => 'القيد', 'court' => 'المحكمة', 'judgment' => 'الحكم', 'objection' => 'الاعتراض', 'execution' => 'التنفيذ', 'closed' => 'منتهية'];
  $collected = $r['stats']['collected'] ?? [];
  $ink = '#303741'; $mist = '#e8ebec'; $off = '#f6f7f6'; $line = '#dde1e3'; $muted = '#6a737d'; $text = '#262c33';
  $cases = collect($r['matters'])->where('kind', 'case'); $execs = collect($r['matters'])->where('kind', 'execution');
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ $r['title'] }}</title>
<style>
  body { font-family: plex, sans-serif; color: {{ $text }}; font-size: 9.6pt; line-height: 1.6; direction: rtl; }
  .num { direction: ltr; unicode-bidi: embed; }
  .muted { color: {{ $muted }}; }
  table { border-collapse: collapse; width: 100%; }
  td { vertical-align: top; text-align: right; }
  h2 { font-size: 10.5pt; font-weight: 700; color: {{ $ink }}; margin: 6mm 0 2.4mm; padding-bottom: 1.2mm; border-bottom: 0.5pt solid {{ $line }}; }
  .note { background: {{ $off }}; padding: 2.6mm 3.6mm; font-size: 9.4pt; line-height: 1.75; }
  .matter { margin: 0 0 4.2mm; page-break-inside: avoid; }
  .items td { padding: 1.25mm 0; border-bottom: 0.4pt solid #eef0f1; }
  .items tr:last-child td { border-bottom: none; }
  .items .a { color: {{ $text }}; }
  .items .d { font-size: 8.4pt; color: {{ $muted }}; line-height: 1.5; }
  .stage td { padding: 0 0.5mm; text-align: center; font-size: 6.6pt; color: #9aa2ab; }
</style>
</head>
<body>
<htmlpageheader name="hdr">
  <table><tr>
    <td style="width:10mm; vertical-align:middle;"><img src="{{ $r['logo'] }}" style="height:8.5mm;"></td>
    <td style="padding:0 2.5mm; vertical-align:middle;">
      <div style="font-weight:700; font-size:8.8pt; color:{{ $ink }};">{{ config('office.name') }}</div>
      <div style="font-size:7pt;" class="muted">ترخيص رقم <span class="num">{{ config('office.license_number') }}</span></div>
    </td>
    <td style="text-align:left; vertical-align:middle; font-size:7.6pt;" class="muted">{{ $r['periodLabel'] === 'لفترة محددة' ? 'تقرير' : 'تقرير '.$r['periodLabel'] }} · <span class="num">{{ $r['start'] }}</span> — <span class="num">{{ $r['end'] }}</span></td>
  </tr></table>
  <div style="border-bottom:0.6pt solid {{ $ink }}; margin-top:1.2mm;"></div>
</htmlpageheader>
<htmlpagefooter name="ftr">
  <table style="font-size:7.2pt;" class="muted"><tr>
    <td>صدر في <span class="num">{{ $r['issuedAt'] }}</span> · سري، معدّ للعميل فقط</td>
    <td style="text-align:left;"><span class="num">{PAGENO}</span> / <span class="num">{nbpg}</span></td>
  </tr></table>
</htmlpagefooter>
<sethtmlpageheader name="hdr" value="on" show-this-page="1" />
<sethtmlpagefooter name="ftr" value="on" />

{{-- الواجهة: العنوان والفترة، والعميل بشعاره --}}
<table style="background:{{ $mist }};"><tr>
  <td style="padding:5mm 6mm 4.4mm; vertical-align:middle;">
    <div style="font-size:8pt;" class="muted">{{ $r['periodLabel'] === 'لفترة محددة' ? 'تقرير سير الأعمال' : 'تقرير '.$r['periodLabel'].' عن سير الأعمال' }}</div>
    <div style="font-size:15pt; font-weight:700; color:{{ $ink }}; line-height:1.35; margin:0.6mm 0 1mm;">{{ $r['title'] }}</div>
    <div style="font-size:8.8pt; color:#3d4650;">الفترة من <span class="num">{{ $r['start'] }}</span> إلى <span class="num">{{ $r['end'] }}</span></div>
  </td>
  <td style="width:52mm; padding:4mm 6mm 4mm 5mm; text-align:left; vertical-align:middle;">
    @if(!empty($r['clientLogo']))<img src="{{ $r['clientLogo'] }}" style="max-height:15mm; max-width:40mm;"><br>@endif
    <span style="font-size:9.6pt; font-weight:700; color:{{ $ink }};">{{ $r['client']['name'] }}</span>
  </td>
</tr></table>

{{-- الأرقام --}}
<table style="margin-top:2.6mm; border-collapse:separate; border-spacing:1.6mm 0;"><tr>
  @foreach([['قضايا', $r['stats']['cases']], ['طلبات تنفيذ', $r['stats']['executions']], ['أعمال منجزة', $r['stats']['actions']], ['جلسات', $r['stats']['hearings']]] as [$k, $v])
    <td style="border:0.5pt solid {{ $line }}; padding:2mm 3mm; vertical-align:middle;">
      <span style="font-size:12.5pt; font-weight:700; color:{{ $ink }};" class="num">{{ $v }}</span>&nbsp; <span style="font-size:8pt;" class="muted">{{ $k }}</span>
    </td>
  @endforeach
  @if(count($collected))
    <td style="border:0.5pt solid #b9dccb; background:#f1f8f4; padding:2mm 3mm; vertical-align:middle;">
      <div style="font-size:7.4pt;" class="muted">المبالغ المحصَّلة</div>
      <div style="font-size:10pt; font-weight:700; color:#136a43;">{!! $amounts($collected) !!}</div>
    </td>
  @endif
</tr></table>

@if($r['intro'])
<h2>ملخص الفترة</h2>
<div class="note">{!! nl2br(e($r['intro'])) !!}</div>
@endif

@foreach([['القضايا', $cases], ['طلبات التنفيذ', $execs]] as [$heading, $list])
  @if($list->count())
  <h2>{{ $heading }}</h2>
  @foreach($list as $m)
  <div class="matter">
    <table><tr>
      <td style="background:{{ $ink }}; padding:1.8mm 3.4mm; vertical-align:middle;">
        <span style="color:#ffffff; font-size:9.6pt; font-weight:700;">{{ $m['title'] }}</span>
        <span style="color:#c9cfd5; font-size:7.6pt;">&nbsp;&nbsp;@if($m['number']){{ $m['kind'] === 'case' ? 'قضية' : 'طلب' }} {!! $n($m['number']) !!}@endif @if($m['court']) · {{ $m['court'] }}@endif</span>
      </td>
      <td style="background:{{ $ink }}; padding:1.8mm 3.4mm; width:30mm; text-align:left; vertical-align:middle; color:#ffffff; font-size:7.6pt;">{{ $m['status'] }}</td>
    </tr></table>
    <div style="border:0.5pt solid {{ $line }}; border-top:none; padding:1.6mm 3.4mm 1.8mm;">
      @if($m['kind'] === 'case' && $m['stage'])
        @php $idx = array_search($m['stage'], $stages); @endphp
        <table class="stage" style="margin:0.6mm 0 1.4mm;"><tr>
          @foreach($stages as $i => $s)
            <td><table><tr><td style="height:1.2mm; background: {{ $i < $idx ? '#a3acb5' : ($i === $idx ? $ink : $mist) }};"></td></tr></table>
              <div style="margin-top:0.5mm; {{ $i === $idx ? 'color:'.$ink.'; font-weight:700;' : '' }}">{{ $stageShort[$s] }}</div></td>
          @endforeach
        </tr></table>
      @endif
      <table class="items">
        @foreach($m['items'] as $it)
        <tr>
          <td style="width:4mm;"><table style="width:1.5mm; margin:1.9mm 0 0 auto;"><tr><td style="height:1.5mm; background:{{ $ink }}; padding:0; border:none;"></td></tr></table></td>
          <td><div class="a">{!! $n($it['title']) !!}</div>@if($it['details'])<div class="d">{!! $n($it['details']) !!}</div>@endif</td>
        </tr>
        @endforeach
      </table>
      @if(count($m['collected'] ?? []))
        <div style="margin-top:1mm; color:#136a43; font-size:8.4pt;">المحصَّل خلال الفترة: <b>{!! $amounts($m['collected']) !!}</b></div>
      @endif
    </div>
  </div>
  @endforeach
  @endif
@endforeach

@if(count($r['general']))
<h2>أعمال أخرى</h2>
<table class="items">
  @foreach($r['general'] as $it)
  <tr>
    <td style="width:4mm;"><table style="width:1.5mm; margin:1.9mm 0 0 auto;"><tr><td style="height:1.5mm; background:{{ $ink }}; padding:0; border:none;"></td></tr></table></td>
    <td><div class="a">{!! $n($it['title']) !!}</div>@if($it['details'])<div class="d">{!! $n($it['details']) !!}</div>@endif</td>
  </tr>
  @endforeach
</table>
@endif

@if($r['nextSteps'])
<h2>الخطوات القادمة</h2>
<div class="note">{!! nl2br(e($r['nextSteps'])) !!}</div>
@endif
</body>
</html>
