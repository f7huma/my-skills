<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<title>{{ $memo->title }}</title>
<style>
  @font-face { font-family: plex; src: url('/fonts/IBMPlexSansArabic-Regular.ttf'); }
  @font-face { font-family: plex; src: url('/fonts/IBMPlexSansArabic-Bold.ttf'); font-weight: 700; }
  @page { size: A4; margin: 22mm 18mm; }
  body { font-family: plex, serif; color: #111; line-height: 2; font-size: 13pt; max-width: 175mm; margin: 0 auto; }
  header { border-bottom: 1.5pt solid #323943; padding-bottom: 8px; margin-bottom: 18px; display: flex; justify-content: space-between; font-size: 10pt; color: #444; }
  h1 { text-align: center; font-size: 17pt; }
  table { border-collapse: collapse; width: 100%; } td, th { border: 1px solid #999; padding: 4px 8px; }
  .noprint { margin: 12px 0; } @media print { .noprint { display: none; } }
</style>
</head>
<body>
<div class="noprint"><button id="print-btn" type="button">طباعة</button></div>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">document.getElementById('print-btn').addEventListener('click', () => window.print());</script>
<header><span>{{ $memo->legalCase->reference }} — {{ $memo->legalCase->client->name }}</span><span>{{ $typeLabel }} — إصدار {{ $memo->current_version }}</span></header>
<h1>{{ $memo->title }}</h1>
{!! $body !!}
</body>
</html>
