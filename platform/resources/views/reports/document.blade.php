@php
  // يعزل الأرقام والتواريخ داخل النص العربي حتى لا ينقلب ترتيبها
  $n = fn ($v) => preg_replace('/([0-9A-Z][0-9A-Z\-:\.\/,]*(?: [0-9][0-9\-:\.\/,]*)*)/u', '<span class="num">$1</span>', e((string) $v));
  $statusTone = ['active' => '#1f5f4a', 'completed' => '#384049', 'suspended' => '#8a5a00', 'struck_off' => '#8a5a00', 'draft' => '#6b7280'];
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ $r['title'] }}</title>
<style>
  @if($screen)
  @font-face { font-family: plex; src: url('/fonts/IBMPlexSansArabic-Regular.ttf'); font-weight: 400; }
  @font-face { font-family: plex; src: url('/fonts/IBMPlexSansArabic-Bold.ttf'); font-weight: 700; }
  body { background: #e9ecef; padding: 24px 0; }
  .page { background: #fff; width: 182mm; margin: 0 auto; padding: 14mm; box-shadow: 0 1px 3px rgba(0,0,0,.12); }
  .screen-head { display: flex; gap: 12px; align-items: center; border-bottom: 2px solid #323943; padding-bottom: 10px; margin-bottom: 14px; }
  @endif
  body { font-family: plex, sans-serif; color: #1d232a; font-size: 10.5pt; line-height: 1.6; direction: rtl; }
  h1 { font-size: 16pt; margin: 0 0 2mm; color: #2a3038; }
  h2 { font-size: 12.5pt; margin: 7mm 0 2mm; color: #2a3038; border-bottom: 1px solid #cfd5db; padding-bottom: 1.5mm; }
  h3 { font-size: 11pt; margin: 5mm 0 1.5mm; color: #2a3038; }
  .muted { color: #5b6570; }
  .meta { font-size: 9pt; color: #5b6570; }
  table { width: 100%; border-collapse: collapse; margin: 2mm 0 3mm; }
  th, td { border: 0.6pt solid #cfd5db; padding: 1.8mm 2.2mm; text-align: right; vertical-align: top; }
  th { background: #eef1f4; font-weight: 700; color: #2a3038; }
  table.kv th { width: 34%; background: #f6f7f9; }
  .num { direction: ltr; unicode-bidi: embed; font-feature-settings: "tnum"; }
  .badge { font-size: 8.5pt; padding: 0.4mm 2mm; border: 0.6pt solid #9aa4ad; border-radius: 2mm; }
  .summary { background: #f6f7f9; border-right: 2pt solid #323943; padding: 2.5mm 3mm; margin: 2mm 0; }
  .block { page-break-inside: avoid; }
  .stats td { text-align: center; font-size: 13pt; font-weight: 700; }
  .stats th { text-align: center; font-size: 9pt; }
  .internal { color: #8a1c1c; font-size: 9pt; }
</style>
</head>
<body>
@unless($screen)
<htmlpageheader name="hdr">
  <table style="border:none; margin:0;"><tr>
    <td style="border:none; width:16mm; padding:0;"><img src="{{ $r['logo'] }}" style="height:14mm;"></td>
    <td style="border:none; padding:0 3mm;">
      <div style="font-weight:700; font-size:11pt; color:#2a3038;">{{ config('office.name') }}</div>
      <div class="meta">ترخيص رقم <span class="num">{{ config('office.license_number') }}</span></div>
    </td>
    <td style="border:none; text-align:left; padding:0;" class="meta">{{ $r['title'] }}@if($r['variant']==='internal') — نسخة داخلية @endif</td>
  </tr></table>
  <div style="border-bottom:1.2pt solid #323943; margin-top:1mm;"></div>
</htmlpageheader>
<htmlpagefooter name="ftr">
  <table style="border:none; margin:0;" class="meta"><tr>
    <td style="border:none; padding:0;">تاريخ الإصدار: {!! $n($r['issuedAt']) !!} — الموافق {!! $n($r['issuedHijri']) !!}</td>
    <td style="border:none; padding:0; text-align:left;">صفحة <span class="num">{PAGENO}</span> من <span class="num">{nbpg}</span></td>
  </tr></table>
</htmlpagefooter>
<sethtmlpageheader name="hdr" value="on" show-this-page="1" />
<sethtmlpagefooter name="ftr" value="on" />
@endunless
<div class="page">
@if($screen)
  <div class="screen-head">
    <img src="{{ $r['logo'] }}" style="height:56px">
    <div><div style="font-weight:700">{{ config('office.name') }}</div><div class="meta">ترخيص رقم <span class="num">{{ config('office.license_number') }}</span> — تاريخ الإصدار {!! $n($r['issuedAt']) !!}</div></div>
  </div>
@endif

<h1>{{ $r['title'] }}</h1>
@if($r['variant']==='internal')<div class="internal">نسخة داخلية للمكتب — لا تُسلَّم للعميل</div>@endif

<h2>بيانات العميل</h2>
<table class="kv">
@foreach($r['subject'] as $k => $v)
  <tr><th>{{ $k }}</th><td>{!! $n($v) !!}</td></tr>
@endforeach
</table>

@if($r['type']==='client' && isset($r['counts']))
<h2>ملخص</h2>
<table class="stats">
  <thead><tr><th>إجمالي القضايا</th><th>السارية</th><th>المكتملة</th><th>موقوفة / مشطوبة قابلة للاستكمال</th><th>طلبات التنفيذ</th></tr></thead>
  <tbody><tr><td class="num">{{ $r['counts']['total'] }}</td><td class="num">{{ $r['counts']['active'] }}</td><td class="num">{{ $r['counts']['completed'] }}</td><td class="num">{{ $r['counts']['resumable'] }}</td><td class="num">{{ count($r['executions']) }}</td></tr></tbody>
</table>
@endif

@php $kinds = \App\Models\Collection::KINDS; @endphp
@if(!empty($r['totals']))
<h2>المبالغ</h2>
<table>
  <thead><tr><th>البند</th><th>العملة</th><th>المبلغ</th></tr></thead>
  <tbody>
  @foreach(['claimed','agreed','seized','received'] as $k)
    @foreach(($r['totals'][$k] ?? []) as $cur => $amt)
      <tr><td>{{ $kinds[$k] }}</td><td>{{ $cur }}</td><td class="num">{{ number_format((float)$amt, 2) }}</td></tr>
    @endforeach
  @endforeach
  </tbody>
</table>
@endif

@if(count($r['cases']))
<h2>{{ $r['type']==='case' ? 'بيانات القضية' : 'القضايا' }}</h2>
@if($r['type']==='client')
<table>
  <thead><tr><th>المرجع</th><th>العنوان</th><th>المرحلة</th><th>الحالة</th></tr></thead>
  <tbody>
  @foreach($r['cases'] as $c)
    <tr><td class="num">{{ $c['fields']['المرجع'] }}</td><td>{!! $n($c['title']) !!}</td><td>{{ $c['fields']['المرحلة'] ?? '' }}</td><td>{{ $c['fields']['حالة العمل'] ?? '' }}</td></tr>
  @endforeach
  </tbody>
</table>
@endif
@foreach($r['cases'] as $c)
  <div class="block">
  <h3>{{ $c['title'] }}</h3>
  <table class="kv">
    @foreach($c['fields'] as $k => $v)<tr><th>{{ $k }}</th><td>{!! $n($v) !!}</td></tr>@endforeach
  </table>
  @if(!empty($c['summary']))<div class="summary"><strong>آخر تحديث:</strong> {{ $c['summary'] }}</div>@endif
  </div>
  @if(!empty($c['timeline']))
  <table>
    <thead><tr><th style="width:24mm">التاريخ</th><th>الإجراء</th></tr></thead>
    <tbody>@foreach($c['timeline'] as $e)<tr><td class="num">{{ $e['date'] }}</td><td>{!! $n($e['title']) !!}@if(!empty($e['details']))<div class="muted">{!! $n($e['details']) !!}</div>@endif</td></tr>@endforeach</tbody>
  </table>
  @endif
  @if(!empty($c['hearings']))
  <table><thead><tr><th>الموعد</th><th>النوع</th><th>الحالة</th><th>النتيجة</th></tr></thead>
  <tbody>@foreach($c['hearings'] as $h)<tr><td class="num">{{ $h['date'] }}</td><td>{{ $h['kind'] }}</td><td>{{ $h['status'] }}</td><td>{{ $h['result'] }}</td></tr>@endforeach</tbody></table>
  @endif
  @if(!empty($c['parties']))
  <table><thead><tr><th>الطرف</th><th>الصفة</th><th>الهوية / السجل</th></tr></thead>
  <tbody>@foreach($c['parties'] as $p)<tr><td>{{ $p['name'] }}</td><td>{{ $p['role'] }}</td><td class="num">{{ $p['id'] }}</td></tr>@endforeach</tbody></table>
  @endif
  @if(!empty($c['tasks']))
  <table><thead><tr><th>مهمة مفتوحة</th><th>النوع</th><th>الاستحقاق</th></tr></thead>
  <tbody>@foreach($c['tasks'] as $t)<tr><td>{{ $t['title'] }}</td><td>{{ $t['kind'] }}</td><td class="num">{{ $t['due'] }}</td></tr>@endforeach</tbody></table>
  @endif
  @if(!empty($c['memos']))
  <table><thead><tr><th>المذكرة</th><th>الحالة</th><th>الإيداع</th></tr></thead>
  <tbody>@foreach($c['memos'] as $m)<tr><td>{{ $m['title'] }}</td><td>{{ $m['status'] }}</td><td class="num">{{ $m['filed'] }}</td></tr>@endforeach</tbody></table>
  @endif
  @if(!empty($c['internalNotes']))<div class="summary internal"><strong>ملاحظات داخلية:</strong> {{ $c['internalNotes'] }}</div>@endif
@endforeach
@endif

@if(count($r['executions']))
<h2>طلبات التنفيذ</h2>
@foreach($r['executions'] as $e)
  <div class="block">
  <h3>{{ $e['title'] }}</h3>
  <table class="kv">@foreach($e['fields'] as $k => $v)<tr><th>{{ $k }}</th><td>{!! $n($v) !!}</td></tr>@endforeach</table>
  @if(!empty($e['summary']))<div class="summary"><strong>آخر تحديث:</strong> {{ $e['summary'] }}</div>@endif
  </div>
  @if(!empty($e['timeline']))
  <table><thead><tr><th style="width:24mm">التاريخ</th><th>الإجراء</th></tr></thead>
  <tbody>@foreach($e['timeline'] as $ev)<tr><td class="num">{{ $ev['date'] }}</td><td>{!! $n($ev['title']) !!}</td></tr>@endforeach</tbody></table>
  @endif
  @if(!empty($e['decisions']))
  <table><thead><tr><th>القرار</th><th>الرقم</th><th>التاريخ</th></tr></thead>
  <tbody>@foreach($e['decisions'] as $d)<tr><td>{{ $d['label'] }}</td><td class="num">{{ $d['number'] }}</td><td class="num">{{ $d['date'] }}</td></tr>@endforeach</tbody></table>
  @endif
  @if(!empty($e['actions']))
  <table><thead><tr><th>طلب الإجراء</th><th>التاريخ</th><th>الحالة</th></tr></thead>
  <tbody>@foreach($e['actions'] as $a)<tr><td>{{ $a['type'] }}</td><td class="num">{{ $a['date'] }}</td><td>{{ $a['status'] }}</td></tr>@endforeach</tbody></table>
  @endif
@endforeach
@endif

<p class="meta" style="margin-top:8mm">صدر هذا التقرير من {{ config('office.name') }} — ترخيص رقم <span class="num">{{ config('office.license_number') }}</span>. المعلومات وفق آخر تحديث مسجّل لدى المكتب.</p>
</div>
</body>
</html>
