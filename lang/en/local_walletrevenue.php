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
 * Language strings for local_walletrevenue.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Teacher revenue and payout report';

// Capabilities.
$string['walletrevenue:viewall'] = 'View full revenue and payout reports';
$string['walletrevenue:managepayouts'] = 'Record and manage teacher payouts';
$string['walletrevenue:manageconfig'] = 'Configure payees and commission rates';
$string['walletrevenue:viewown'] = 'View personal teacher revenue and payouts';
$string['walletrevenue:recalculate'] = 'Recalculate commission rates on past sales';

// Settings.
$string['settings_general'] = 'General settings';
$string['defaultrate'] = 'Global default commission rate';
$string['defaultrate_desc'] = 'Default percentage of course revenue credited to the teacher when no override is set.';
$string['defaultcurrency'] = 'Default currency';
$string['defaultcurrency_desc'] = 'Standard 3-letter currency code (e.g. USD, EUR) for reports and payouts.';
$string['includemicropayments'] = 'Include micro-payments';
$string['includemicropayments_desc'] = 'Include availability_wallet activity and section access payments in course revenue.';
$string['enablewalletsync'] = 'Enable wallet transactions sync';
$string['enablewalletsync_desc'] = 'Synchronize course sales, micro-payments, and refunds from the enrol_wallet plugin.';
$string['enablepaymentsync'] = 'Enable core payment gateway sync';
$string['enablepaymentsync_desc'] = 'Synchronize course sales processed via Moodle core payment gateways (e.g. PayPal, Stripe via enrol_fee).';
$string['source'] = 'Revenue source';
$string['source_all'] = 'All sources';
$string['source_wallet'] = 'Wallet';
$string['source_core_payment'] = 'Core payment gateway';
$string['gateway'] = 'Payment gateway';
$string['filtersource'] = 'Filter by source';
$string['cli_option_source'] = 'Filter synchronization by source (all, wallet, payment). Default: all';

// Navigation & tabs.
$string['reports'] = 'Revenue reports';
$string['payees'] = 'Course teachers';
$string['rates'] = 'Commission rates';
$string['payouts'] = 'Payouts';
$string['myrevenue'] = 'My revenue and payouts';
$string['recalculate'] = 'Recalculate rates';

// Teacher management.
$string['assignpayee'] = 'Assign / override teacher';
$string['changepayee'] = 'Change teacher';
$string['nopayeeassigned'] = 'No teacher found';
$string['unassignedalert'] = 'There are {$a} courses with wallet sales where no teacher could be found in the course. Please enrol a teacher in the course or assign one manually.';
$string['selectteacher'] = 'Select teacher';
$string['course'] = 'Course';
$string['payeeteacher'] = 'Course teacher';
$string['autodetected'] = 'Auto-detected';
$string['manualoverride'] = 'Manual override';
$string['noteacherfound'] = 'No teacher found';
$string['autodetectoption'] = 'Automatic (auto-detect course teacher)';
$string['timefrom'] = 'From date';
$string['timeto'] = 'To date';

// Rates.
$string['rate_percent'] = 'Commission percentage';
$string['rate_scope'] = 'Scope';
$string['rate_scope_course'] = 'Course override';
$string['rate_scope_teacher'] = 'Teacher override';
$string['appliedrate'] = 'Applied rate';
$string['addrateoverride'] = 'Add commission override';
$string['itemid'] = 'Target ID';
$string['itemid_help'] = 'Enter the Course ID for a course override, or the Teacher user ID for a teacher override.';

// Reports.
$string['teacher'] = 'Teacher';
$string['currency'] = 'Currency';
$string['totalsales'] = 'Total sales';
$string['teachershare'] = 'Teacher share';
$string['transferred'] = 'Transferred';
$string['balance'] = 'Current balance';
$string['periodnetdelta'] = 'Period net change';
$string['paidstudents'] = 'Paid enrolments';
$string['listprice'] = 'List price';
$string['viewcourses'] = 'View courses';
$string['recordsale'] = 'Sale details';

// Payouts.
$string['recordpayout'] = 'Record payout';
$string['editpayout'] = 'Edit payout';
$string['deletepayout'] = 'Delete payout';
$string['payoutamount'] = 'Payout amount';
$string['paymentmethod'] = 'Payment method';
$string['method_bank'] = 'Bank transfer';
$string['method_paypal'] = 'PayPal';
$string['method_cash'] = 'Cash';
$string['method_other'] = 'Other';
$string['paymentreference'] = 'Reference / transaction ID';
$string['paymentnotes'] = 'Notes / description';
$string['paymentproof'] = 'Payment receipt / screenshot';
$string['paymentproof_help'] = 'Upload a screenshot or PDF document confirming the money transfer.';
$string['paidat'] = 'Date paid';
$string['payoutrecorded'] = 'Payout recorded successfully.';
$string['confirmpayoutdelete'] = 'Are you sure you want to delete this payout record? The teacher balance will increase accordingly.';

// Teacher portal (my.php).
$string['myearnings'] = 'My earnings overview';
$string['mycourses'] = 'My monetized courses';
$string['mypayouts'] = 'Payout history and receipts';
$string['viewreceipt'] = 'View receipt';
$string['noreceipt'] = 'No receipt attached';
$string['anonymizedstudent'] = 'Student #{$a}';
$string['notateacher'] = 'No teacher revenue records found for your account. If you teach monetized courses, your earnings and payout history will appear here.';

// Tasks.
$string['task_sync_sales'] = 'Synchronize wallet course sales and refunds';

// Recalculation.
$string['recalculatetool'] = 'Commission rate recalculation tool';
$string['previewdiff'] = 'Preview changes';
$string['applyrecalculation'] = 'Apply recalculation';

// Privacy.
$string['privacy:metadata:payee'] = 'Information about course payee teacher assignments.';
$string['privacy:metadata:payee:courseid'] = 'The course ID.';
$string['privacy:metadata:payee:teacherid'] = 'The teacher user ID.';
$string['privacy:metadata:rate'] = 'Commission rate overrides assigned to teachers or courses.';
$string['privacy:metadata:sale'] = 'Records of student purchases, course enrollments, and teacher shares.';
$string['privacy:metadata:sale:studentid'] = 'The student who purchased the enrolment.';
$string['privacy:metadata:sale:teacherid'] = 'The teacher receiving revenue share.';
$string['privacy:metadata:sale:amount'] = 'The net amount paid.';
$string['privacy:metadata:sale:teachershare'] = 'The calculated teacher share.';
$string['privacy:metadata:sale:source'] = 'The originating system of the transaction (wallet or core_payment).';
$string['privacy:metadata:sale:gateway'] = 'The payment gateway or method used for the transaction.';
$string['privacy:metadata:payout'] = 'Financial payout transfers recorded for teachers.';
$string['privacy:metadata:payout:teacherid'] = 'The teacher who received the payout.';
$string['privacy:metadata:payout:amount'] = 'The amount paid out.';
$string['privacy:metadata:payout:method'] = 'The payment method used.';
$string['privacy:metadata:payout:reference'] = 'The transaction reference number.';

// Additional UI and recalculation strings.
$string['currencybalance'] = '{$a} Balance';
$string['ratesource_course'] = 'Course override';
$string['ratesource_teacher'] = 'Teacher override';
$string['ratesource_global'] = 'Global default';
$string['amount'] = 'Amount';
$string['oldrate'] = 'Old rate %';
$string['newrate'] = 'New rate %';
$string['oldshare'] = 'Old share';
$string['newshare'] = 'New share';
$string['difference'] = 'Difference';
$string['totalnetimpact'] = 'Total net impact on teacher share: {$a}';
$string['recalculatesuccess'] = 'Successfully recalculated {$a} sale record(s).';
$string['confirmdeleterate'] = 'Are you sure you want to delete this commission rate override?';

// Partners and Agencies.
$string['partners'] = 'Partners';
$string['partner'] = 'Partner';
$string['partnername'] = 'Organization / Partner name';
$string['partnername_help'] = 'Enter the official name of the partner organization, academy, or training center.';
$string['partneruser'] = 'Partner Moodle account';
$string['partneruser_help'] = 'Select the user account representing this partner to log in and view their financial statement.';
$string['partnercode'] = 'Partner unique code';
$string['partnercode_help'] = 'An optional unique alphanumeric identifier or slug for this partner.';
$string['addpartner'] = 'Add partner';
$string['editpartner'] = 'Edit partner';
$string['deletepartner'] = 'Delete partner';
$string['confirmdeletepartner'] = 'Are you sure you want to delete this partner? Affiliated teachers will become unassigned.';
$string['partnercreated'] = 'Partner organization created successfully.';
$string['partnerupdated'] = 'Partner organization updated successfully.';
$string['partnerdeleted'] = 'Partner organization deleted successfully.';
$string['invalidpartnerid'] = 'Invalid partner ID.';
$string['erroruseralreadypartner'] = 'This user account is already assigned to another partner organization.';
$string['errorcodealreadyused'] = 'This partner code is already in use by another organization.';
$string['assignedteachers'] = 'Assigned teachers';
$string['assignedteachers_help'] = 'Select the teachers belonging to this partner. Their revenue shares will aggregate here.';
$string['assignteachers'] = 'Assign teachers';
$string['assignteacherstopartner'] = 'Assign teachers to partner';
$string['teachersassignedsuccess'] = 'Teacher assignments updated successfully.';
$string['noteachersassigned'] = 'No teachers currently assigned to this partner.';
$string['nopartnersfound'] = 'No partner organizations created yet. Click "+ Add partner" to create one.';
$string['syncfromprofiles'] = 'Sync from profile fields';
$string['syncsuccess'] = 'Teacher partner assignments synchronized from user profile fields successfully.';
$string['syncsuccesscount'] = 'Synchronized {$a} teacher assignments from user profile fields successfully.';
$string['syncalluptodate'] = 'All {$a} teacher assignments from user profile fields are already up to date.';
$string['syncnomatches'] = 'No matching partner records found in user profile fields. Make sure the profile field contains a valid partner name, code, or user email.';
$string['recipient'] = 'Recipient';
$string['recipienttype'] = 'Recipient type';
$string['recipient_teacher'] = 'Individual teacher';
$string['recipient_partner'] = 'Partner organization';
$string['selectpartner'] = 'Select partner organization...';
$string['viewstatement'] = 'View statement';
$string['partnerportal'] = 'Partner Revenue Portal';
$string['partneraccount'] = 'Affiliated account';
$string['backtopartners'] = 'Back to partners';
$string['notapartner'] = 'You are not currently registered as an authorized partner organization.';
$string['partnerportalnotice'] = 'This financial statement provides an informative, read-only summary for your affiliated instructors.';
$string['switchtopartnerportal'] = 'Switch to Partner Dashboard';
$string['teacherpartneraffiliationnotice'] = 'Your courses are affiliated with {$a}. Payouts are managed through your partner organization.';
$string['setting_partner_profile_field'] = 'Partner custom profile field';
$string['setting_partner_profile_field_desc'] = 'The shortname of the custom user profile field used to store the partner code.';
$string['partner_profile_field_label'] = 'Affiliated Partner / Organization';
$string['partner_profile_field_desc'] = 'Partner organization code or ID for revenue aggregation.';
$string['earned'] = 'Earned';
$string['teachers'] = 'Teachers';
$string['courses'] = 'Courses';
$string['payout'] = 'Payout';
