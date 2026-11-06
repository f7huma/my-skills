/*
 * اختبارات المسارات الكاملة في المتصفح (Playwright) على بيانات اصطناعية.
 * التشغيل: php artisan migrate:fresh --seed && php artisan serve & php artisan queue:work &
 *          BASE_URL=http://127.0.0.1:8000 node tests/e2e/flows.cjs
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const PASS = 'Demo-Pass-2026';
const FX = (n) => path.join(__dirname, 'fixtures', n);
const OUT = process.env.E2E_OUT || path.join(__dirname, '../../storage/app/e2e');
fs.mkdirSync(OUT, { recursive: true });

const results = [];
const pagesForShots = [];
let stepNo = 0;
async function step(name, fn) {
  stepNo++;
  const t = Date.now();
  try { await fn(); results.push({ name, ok: true, ms: Date.now() - t }); console.log('✓', name); }
  catch (e) {
    results.push({ name, ok: false, error: String(e.message || e).slice(0, 300) }); console.log('✗', name, '\n   ', String(e.message || e).split('\n')[0]);
    for (const [i, pg] of pagesForShots.entries()) await pg.screenshot({ path: `${OUT}/fail-${stepNo}-${i}.png` }).catch(() => {});
  }
}
const assert = (c, m) => { if (!c) throw new Error('assert: ' + m); };

async function login(browser, email, viewport = { width: 1440, height: 900 }) {
  const ctx = await browser.newContext({ viewport, acceptDownloads: true, locale: 'ar-SA', timezoneId: 'Asia/Riyadh' });
  const page = await ctx.newPage();
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(e.message));
  await page.goto(BASE + '/login');
  await page.getByLabel('البريد الإلكتروني').fill(email);
  await page.getByLabel('كلمة المرور').fill(PASS);
  await page.getByRole('button', { name: 'دخول', exact: true }).click();
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
  await page.waitForLoadState('networkidle');
  return page;
}
/** يختار من القائمة التي تحتوي خيارًا يطابق النص */
async function pickAny(page, re) {
  const sel = page.locator('select').filter({ has: page.locator('option', { hasText: re }) }).first();
  return pick(sel, re);
}
async function pick(locator, re) {
  const opts = await locator.locator('option').evaluateAll((os) => os.map((o) => ({ v: o.value, t: o.textContent })));
  const o = opts.find((x) => re.test(x.t));
  if (!o) throw new Error('option not found ' + re);
  await locator.selectOption(o.v);
}
const go = async (p, url) => { await p.goto(BASE + url); await p.waitForLoadState('networkidle'); };
const settle = (p) => p.waitForLoadState('networkidle');
/** ينفذ إجراءً ينتج طلب كتابة (POST/PUT/DELETE) وينتظر اكتماله وإعادة العرض */
async function act(p, fn) {
  const done = p.waitForResponse((r) => r.request().method() !== 'GET' && r.url().startsWith(BASE), { timeout: 30000 });
  await fn();
  await done;
  await p.waitForLoadState('networkidle');
  await p.waitForTimeout(300);
}
const dialog = (p) => p.locator('dialog[open]');
/** حقل التاريخ في المنصة: يوم / شهر / سنة (ميلادي افتراضيًا) */
async function fillDate(loc, iso) {
  const [y, m, d] = iso.split('-');
  const g = loc.locator('xpath=..');
  await g.locator('select').nth(0).selectOption(String(Number(d)));
  await g.locator('select').nth(1).selectOption(String(Number(m)));
  await g.locator('input').fill(y);
}
function localDate(offset) {
  const d = new Date(Date.now() + offset * 86400000);
  return d.toLocaleDateString('en-CA', { timeZone: 'Asia/Riyadh' });
}
function nextWorkday(offset) {
  let d = offset;
  for (;;) { const s = localDate(d); const wd = new Date(s + 'T12:00:00Z').getUTCDay(); if (wd !== 5 && wd !== 6) return s; d++; }
}

(async () => {
  const browser = await chromium.launch();
  const lawyer = await login(browser, 'lawyer@example.test');
  const client = await login(browser, 'cfo@example.test');
  pagesForShots.push(lawyer, client);
  let claimId; let caseId;

  await step('مطالبة واردة مع مرفقات: مسودة ثم إرسال (مع رفض ملف غير مسموح)', async () => {
    await go(client, '/portal/claims');
    await client.getByRole('button', { name: 'مطالبة جديدة', exact: true }).click();
    await client.waitForURL(/\/portal\/claims\/\d+\/edit/);
    claimId = client.url().match(/claims\/(\d+)/)[1];
    await client.getByText('منشأة', { exact: true }).click();
    await client.getByLabel('اسم الطرف المطالب ضده').fill('مؤسسة اختبار المتصفح');
    await client.getByLabel('رقم السجل التجاري أو الرقم الموحد').fill('1010555555');
    await client.getByLabel('مبلغ المطالبة').fill('12500.50');
    await act(client, () => client.getByRole('button', { name: 'حفظ المسودة', exact: true }).first().click());
    // ملف تنفيذي مرفوض
    await client.locator('#claim-file').setInputFiles(FX('bad.exe'));
    await act(client, () => client.getByRole('button', { name: 'إرفاق', exact: true }).click());
    await client.getByText(/نوع الملف غير مسموح/).first().waitFor({ timeout: 10000 });
    for (const [f, cat] of [['invoice.pdf', 'invoice'], ['invoice.pdf', 'invoice'], ['statement.pdf', 'statement']]) {
      await client.locator('#claim-file').setInputFiles(FX(f));
      await client.getByLabel('نوع المستند').selectOption(cat);
      await act(client, () => client.getByRole('button', { name: 'إرفاق', exact: true }).click());
    }
    assert((await client.getByText('فاتورة').count()) >= 2, 'فاتورتان مرفقتان');
    await client.getByLabel('سبب عدم توفر مستند أساسي').fill('السجل التجاري للطرف غير متوفر حاليًا');
    await client.getByRole('button', { name: 'إرسال المطالبة', exact: true }).click();
    await client.waitForURL(new RegExp(`/portal/claims/${claimId}$`)); await settle(client);
    assert(await client.getByText('قيد الدراسة').count() > 0, 'الحالة قيد الدراسة');
    await client.screenshot({ path: OUT + '/01-claim-submitted.png' });
  });

  await step('المحامي يرى المطالبة ويحوّلها إلى قضية مرة واحدة (ضغط مزدوج)', async () => {
    await go(lawyer, '/office/claims');
    await lawyer.getByText('مؤسسة اختبار المتصفح').first().click(); await lawyer.waitForURL(/\/office\/claims\/\d+/); await settle(lawyer);
    assert(await lawyer.getByText('ناقص مستند').count() > 0, 'علامة ناقص مستند');
    await lawyer.getByRole('button', { name: 'تحويل إلى قضية', exact: true }).click();
    await dialog(lawyer).getByRole('button', { name: 'تحويل', exact: true }).dblclick();
    await lawyer.waitForURL(/\/office\/cases\/\d+/); await settle(lawyer);
    caseId = lawyer.url().match(/cases\/(\d+)/)[1];
    await go(lawyer, `/office/cases?q=${encodeURIComponent('مؤسسة اختبار المتصفح')}`);
    const rows = await lawyer.locator('table tbody tr').count();
    assert(rows === 1, 'قضية واحدة فقط من المطالبة، وُجد ' + rows);
    await go(lawyer, `/office/cases/${caseId}?tab=files`);
    assert(await lawyer.getByText('فاتورة').count() >= 2, 'مرفقات المطالبة مرتبطة بالقضية');
  });

  await step('تعذر صلح ← الانتقال إلى ناجز وإنشاء مهمة لائحة مرة واحدة', async () => {
    await go(lawyer, `/office/cases/${caseId}?tab=reconciliation`);
    if (await lawyer.getByRole('tab', { name: /الصلح/ }).count() === 0) await lawyer.getByRole('button', { name: 'طلب صلح', exact: true }).click();
    await lawyer.getByLabel('رقم الطلب').fill('TR-E2E-1');
    await act(lawyer, () => lawyer.getByRole('button', { name: 'تسجيل', exact: true }).click());
    await lawyer.getByRole('button', { name: 'تسجيل النتيجة', exact: true }).click();
    await dialog(lawyer).getByLabel('النتيجة').selectOption('failed');
    await act(lawyer, () => dialog(lawyer).getByRole('button', { name: 'حفظ النتيجة', exact: true }).click());
    await act(lawyer, () => lawyer.getByRole('button', { name: /الانتقال إلى ناجز/ }).dblclick());
    await go(lawyer, `/office/cases/${caseId}?tab=tasks`);
    const n = await lawyer.locator('li[id^="task-"]').filter({ hasText: 'إعداد لائحة الدعوى' }).count();
    assert(n === 1, 'مهمة لائحة واحدة، وُجد ' + n);
    await go(lawyer, '/office');
    assert(await lawyer.getByText('إعداد لائحة الدعوى').count() >= 1, 'تظهر في قائمة الإنجاز (بانتظار موعد)');
  });

  let defCase;
  await step('قضية مدعى عليه تبدأ من القضاء', async () => {
    await go(lawyer, '/office/cases/create');
    await pickAny(lawyer, /سالم/);
    await lawyer.getByLabel('عنوان القضية').fill('دعوى ضد العميل — اختبار متصفح');
    await lawyer.getByLabel('صفة العميل').selectOption('defendant');
    await lawyer.getByText('قضية مقيدة', { exact: true }).click();
    await lawyer.getByLabel('رقم القضية').fill('4479990001');
    await lawyer.getByLabel('المحكمة', { exact: true }).fill('المحكمة العامة بالرياض');
    await lawyer.getByRole('button', { name: 'إنشاء القضية', exact: true }).click();
    await lawyer.waitForURL(/\/office\/cases\/\d+$/); await settle(lawyer);
    defCase = lawyer.url().match(/cases\/(\d+)/)[1];
    assert(await lawyer.getByText('منظورة أمام المحكمة').count() > 0, 'المرحلة قضاء');
  });

  await step('جدولة جلسة ومهمة مرتبطة ثم تأجيلها وتحديث قائمة الأسبوع', async () => {
    const d1 = nextWorkday(1), d2 = nextWorkday(15);
    await go(lawyer, `/office/cases/${defCase}?tab=hearings`);
    await lawyer.getByRole('button', { name: 'جدولة جلسة', exact: true }).click();
    await dialog(lawyer).getByLabel('الموعد (بتوقيت الرياض)').fill(d1 + 'T10:00');
    await act(lawyer, () => dialog(lawyer).getByRole('button', { name: 'جدولة', exact: true }).click());
    await go(lawyer, `/office/cases/${defCase}?tab=tasks`);
    await lawyer.getByLabel('المهمة').fill('تقديم مذكرة جوابية قبل الجلسة');
    await lawyer.getByLabel('الاستحقاق', { exact: true }).selectOption('next_hearing');
    await lawyer.getByLabel(/قبل الجلسة القادمة/).fill('-1');
    await act(lawyer, () => lawyer.getByRole('button', { name: 'إضافة', exact: true }).click());
    await go(lawyer, '/office');
    assert(await lawyer.getByText('دعوى ضد العميل — اختبار متصفح').count() >= 1, 'الجلسة في قائمة الأسبوع');
    await go(lawyer, `/office/cases/${defCase}?tab=hearings`);
    await lawyer.getByRole('button', { name: 'تأجيل / تعديل / إلغاء', exact: true }).click();
    await dialog(lawyer).getByLabel(/الموعد الجديد/).fill(d2 + 'T11:00');
    await lawyer.waitForTimeout(500);
    assert(await dialog(lawyer).getByText('أثر التغيير على المهام المرتبطة').count() === 1, 'معاينة الأثر');
    await dialog(lawyer).getByLabel('السبب').fill('قرار الدائرة بالتأجيل');
    await act(lawyer, () => dialog(lawyer).getByRole('button', { name: 'تأكيد', exact: true }).click());
    assert(await lawyer.getByText('مؤجلة').count() >= 1, 'الجلسة مؤجلة');
    await go(lawyer, '/office');
    const inWeek = await lawyer.getByText('تقديم مذكرة جوابية قبل الجلسة').count();
    assert(inWeek === 0, 'المهمة خرجت من الأسبوع بعد التأجيل');
    await go(lawyer, `/office/cases/${defCase}?tab=timeline`);
    assert(await lawyer.getByText(/قرار الدائرة بالتأجيل/).count() >= 1, 'الواقعة محفوظة في الخط الزمني');
  });

  await step('إكمال جلسة وإرفاق ضبطها وإضافة مهام', async () => {
    await go(lawyer, `/office/cases/${defCase}?tab=hearings`);
    await lawyer.getByRole('button', { name: 'إكمال الجلسة', exact: true }).click();
    const dlg = dialog(lawyer);
    await dlg.getByRole('button', { name: 'رفع ضبط الجلسة الآن', exact: true }).click();
    await dlg.locator('input[type=file]').setInputFiles(FX('minutes.pdf'));
    await act(lawyer, () => dlg.getByRole('button', { name: 'رفع', exact: true }).click());
    await lawyer.waitForTimeout(1500);
    await lawyer.reload(); await settle(lawyer);
    await lawyer.getByRole('button', { name: 'إكمال الجلسة', exact: true }).click();
    const d2 = dialog(lawyer);
    const opt = await d2.getByLabel('ضبط الجلسة').locator('option').nth(1).getAttribute('value');
    assert(opt, 'الضبط متاح بعد الفحص');
    await d2.getByLabel('ضبط الجلسة').selectOption(opt);
    await d2.getByLabel('النتيجة').fill('قررت الدائرة تقديم الرد');
    await d2.getByRole('button', { name: 'تقديم رد', exact: true }).click();
    await d2.getByRole('button', { name: 'إرفاق مستند', exact: true }).click();
    const dates = d2.getByLabel('الاستحقاق', { exact: true });
    await dates.nth(0).selectOption('date'); await fillDate(d2.getByLabel('تاريخ الاستحقاق').first(), localDate(5));
    await dates.nth(1).selectOption('unscheduled');
    await act(lawyer, () => d2.getByRole('button', { name: 'حفظ وإكمال الجلسة', exact: true }).click());
    assert(await lawyer.getByText('منعقدة').count() >= 1, 'الجلسة منعقدة');
    assert(await lawyer.getByText('تنزيل الضبط').count() >= 1, 'الضبط مرتبط');
  });

  await step('حكم نُطق به والصك منتظر: عدادان منفصلان', async () => {
    await go(lawyer, `/office/cases/${defCase}?tab=judgment`);
    if (await lawyer.getByRole('tab', { name: 'الحكم والتنفيذ', exact: true }).count() === 0) { await lawyer.getByRole('button', { name: 'الحكم', exact: true }).click(); }
    await lawyer.getByLabel('النتيجة', { exact: true }).selectOption('partial_win');
    await fillDate(lawyer.getByLabel('تاريخ النطق'), localDate(-2));
    await act(lawyer, () => lawyer.getByRole('button', { name: 'تسجيل الحكم', exact: true }).click());
    assert(await lawyer.getByText('متابعة تقديرية لصدور صك الحكم').count() === 1, 'متابعة تقديرية');
    assert(await lawyer.getByText('موعد اعتراض على الحكم').count() === 1, 'عداد اعتراض مستقل');
    assert(await lawyer.getByText('يحتاج تحديدًا').count() >= 1, 'الاعتراض يحتاج تحديدًا');
  });

  let execLinked;
  await step('تنفيذ مالي مرتبط بالحكم ثم تحصيل جزئي', async () => {
    await go(lawyer, `/office/cases/${defCase}?tab=judgment`);
    await lawyer.getByLabel('الصك').selectOption('issued');
    await lawyer.getByLabel('رقم الصك').fill('D-E2E-1');
    await fillDate(lawyer.getByLabel('تاريخ الصك'), localDate(-1));
    await act(lawyer, () => lawyer.getByRole('button', { name: 'حفظ التحديث', exact: true }).click());
    await lawyer.getByLabel('القرار', { exact: true }).selectOption('pursue');
    await lawyer.getByRole('button', { name: 'حفظ القرار', exact: true }).click();
    await lawyer.waitForURL(/\/office\/executions\/\d+/); await settle(lawyer);
    execLinked = lawyer.url().match(/executions\/(\d+)/)[1];
    assert(await lawyer.getByText('D-E2E-1').count() >= 1, 'بيانات الصك منقولة');
    await go(lawyer, `/office/executions/${execLinked}?tab=money`);
    await lawyer.getByRole('button', { name: 'عملية مالية', exact: true }).click();
    await dialog(lawyer).getByLabel('المبلغ').fill('3000.25');
    await act(lawyer, () => dialog(lawyer).getByRole('button', { name: 'تسجيل', exact: true }).click());
    assert(await lawyer.getByText('3,000.25').count() >= 1, 'المقبوض ظاهر');
    await go(lawyer, `/office/cases/${defCase}?tab=judgment`);
    assert(await lawyer.getByText('3,000.25').count() >= 1, 'الدفعة تظهر في القضية مرة واحدة');
  });

  await step('تنفيذ مباشر يطلب محل التنفيذ ووصف الالتزام', async () => {
    await go(lawyer, '/office/executions/create');
    await pick(lawyer.getByLabel('العميل (طالب التنفيذ)'), /شركة الأمثلة/);
    await lawyer.getByText('تنفيذ مباشر', { exact: true }).click();
    await lawyer.getByLabel('نوع السند').selectOption('judgment_deed');
    await act(lawyer, () => lawyer.getByRole('button', { name: 'إنشاء الطلب', exact: true }).click());
    assert(await lawyer.getByText('حدد محل التنفيذ في التنفيذ المباشر.').count() >= 1, 'تحقق الحقول');
    await lawyer.getByLabel('محل التنفيذ').fill('عقار بحي الملقا');
    await lawyer.getByLabel('وصف الالتزام').fill('تسليم العين المؤجرة خالية');
    await lawyer.getByRole('button', { name: 'إنشاء الطلب', exact: true }).click();
    await lawyer.waitForURL(/\/office\/executions\/\d+$/); await settle(lawyer);
    assert(await lawyer.getByText('عقار بحي الملقا').count() >= 1, 'محل التنفيذ محفوظ');
  });

  await step('طلب تنفيذ مستقل بسند لأمر إلكتروني', async () => {
    await go(lawyer, '/office/executions/create');
    await pick(lawyer.getByLabel('العميل (طالب التنفيذ)'), /سالم/);
    await lawyer.getByLabel('نوع السند').selectOption('electronic_promissory_note');
    await lawyer.getByLabel('رقم السند').fill('EPN-E2E-77');
    await lawyer.getByLabel('قيمة السند').fill('8800');
    await lawyer.getByLabel('الاسم').first().fill('مدين اختبار');
    await lawyer.getByRole('button', { name: 'إنشاء الطلب', exact: true }).click();
    await lawyer.waitForURL(/\/office\/executions\/\d+$/); await settle(lawyer);
    assert(await lawyer.getByText('EPN-E2E-77').count() >= 1, 'رقم السند');
    assert(await lawyer.getByText('سند لأمر إلكتروني').count() >= 1, 'نوع السند');
  });

  await step('العميل يرى المبالغ المحصلة ولا يرى المسودات الداخلية', async () => {
    await go(client, '/portal');
    assert(await client.getByText('المحصّل فعليًا').count() === 1, 'قسم المحصل');
    const html = await client.content();
    assert(!html.includes('ملاحظات المحامي') && !html.includes('internal'), 'لا حقول داخلية');
  });

  await step('تصدير تقرير العميل وتقرير القضية PDF', async () => {
    await go(lawyer, `/office/reports?type=client&client=2`);
    await pickAny(lawyer, /شركة الأمثلة/);
    await lawyer.getByRole('button', { name: 'معاينة', exact: true }).click();
    await lawyer.frameLocator('iframe').getByText('تقرير العميل').first().waitFor();
    const [d1] = await Promise.all([lawyer.waitForEvent('download'), lawyer.getByRole('link', { name: 'تنزيل PDF', exact: true }).click()]);
    await d1.saveAs(OUT + '/client-report.pdf');
    assert(fs.readFileSync(OUT + '/client-report.pdf').subarray(0, 5).toString() === '%PDF-', 'PDF صالح');
    await go(lawyer, `/office/reports?type=case&case=${defCase}`);
    await lawyer.getByText('تقرير قضية', { exact: true }).click();
    await lawyer.getByLabel('القضية').selectOption(String(defCase));
    const [d2] = await Promise.all([lawyer.waitForEvent('download'), lawyer.getByRole('link', { name: 'تنزيل PDF', exact: true }).click()]);
    await d2.saveAs(OUT + '/case-report.pdf');
    assert(fs.statSync(OUT + '/case-report.pdf').size > 10000, 'PDF القضية');
  });

  await step('لا أخطاء JavaScript في الصفحات', async () => {
    assert(lawyer.errors.length === 0 && client.errors.length === 0, JSON.stringify([...lawyer.errors, ...client.errors]).slice(0, 300));
  });

  await browser.close();
  fs.writeFileSync(OUT + '/results.json', JSON.stringify(results, null, 2));
  const failed = results.filter((r) => !r.ok).length;
  console.log(`\n${results.length - failed}/${results.length} مسار ناجح`);
  process.exit(failed ? 1 : 0);
})();
