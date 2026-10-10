<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Arabic language strings for local_walletrevenue.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'تقرير أرباح ومدفوعات المعلمين';

// Capabilities.
$string['walletrevenue:viewall'] = 'عرض كامل تقارير الأرباح والمدفوعات';
$string['walletrevenue:managepayouts'] = 'تسجيل وإدارة مدفوعات المعلمين';
$string['walletrevenue:manageconfig'] = 'ضبط المعلمين المستحقين ونسب العمولة';
$string['walletrevenue:viewown'] = 'عرض الأرباح والمدفوعات الشخصية للمعلم';
$string['walletrevenue:recalculate'] = 'إعادة احتساب نسب العمولة للمبيعات السابقة';

// Settings.
$string['settings_general'] = 'الإعدادات العامة';
$string['defaultrate'] = 'نسبة العمولة الافتراضية العامة';
$string['defaultrate_desc'] = 'النسبة المئوية الافتراضية من إيرادات المقرر التي تُضاف لرصيد المعلم عند عدم تعيين استثناء مخصص.';
$string['defaultcurrency'] = 'العملة الافتراضية';
$string['defaultcurrency_desc'] = 'رمز العملة القياسي المكون من 3 أحرف (مثل USD، EGP، SAR) للتقارير والمدفوعات.';
$string['includemicropayments'] = 'تضمين المدفوعات الجزئية والمصغرة';
$string['includemicropayments_desc'] = 'تضمين مدفوعات الوصول إلى الأقسام والأنشطة عبر availability_wallet في إيرادات المقرر.';

// Navigation & tabs.
$string['reports'] = 'تقارير الأرباح';
$string['payees'] = 'معلمو المقررات';
$string['rates'] = 'نسب العمولة';
$string['payouts'] = 'المدفوعات';
$string['myrevenue'] = 'أرباحي ومدفوعاتي';
$string['recalculate'] = 'إعادة احتساب النسب';

// Teacher management.
$string['assignpayee'] = 'تعيين / استبدال المعلم';
$string['changepayee'] = 'تغيير المعلم';
$string['nopayeeassigned'] = 'لم يُعيّن معلم';
$string['unassignedalert'] = 'يوجد {$a} من المقررات بها مبيعات محفظة دون وجود معلم للمقرر. يُرجى تسجيل معلم في المقرر أو تعيينه يدوياً.';
$string['selectteacher'] = 'اختر المعلم';
$string['course'] = 'المقرر الدراسي';
$string['payeeteacher'] = 'معلم المقرر المستحق';
$string['autodetected'] = 'تلقائي';
$string['manualoverride'] = 'استثناء يدوي';
$string['noteacherfound'] = 'لم يتم العثور على معلم';
$string['autodetectoption'] = 'تلقائي (التعرف التلقائي على معلم المقرر)';
$string['timefrom'] = 'من تاريخ';
$string['timeto'] = 'إلى تاريخ';

// Rates.
$string['rate_percent'] = 'نسبة العمولة';
$string['rate_scope'] = 'النطاق';
$string['rate_scope_course'] = 'استثناء للمقرر';
$string['rate_scope_teacher'] = 'استثناء للمعلم';
$string['appliedrate'] = 'النسبة المطبقة';
$string['addrateoverride'] = 'إضافة استثناء لنسبة العمولة';
$string['itemid'] = 'معرف الهدف';
$string['itemid_help'] = 'أدخل مُعرّف المقرر الدراسي لاستثناء مقرر، أو مُعرّف المستخدم لاستثناء معلم.';

// Reports.
$string['teacher'] = 'المعلم';
$string['currency'] = 'العملة';
$string['totalsales'] = 'إجمالي المبيعات';
$string['teachershare'] = 'حصة المعلم';
$string['transferred'] = 'المحوَّل';
$string['balance'] = 'الرصيد الحالي';
$string['periodnetdelta'] = 'صافي تغير الفترة';
$string['paidstudents'] = 'الاشتراكات المدفوعة';
$string['listprice'] = 'سعر البيع';
$string['viewcourses'] = 'عرض المقررات';
$string['recordsale'] = 'تفاصيل المبيعات';

// Payouts.
$string['recordpayout'] = 'تسجيل دفعة';
$string['editpayout'] = 'تعديل الدفعة';
$string['deletepayout'] = 'حذف الدفعة';
$string['payoutamount'] = 'مبلغ الدفعة';
$string['paymentmethod'] = 'طريقة الدفع';
$string['method_bank'] = 'تحويل بنكي';
$string['method_paypal'] = 'بايبال (PayPal)';
$string['method_cash'] = 'نقداً';
$string['method_other'] = 'أخرى';
$string['paymentreference'] = 'رقم المعاملة / المرجع';
$string['paymentnotes'] = 'ملاحظات / وصف';
$string['paymentproof'] = 'إيصال الدفع / صورة التحويل';
$string['paymentproof_help'] = 'ارفع لقطة شاشة أو ملف PDF يؤكد إتمام التحويل المالي.';
$string['paidat'] = 'تاريخ السداد';
$string['payoutrecorded'] = 'تم تسجيل الدفعة بنجاح.';
$string['confirmpayoutdelete'] = 'هل أنت متأكد من رغبتك في حذف هذا السجل؟ سيتم زيادة رصيد المعلم بمقدار هذا المبلغ.';

// Teacher portal (my.php).
$string['myearnings'] = 'نظرة عامة على أرباحي';
$string['mycourses'] = 'مقرراتي المدفوعة';
$string['mypayouts'] = 'سجل المدفوعات والإيصالات';
$string['viewreceipt'] = 'عرض الإيصال';
$string['noreceipt'] = 'لا يوجد إيصال مرفق';
$string['anonymizedstudent'] = 'طالب #{$a}';
$string['notateacher'] = 'لم يتم العثور على سجلات أرباح لحسابك. إذا كنت تقوم بتدريس مقررات مدفوعة، فستظهر أرباحك وسجل مدفوعاتك هنا.';

// Tasks.
$string['task_sync_sales'] = 'مزامنة مبيعات واستردادات المحفظة';

// Recalculation.
$string['recalculatetool'] = 'أداة إعادة احتساب نسب العمولة';
$string['previewdiff'] = 'معاينة التغييرات';
$string['applyrecalculation'] = 'تطبيق إعادة الاحتساب';

// Privacy.
$string['privacy:metadata:payee'] = 'معلومات حول تعيينات معلمي المقررات المستحقين للأرباح.';
$string['privacy:metadata:payee:courseid'] = 'معرف المقرر الدراسي.';
$string['privacy:metadata:payee:teacherid'] = 'معرف المستخدم للمعلم.';
$string['privacy:metadata:rate'] = 'استثناءات نسب العمولة المخصصة للمعلمين أو المقررات.';
$string['privacy:metadata:sale'] = 'سجلات مشتريات الطلاب واشتراكات المقررات وحصص المعلمين.';
$string['privacy:metadata:sale:studentid'] = 'الطالب الذي قام بشراء الاشتراك.';
$string['privacy:metadata:sale:teacherid'] = 'المعلم المستحق لحصة الأرباح.';
$string['privacy:metadata:sale:amount'] = 'المبلغ الصافي المدفوع.';
$string['privacy:metadata:sale:teachershare'] = 'حصة المعلم المحتسبة.';
$string['privacy:metadata:payout'] = 'التحويلات المالية المسجلة للمعلمين.';
$string['privacy:metadata:payout:teacherid'] = 'المعلم الذي استلم الدفعة.';
$string['privacy:metadata:payout:amount'] = 'المبلغ المدفوع.';
$string['privacy:metadata:payout:method'] = 'طريقة الدفع المستخدمة.';
$string['privacy:metadata:payout:reference'] = 'رقم مرجع المعاملة.';

// Additional UI and recalculation strings.
$string['currencybalance'] = 'رصيد {$a}';
$string['ratesource_course'] = 'استثناء للمقرر';
$string['ratesource_teacher'] = 'استثناء للمعلم';
$string['ratesource_global'] = 'الافتراضي العام';
$string['amount'] = 'المبلغ';
$string['oldrate'] = 'النسبة السابقة %';
$string['newrate'] = 'النسبة الجديدة %';
$string['oldshare'] = 'الحصة السابقة';
$string['newshare'] = 'الحصة الجديدة';
$string['difference'] = 'الفارق';
$string['totalnetimpact'] = 'إجمالي الأثر الصافي على حصة المعلم: {$a}';
$string['recalculatesuccess'] = 'تمت إعادة احتساب {$a} من سجلات المبيعات بنجاح.';
$string['confirmdeleterate'] = 'هل أنت متأكد من رغبتك في حذف هذا الاستثناء لنسبة العمولة؟';

// Partners and Agencies.
$string['partners'] = 'الشركاء والمراكز';
$string['partner'] = 'الشريك / المركز';
$string['partnername'] = 'اسم المركز / الشريك';
$string['partnername_help'] = 'أدخل الاسم التجاري أو الرسمي للمركز، الأكاديمية، أو الجهة الشريكة.';
$string['partneruser'] = 'حساب مودل للشريك';
$string['partneruser_help'] = 'اختر حساب المستخدم في مودل الذي يمثل هذه الجهة الشريكة للاطلاع على كشف الحساب.';
$string['partnercode'] = 'رمز الشريك الفريد';
$string['partnercode_help'] = 'رمز أو معرف فريد ومختصر للشريك، يمكن استخدامه في حقول الملف الشخصي.';
$string['addpartner'] = 'إضافة شريك';
$string['editpartner'] = 'تعديل بيانات الشريك';
$string['deletepartner'] = 'حذف الشريك';
$string['confirmdeletepartner'] = 'هل أنت متأكد من رغبتك في حذف هذا الشريك؟ سيتم إلغاء انتساب المعلمين التابعين له.';
$string['partnercreated'] = 'تم إنشاء جهة الشريك بنجاح.';
$string['partnerupdated'] = 'تم تحديث بيانات الشريك بنجاح.';
$string['partnerdeleted'] = 'تم حذف جهة الشريك بنجاح.';
$string['invalidpartnerid'] = 'معرف الشريك غير صالح.';
$string['erroruseralreadypartner'] = 'حساب المستخدم هذا مرتبط بالفعل بجهة شريكة أخرى.';
$string['errorcodealreadyused'] = 'رمز الشريك هذا مستخدم بالفعل من قبل جهة أخرى.';
$string['assignedteachers'] = 'المعلمون المنتسبون';
$string['assignedteachers_help'] = 'حدد المعلمين التابعين لهذه الجهة الشريكة لتجميع أرباحهم المالية هنا.';
$string['assignteachers'] = 'تعيين المعلمين';
$string['assignteacherstopartner'] = 'تعيين المعلمين للمركز الشريك';
$string['teachersassignedsuccess'] = 'تم تحديث تعيينات المعلمين بنجاح.';
$string['noteachersassigned'] = 'لا يوجد معلمون منتسبون لهذا الشريك حالياً.';
$string['nopartnersfound'] = 'لم يتم إنشاء أي جهات شريكة حتى الآن. انقر على "+ إضافة شريك" للبدء.';
$string['syncfromprofiles'] = 'مزامنة من الملفات الشخصية';
$string['syncsuccess'] = 'تمت مزامنة انتساب المعلمين للشركاء من حقول الملفات الشخصية بنجاح.';
$string['syncsuccesscount'] = 'تمت مزامنة تعيينات {$a} معلماً من حقول الملفات الشخصية بنجاح.';
$string['syncalluptodate'] = 'جميع تعيينات المعلمين ({$a}) من حقول الملفات الشخصية محدثة بالفعل.';
$string['syncnomatches'] = 'لم يتم العثور على أي شريك مطابق في حقول الملفات الشخصية. يرجى التأكد من كتابة اسم الشريك أو رمزه أو بريده الإلكتروني بشكل صحيح.';
$string['recipient'] = 'المستحق / المستلم';
$string['recipienttype'] = 'نوع المستلم';
$string['recipient_teacher'] = 'معلم فردي';
$string['recipient_partner'] = 'جهة شريكة / مركز';
$string['selectpartner'] = 'اختر الجهة الشريكة...';
$string['viewstatement'] = 'عرض كشف الحساب';
$string['partnerportal'] = 'بوابة أرباح الشركاء والمراكز';
$string['partneraccount'] = 'الحساب المالي المرتبط';
$string['backtopartners'] = 'العودة لقائمة الشركاء';
$string['notapartner'] = 'أنت غير مسجل كجهة شريكة معتمدة حالياً.';
$string['partnerportalnotice'] = 'يوفر كشف الحساب هذا ملخصاً مالياً للقراءة فقط يوضح الأرباح وسجل التحويلات المالية للمعلمين التابعين لكم.';
$string['switchtopartnerportal'] = 'الانتقال للوحة تحكم الشريك';
$string['teacherpartneraffiliationnotice'] = 'دوراتك تابعة لجهة {$a}. تتم إدارة وصرف مستحقاتك المالية من خلال جهتكم الشريكة.';
$string['setting_partner_profile_field'] = 'حقل الملف الشخصي للشريك';
$string['setting_partner_profile_field_desc'] = 'الاسم المختصر لحقل الملف الشخصي المخصص في مودل لحفظ رمز الشريك.';
$string['partner_profile_field_label'] = 'الجهة الشريكة / المركز التابع له';
$string['partner_profile_field_desc'] = 'رمز أو معرف المركز الشريك لتجميع الأرباح المالية.';
$string['earned'] = 'المكتسب';
$string['teachers'] = 'معلمين';
$string['courses'] = 'المقررات الدراسية';
$string['payout'] = 'صرف المستحقات';
