# چک لیست تست فیچر جفت‌سازی محصولات

## مرحله 1: تحضیر
- [ ] WordPress Admin میں لاگ‌ان کنید
- [ ] WooCommerce فعال است
- [ ] Inventory Sync Plugin موجود است
- [ ] دو سایت WooCommerce آماده ہے

---

## مرحله 2: Plugin Activation
- [ ] Plugins میں Inventory Sync پیدا کنید
- [ ] "Activate" کلیک کریں
- [ ] Admin میں "Inventory Sync" menu ظاہر ہے
- [ ] هیچ PHP errors نیستند

---

## مرحله 3: Settings Configuration
- [ ] Inventory Sync > ⚙️ Settings کھولیں
- [ ] Site 1 معلومات داخل کریں:
  - [ ] URL: درست ہے
  - [ ] Consumer Key: درست ہے
  - [ ] Consumer Secret: درست ہے
- [ ] Test Connection Site 1 کریں
  - [ ] ✓ "Connection established" ظاہر ہے
- [ ] Site 2 معلومات داخل کریں
  - [ ] URL: درست ہے
  - [ ] Consumer Key: درست ہے
  - [ ] Consumer Secret: درست ہے
- [ ] Test Connection Site 2 کریں
  - [ ] ✓ "Connection established" ظاہر ہے
- [ ] Save Settings کریں

---

## مرحله 4: UI Check
- [ ] Inventory Sync menu ہے
- [ ] 6 tabs ہیں:
  - [ ] ⚙️ Settings
  - [ ] 💑 Product Pairing ← نیا ہے
  - [ ] 🔗 Product Mapping
  - [ ] 📤 Transfer Products
  - [ ] ✅ Transferred Products
  - [ ] 📋 Logs

---

## مرحله 5: Product Pairing Tab
- [ ] "💑 Product Pairing" tab میں کلیک کریں
- [ ] 3 sub-tabs دیکھیں:
  - [ ] "ایجاد جفت جدید" (فعال)
  - [ ] "مدیریت جفت‌ها"
  - [ ] "لاگ‌های هماهنگ‌سازی"

---

## مرحله 6: Create Pair - Tab 1
- [ ] "ایجاد جفت جدید" tab فعال رہیں
- [ ] "محصول سایت 1" field میں کچھ لکھیں (مثلاً "test")
  - [ ] Search results دکھائی دیں
  - [ ] محصولات list میں ہوں
- [ ] ایک محصول منتخب کریں
  - [ ] Field میں محصول کا نام ہو
  - [ ] Search results بند ہو
- [ ] "محصول سایت 2" field میں کچھ لکھیں
  - [ ] Search results دکھائی دیں
  - [ ] محصولات list میں ہوں
- [ ] ایک محصول منتخب کریں
- [ ] "جهت هماهنگ‌سازی" dropdown میں:
  - [ ] "دوطرفه (بهترين انتخاب)" انتخاب کریں
- [ ] "ایجاد جفت" button کلیک کریں
  - [ ] ✓ "جفت ایجاد شد" message دیکھیں
  - [ ] 1-2 سیکنڈ میں "مدیریت جفت‌ها" tab میں جائیں

---

## مرحله 7: Manage Pairs - Tab 2
- [ ] "مدیریت جفت‌ها" tab کلیک کریں
- [ ] جفت آپ کے بننے والے جدول میں ہے:
  - [ ] شناسه (ID) ہے
  - [ ] محصول سایت 1 نام ہے
  - [ ] موجودی سایت 1 ہے
  - [ ] محصول سایت 2 نام ہے
  - [ ] موجودی سایت 2 ہے
  - [ ] آخرین sync دیکھیں
  - [ ] جهت ⟷ (دوطرفه) ہے
  - [ ] وضعیت "✓ فعال" ہے
  - [ ] دو بٹن ہیں: 🔄 (sync) اور 🗑️ (delete)
- [ ] 🔄 button کلیک کریں
  - [ ] ✓ "جفت sync شد" alert دیکھیں

---

## مرحله 8: Stock Change Test - Manual Sync
- [ ] Site 1 میں WooCommerce میں لاگ‌ان کریں
- [ ] محصول اپنے pair میں تلاش کریں
- [ ] موجودی (Stock) کو تبدیل کریں:
  - [ ] پہلے موجودی: 10
  - [ ] نیا موجودی: 25
  - [ ] Save کریں
- [ ] Site 2 میں اس محصول چیک کریں
  - [ ] موجودی: 10 ہے (ابھی نہیں بدلا)
- [ ] "💑 Product Pairing" > "مدیریت جفت‌ها" میں 🔄 button دوبارہ کلیک کریں
- [ ] Site 2 محصول دوبارہ چیک کریں
  - [ ] موجودی: 25 ہے ✅ (sync ہو گیا)

---

## مرحله 9: Automatic Sync Test (Optional - 5 minutes)
- [ ] Site 1 میں محصول کی موجودی دوبارہ تبدیل کریں:
  - [ ] نیا موجودی: 50
  - [ ] Save کریں
- [ ] 5 دقیقے انتظار کریں (Cron interval)
- [ ] Site 2 محصول چیک کریں
  - [ ] موجودی: 50 ہے ✅ (خودکار sync)

---

## مرحله 10: Logs - Tab 3
- [ ] "لاگ‌های هماهنگ‌سازی" tab کلیک کریں
- [ ] لاگ entries دیکھیں:
  - [ ] تاریخ: ہے
  - [ ] محصول نام: ہے
  - [ ] عملیات: "Stock Update" یا similar
  - [ ] منبع → مقصد: "site1 → site2" یا similar
  - [ ] موجودی نیا: value ہے
  - [ ] وضعیت: "✓ موفق" ہے
  - [ ] پیام: "Stock synced" یا similar

---

## مرحله 11: Delete Pair Test
- [ ] "مدیریت جفت‌ها" میں 🗑️ button کلیک کریں
- [ ] Confirm dialog دیکھیں
- [ ] "OK" کریں
- [ ] جفت جدول سے غائب ہے ✓

---

## مرحله 12: Create Another Pair (Different Direction)
- [ ] دوبارہ "ایجاد جفت جدید" میں جائیں
- [ ] مختلف محصولات منتخب کریں
- [ ] جهت کو "سایت 1 → سایت 2" (یک‌طرفه) کریں
- [ ] Pair بنائیں
- [ ] Site 1 میں موجودی تبدیل کریں
- [ ] Sync دستی کریں
- [ ] Site 2 موجودی تبدیل ہو ✓

---

## مرحله 13: Error Handling
- [ ] غلط/invalid API credentials ڈالیں
- [ ] Test Connection کریں
  - [ ] ✗ Error message دیکھیں
- [ ] صحیح credentials پھر سے داخل کریں

---

## نتیجہ جانچ

### کامیاب تست کے نشانات:
- ✅ Tabs کام کر رہے ہیں
- ✅ Search کام کر رہا ہے
- ✅ Pair بن رہے ہیں
- ✅ Manual sync کام کر رہا ہے
- ✅ Automatic sync (اگر 5 min الاؤ) کام کر رہا ہے
- ✅ Delete کام کر رہا ہے
- ✅ Logs ریکارڈ ہو رہے ہیں

### اگر کوئی issue ہے:
1. Browser console (F12) میں errors دیکھیں
2. WordPress Cron فعال ہے؟
3. REST API فعال ہے؟
4. API credentials صحیح ہیں؟

---

**Test Completed**: _________________ (تاریخ)
**Tested By**: ________________________ (نام)
**Result**: ☐ PASS / ☐ FAIL

---

## اگر کوئی Issue ہے تو:

### Issue: "تب خالی ہے"
```
حل:
1. Plugin Deactivate > Activate کریں
2. Browser cache پاک کریں (Ctrl+Shift+Delete)
3. Page refresh کریں
```

### Issue: "Search کام نہیں کر رہا"
```
حل:
1. API Test Connection ✓ ہے؟
2. محصولات Site 1 میں موجود ہیں؟
3. Browser console میں error ہے؟
```

### Issue: "Pair sync نہیں ہو رہا"
```
حل:
1. Cron jobs چل رہے ہیں؟
2. Manual sync button کام کر رہا ہے؟
3. Logs میں errors ہیں؟
```

---

## اگر سب ٹھیک ہے:
🎉 تبریک! فیچر جفت‌سازی دو طرفه محصولات کامیاب ہے!
