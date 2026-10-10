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

namespace local_walletrevenue\local;

/**
 * Calculation engine for revenue, commission shares, payouts, and balances.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculator {
    /**
     * Get summary metrics for all teachers grouped by teacher and currency.
     *
     * @param int $timestart Filter start timestamp (0 for all time).
     * @param int $timeend Filter end timestamp (0 for all time).
     * @param int $filterteacher Filter by specific teacher ID (0 for all).
     * @param string $filtercurrency Filter by currency code (empty for all).
     * @return array Array of summary objects per teacher and currency.
     */
    public static function get_teachers_summary(
        int $timestart = 0,
        int $timeend = 0,
        int $filterteacher = 0,
        string $filtercurrency = ''
    ): array {
        global $DB;

        // Auto-heal and resolve any sales to course teachers if they are unassigned.
        payee_resolver::resolve_all_unassigned_sales();

        // 1. Fetch lifetime teacher shares and all-time transferred to compute current balance accurately.
        $lifetime = self::get_lifetime_balances($filterteacher, $filtercurrency);

        // 2. Fetch period sales and shares.
        $periodsales = self::get_period_sales($timestart, $timeend, $filterteacher, $filtercurrency);

        // 3. Fetch period payouts.
        $periodpayouts = self::get_period_payouts($timestart, $timeend, $filterteacher, $filtercurrency);

        // Merge results into unified summary list.
        $allkeys = array_unique(array_merge(
            array_keys($lifetime),
            array_keys($periodsales),
            array_keys($periodpayouts)
        ));

        $results = [];
        foreach ($allkeys as $key) {
            [$teacherid, $currency] = explode(':', $key);
            $teacherid = (int) $teacherid;

            $lt = $lifetime[$key] ?? ['share' => 0.0, 'transferred' => 0.0, 'balance' => 0.0];
            $ps = $periodsales[$key] ?? ['totalsales' => 0.0, 'teachershare' => 0.0];
            $pp = $periodpayouts[$key] ?? ['transferred' => 0.0];

            // Teacher info.
            $teacheruser = null;
            if ($teacherid > 0) {
                $teacheruser = \core_user::get_user($teacherid);
            }

            $perioddelta = $ps['teachershare'] - $pp['transferred'];

            $results[] = (object) [
                'teacherid'       => $teacherid,
                'teacheruser'     => $teacheruser,
                'currency'        => $currency,
                'totalsales'      => $ps['totalsales'],
                'teachershare'    => $ps['teachershare'],
                'transferred'     => $pp['transferred'],
                'currentbalance'  => $lt['balance'],
                'lifetimeshare'   => $lt['share'],
                'lifetimetransfer' => $lt['transferred'],
                'periodnetdelta'  => $perioddelta,
            ];
        }

        // Sort by teacher name.
        usort($results, function ($a, $b) {
            $unassigned = get_string('nopayeeassigned', 'local_walletrevenue');
            $namea = $a->teacheruser ? fullname($a->teacheruser) : $unassigned;
            $nameb = $b->teacheruser ? fullname($b->teacheruser) : $unassigned;
            return strcmp($namea, $nameb);
        });

        return $results;
    }

    /**
     * Get course breakdown for a teacher.
     *
     * @param int $teacherid Teacher user ID.
     * @param string $currency Currency code.
     * @param int $timestart Filter start timestamp.
     * @param int $timeend Filter end timestamp.
     * @return array Array of course breakdown objects.
     */
    public static function get_teacher_courses(
        int $teacherid,
        string $currency = '',
        int $timestart = 0,
        int $timeend = 0
    ): array {
        global $DB;

        $params = ['teacherid' => $teacherid];
        $where = "s.teacherid = :teacherid";

        if (!empty($currency)) {
            $where .= " AND s.currency = :currency";
            $params['currency'] = $currency;
        }
        if ($timestart > 0) {
            $where .= " AND s.timecreated >= :timestart";
            $params['timestart'] = $timestart;
        }
        if ($timeend > 0) {
            $where .= " AND s.timecreated <= :timeend";
            $params['timeend'] = $timeend;
        }

        $sql = "SELECT " . $DB->sql_concat('s.courseid', "':'", 's.currency') . " AS ukey,
                       s.courseid,
                       c.fullname AS coursename,
                       c.shortname AS courseshortname,
                       s.currency,
                       AVG(s.listprice) AS listprice,
                       COUNT(DISTINCT CASE WHEN s.type = 'sale' THEN s.studentid END) AS studentcount,
                       SUM(s.amount) AS totalsales,
                       SUM(s.teachershare) AS teachershare
                  FROM {local_walletrevenue_sale} s
             LEFT JOIN {course} c ON c.id = s.courseid
                 WHERE $where
              GROUP BY s.courseid, c.fullname, c.shortname, s.currency
              ORDER BY totalsales DESC";

        $records = $DB->get_records_sql($sql, $params);
        $output = [];

        foreach ($records as $r) {
            $rateinfo = rate_resolver::get_rate_info((int) $r->courseid, $teacherid);
            $r->appliedrate = $rateinfo['percent'];
            $r->ratesource = $rateinfo['source'];
            $r->totalsales = (float) $r->totalsales;
            $r->teachershare = (float) $r->teachershare;
            $r->listprice = (float) $r->listprice;
            $output[] = $r;
        }

        return $output;
    }

    /**
     * Helper to get lifetime balances per teacher and currency.
     */
    protected static function get_lifetime_balances(int $filterteacher = 0, string $filtercurrency = ''): array {
        global $DB;

        // Lifetime sales shares.
        $params = [];
        $where = "1=1";
        if ($filterteacher > 0) {
            $where .= " AND s.teacherid = :tid";
            $params['tid'] = $filterteacher;
        }
        if (!empty($filtercurrency)) {
            $where .= " AND s.currency = :cur";
            $params['cur'] = $filtercurrency;
        }

        $sql = "SELECT " . $DB->sql_concat('s.teacherid', "':'", 's.currency') . " AS ukey,
                       s.teacherid,
                       s.currency,
                       SUM(s.teachershare) AS totalshare
                  FROM {local_walletrevenue_sale} s
                 WHERE $where
              GROUP BY s.teacherid, s.currency";

        $sales = $DB->get_records_sql($sql, $params);

        // Lifetime payouts.
        $pparams = [];
        $pwhere = "1=1";
        if ($filterteacher > 0) {
            $pwhere .= " AND p.teacherid = :ptid";
            $pparams['ptid'] = $filterteacher;
        }
        if (!empty($filtercurrency)) {
            $pwhere .= " AND p.currency = :pcur";
            $pparams['pcur'] = $filtercurrency;
        }

        $psql = "SELECT " . $DB->sql_concat('p.teacherid', "':'", 'p.currency') . " AS ukey,
                        p.teacherid,
                        p.currency,
                        SUM(p.amount) AS totalpayout
                   FROM {local_walletrevenue_payout} p
                  WHERE $pwhere
               GROUP BY p.teacherid, p.currency";

        $payouts = $DB->get_records_sql($psql, $pparams);

        $lifetime = [];
        $allkeys = array_unique(array_merge(array_keys($sales), array_keys($payouts)));

        foreach ($allkeys as $k) {
            $share = isset($sales[$k]) ? (float) $sales[$k]->totalshare : 0.0;
            $trans = isset($payouts[$k]) ? (float) $payouts[$k]->totalpayout : 0.0;
            $lifetime[$k] = [
                'share'       => $share,
                'transferred' => $trans,
                'balance'     => round($share - $trans, 4),
            ];
        }

        return $lifetime;
    }

    /**
     * Helper to get period sales per teacher and currency.
     */
    protected static function get_period_sales(
        int $timestart = 0,
        int $timeend = 0,
        int $filterteacher = 0,
        string $filtercurrency = ''
    ): array {
        global $DB;

        $params = [];
        $where = "1=1";
        if ($timestart > 0) {
            $where .= " AND s.timecreated >= :start";
            $params['start'] = $timestart;
        }
        if ($timeend > 0) {
            $where .= " AND s.timecreated <= :end";
            $params['end'] = $timeend;
        }
        if ($filterteacher > 0) {
            $where .= " AND s.teacherid = :tid";
            $params['tid'] = $filterteacher;
        }
        if (!empty($filtercurrency)) {
            $where .= " AND s.currency = :cur";
            $params['cur'] = $filtercurrency;
        }

        $sql = "SELECT " . $DB->sql_concat('s.teacherid', "':'", 's.currency') . " AS ukey,
                       SUM(s.amount) AS totalsales,
                       SUM(s.teachershare) AS teachershare
                  FROM {local_walletrevenue_sale} s
                 WHERE $where
              GROUP BY s.teacherid, s.currency";

        $records = $DB->get_records_sql($sql, $params);
        $res = [];
        foreach ($records as $k => $r) {
            $res[$k] = [
                'totalsales'   => (float) $r->totalsales,
                'teachershare' => (float) $r->teachershare,
            ];
        }
        return $res;
    }

    /**
     * Helper to get period payouts per teacher and currency.
     */
    protected static function get_period_payouts(
        int $timestart = 0,
        int $timeend = 0,
        int $filterteacher = 0,
        string $filtercurrency = ''
    ): array {
        global $DB;

        $params = [];
        $where = "1=1";
        if ($timestart > 0) {
            $where .= " AND p.paidat >= :start";
            $params['start'] = $timestart;
        }
        if ($timeend > 0) {
            $where .= " AND p.paidat <= :end";
            $params['end'] = $timeend;
        }
        if ($filterteacher > 0) {
            $where .= " AND p.teacherid = :tid";
            $params['tid'] = $filterteacher;
        }
        if (!empty($filtercurrency)) {
            $where .= " AND p.currency = :cur";
            $params['cur'] = $filtercurrency;
        }

        $sql = "SELECT " . $DB->sql_concat('p.teacherid', "':'", 'p.currency') . " AS ukey,
                       SUM(p.amount) AS transferred
                  FROM {local_walletrevenue_payout} p
                 WHERE $where
              GROUP BY p.teacherid, p.currency";

        $records = $DB->get_records_sql($sql, $params);
        $res = [];
        foreach ($records as $k => $r) {
            $res[$k] = [
                'transferred' => (float) $r->transferred,
            ];
        }
        return $res;
    }

    /**
     * Get summary metrics for a specific partner aggregated across all assigned teachers.
     *
     * @param int $partnerid Partner ID.
     * @param int $timestart Filter start timestamp (0 for all time).
     * @param int $timeend Filter end timestamp (0 for all time).
     * @return array Array of summary objects per currency.
     */
    public static function get_partner_summary(int $partnerid, int $timestart = 0, int $timeend = 0): array {
        global $DB;

        $teacherids = partner_manager::get_teacher_ids_for_partner($partnerid);
        if (empty($teacherids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, 'tid');

        // 1. Lifetime sales share for all assigned teachers.
        $sharesql = "SELECT s.currency,
                            SUM(s.amount) AS totalsales,
                            SUM(s.teachershare) AS totalshare
                       FROM {local_walletrevenue_sale} s
                      WHERE s.teacherid $insql
                   GROUP BY s.currency";
        $sharerows = $DB->get_records_sql($sharesql, $inparams);

        // 2. Lifetime payouts recorded directly to this partner.
        $payoutsql = "SELECT p.currency,
                             SUM(p.amount) AS totalpayout
                        FROM {local_walletrevenue_payout} p
                       WHERE p.partnerid = :pid AND p.recipienttype = 'partner'
                    GROUP BY p.currency";
        $payoutrows = $DB->get_records_sql($payoutsql, ['pid' => $partnerid]);

        $currencies = array_unique(array_merge(array_keys($sharerows), array_keys($payoutrows)));
        sort($currencies);

        $results = [];
        foreach ($currencies as $cur) {
            $share = isset($sharerows[$cur]) ? (float) $sharerows[$cur]->totalshare : 0.0;
            $sales = isset($sharerows[$cur]) ? (float) $sharerows[$cur]->totalsales : 0.0;
            $payout = isset($payoutrows[$cur]) ? (float) $payoutrows[$cur]->totalpayout : 0.0;
            $balance = round($share - $payout, 4);

            $results[] = (object) [
                'partnerid'        => $partnerid,
                'currency'         => $cur,
                'totalsales'       => $sales,
                'lifetimeshare'    => $share,
                'lifetimetransfer' => $payout,
                'currentbalance'   => $balance,
            ];
        }

        return $results;
    }

    /**
     * Get teacher breakdown metrics for a specific partner.
     *
     * @param int $partnerid Partner ID.
     * @param int $timestart Filter start timestamp (0 for all time).
     * @param int $timeend Filter end timestamp (0 for all time).
     * @return array Array of teacher breakdown objects.
     */
    public static function get_partner_teachers(int $partnerid, int $timestart = 0, int $timeend = 0): array {
        global $DB;

        $teacherids = partner_manager::get_teacher_ids_for_partner($partnerid);
        if (empty($teacherids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, 'tid');
        $where = "s.teacherid $insql";
        if ($timestart > 0) {
            $where .= " AND s.timecreated >= :start";
            $params['start'] = $timestart;
        }
        if ($timeend > 0) {
            $where .= " AND s.timecreated <= :end";
            $params['end'] = $timeend;
        }

        $ukey = $DB->sql_concat('s.teacherid', "':'", 's.currency');
        $sql = "SELECT $ukey AS ukey,
                       s.teacherid,
                       s.currency,
                       COUNT(DISTINCT s.courseid) AS coursecount,
                       COUNT(DISTINCT CASE WHEN s.type = 'sale' THEN s.studentid END) AS studentcount,
                       SUM(s.amount) AS totalsales,
                       SUM(s.teachershare) AS teachershare
                  FROM {local_walletrevenue_sale} s
                 WHERE $where
              GROUP BY s.teacherid, s.currency
              ORDER BY teachershare DESC";

        $records = $DB->get_records_sql($sql, $params);
        $results = [];

        foreach ($records as $r) {
            $r->teacheruser = \core_user::get_user((int) $r->teacherid);
            $r->totalsales = (float) $r->totalsales;
            $r->teachershare = (float) $r->teachershare;
            $results[] = $r;
        }

        return $results;
    }

    /**
     * Get courses breakdown for all teachers assigned to a partner.
     *
     * @param int $partnerid Partner ID.
     * @param int $timestart Filter start timestamp (0 for all time).
     * @param int $timeend Filter end timestamp (0 for all time).
     * @return array Array of course breakdown objects.
     */
    public static function get_partner_courses(int $partnerid, int $timestart = 0, int $timeend = 0): array {
        global $DB;

        $teacherids = partner_manager::get_teacher_ids_for_partner($partnerid);
        if (empty($teacherids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, 'tid');
        $where = "s.teacherid $insql";
        if ($timestart > 0) {
            $where .= " AND s.timecreated >= :start";
            $params['start'] = $timestart;
        }
        if ($timeend > 0) {
            $where .= " AND s.timecreated <= :end";
            $params['end'] = $timeend;
        }

        $ukey = $DB->sql_concat('s.courseid', "':'", 's.currency');
        $sql = "SELECT $ukey AS ukey,
                       s.courseid,
                       s.teacherid,
                       c.fullname AS coursename,
                       c.shortname AS courseshortname,
                       s.currency,
                       AVG(s.listprice) AS listprice,
                       COUNT(DISTINCT CASE WHEN s.type = 'sale' THEN s.studentid END) AS studentcount,
                       SUM(s.amount) AS totalsales,
                       SUM(s.teachershare) AS teachershare
                  FROM {local_walletrevenue_sale} s
             LEFT JOIN {course} c ON c.id = s.courseid
                 WHERE $where
              GROUP BY s.courseid, s.teacherid, c.fullname, c.shortname, s.currency
              ORDER BY totalsales DESC";

        $records = $DB->get_records_sql($sql, $params);
        $results = [];

        foreach ($records as $r) {
            $r->teacheruser = \core_user::get_user((int) $r->teacherid);
            $rateinfo = rate_resolver::get_rate_info((int) $r->courseid, (int) $r->teacherid);
            $r->appliedrate = $rateinfo['percent'];
            $r->totalsales = (float) $r->totalsales;
            $r->teachershare = (float) $r->teachershare;
            $r->listprice = (float) $r->listprice;
            $results[] = $r;
        }

        return $results;
    }
}
