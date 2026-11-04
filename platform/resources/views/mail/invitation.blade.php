<!doctype html>
<html lang="ar" dir="rtl">
<body style="font-family: Tahoma, Arial, sans-serif; color:#1f2933; line-height:1.8">
  <p>مرحبًا {{ $name }}،</p>
  <p>دعاك {{ config('office.name') }} إلى إنشاء حسابك على منصة المكتب.</p>
  <p><a href="{{ $url }}">قبول الدعوة وتعيين كلمة المرور</a></p>
  <p>الرابط صالح لمرة واحدة وينتهي في {{ $expiresAt->setTimezone(config('office.display_timezone'))->format('Y-m-d H:i') }} بتوقيت الرياض.</p>
  <p style="color:#6b7280; font-size: 13px">إذا لم تكن تتوقع هذه الرسالة فتجاهلها.</p>
</body>
</html>
