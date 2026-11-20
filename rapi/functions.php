<?php

class DataValues{
    private $conn;
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }
    
    public function getNewAccount($category){
        if($category == 8){
            $sql = "SELECT IFNULL(MAX(accNumber), 0) + 1 AS accNumber FROM accounts WHERE accCategory = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$category]);
        }else{
            $stmt1 = $this->conn->prepare("SELECT max(acgCategory) from accountCategory where acgID = ? limit 1");
            $stmt1->execute([$category]);
            $mainCat = $stmt1->fetchColumn();

            $sql = "SELECT IFNULL(MAX(accNumber), 0) + 1 AS accNumber 
                FROM accounts ac
                join accountCategory ag on ag.acgID = ac.accCategory
                WHERE acgCategory = ?;";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$mainCat]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $accNumber = $row['accNumber'];
        return $accNumber;
    }

    public function getLocalCurrency(){
        $sql = "select comLocalCcy from companyProfile limit 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $localCcy = $row['comLocalCcy'];
        return $localCcy;
    }

    public function accountDependencies($acc){
        $sql = "select comLocalCcy from companyProfile limit 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $localCcy = $row['comLocalCcy'];
        return $localCcy;
    }

    public function getCcyRate($from, $to){
        $sql = "select crExchange from ccyRate where crFrom = :from and crTo = :to order by crDate DESC limit 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':from', $from);
        $stmt->bindParam(':to', $to);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $rate = $row['crExchange'];
        return $rate;
    }

    public function generateBranchID(){
        $sql = "select brcID+1 as brcID from branch order by brcID DESC limit 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $row['brcID'];
        return $id;
    }

    public function checkUsernameExistance($user){
        $sql = "SELECT COUNT(*) from users Where usrName = :user";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user', $user);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;;
    }

    public function checkUserEmailExistance($email){
        $sql = "SELECT COUNT(*) FROM users WHERE usrEmail = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function checkForAccountLimit($accNo, $amount){
        if($accNo > 10000000){
            return true;
        }else{
            $sql = "SELECT COALESCE(SUM(CASE WHEN trdDrCr='Cr' THEN trdAmount ELSE -trdAmount END), 0) AS balance, COALESCE(actCreditLimit, 0) as actCreditLimit FROM accounts
                JOIN accountDetails 
                    ON accountDetails.actAccount = accounts.accNumber
                LEFT JOIN trnDetails 
                    ON trnDetails.trdAccount = accounts.accNumber
                WHERE accounts.accNumber = :accNo";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':accNo', $accNo, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $balance      = (float) ($result['balance'] ?? 0);
            $creditLimit  = (float) ($result['actCreditLimit'] ?? 0);

            // echo "Current Balance is: $balance\n";
            // echo "Credit Limit is $creditLimit\n";
            // Calculate balance after withdrawal
            $newBalance = $balance - $amount;

            /*
                Rule:
                - Balance can go negative
                - But NOT below -creditLimit
            */

            // echo "New Balance is $newBalance\n";
            if ($newBalance >= -$creditLimit) {
                // echo "New Balance is 'True'\n";
                return true;
            }
            // echo "New Balance is 'False'";
            return false;
        }
        
    }

    public function getUserDetails($username){
        $sql = "SELECT * FROM users WHERE usrName = :user LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user', $username);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function generateTrnRef($brc, $type){
        $sql = "SELECT generate_ref(:brc, :type) AS trnRef";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":brc", $brc);
        $stmt->bindParam(":type", $type);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['trnRef'];
    }

    public function getCompanyAttributes($colName){
        $sql = "select * from companyProfile limit 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row[$colName];
    }

    public function getBranchAuthLimit($branch, $ccy){
        $sql = "select balLimitAmount from branchAuthLimit where balBranch=:bCode and balCurrency=:ccy";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":bCode", $branch);
        $stmt->bindParam(":ccy", $ccy);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            return $row['balLimitAmount'];
        }else{
            return 0;
        }

        
    }

    public function getAccountDetails($acc, $colName){
        if($acc < 10000000){
            $sql = "SELECT * from accounts 
            join accountDetails on accountDetails.actAccount = accounts.accNumber
            where accNumber = :acc";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":acc", $acc);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row[$colName];
        }
        return 1;
        
    }

    public function getCurrencyDetails($ccy, $colName){
        $sql = "select * from currency where ccyCode = :code limit 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":code", $ccy);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row[$colName];
    }

    public function generateUserActivityLog($user, $logType, $logdetails){
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        // Get IP Address
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Get Platfrom
        if (preg_match('/android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $os = 'iOS';
        } elseif (preg_match('/windows/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/mac/i', $userAgent)) {
            $os = 'Mac';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $os = 'Linux';
        } else{
            $os = 'Unknown';
        }

        // Get Browser
        if (preg_match('/chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
             $browser = 'Firefox';
        } elseif (preg_match('/safari/i', $userAgent)) {
             $browser = 'Safari';
        } elseif (preg_match('/edge/i', $userAgent)) {
             $browser = 'Edge';
        } else {
            $browser = 'Unknown';
        }

        // Get Device Type
        $isMobile = preg_match('/mobile|android|iphone/i', $userAgent) ? 'Mobile' : 'Desktop';

        $device = "$os, $isMobile, $browser";
        



        try {
            $sql = "select * from companyProfile limit 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            date_default_timezone_set($row['comTimeZone']);
            $entryDateTime = date("Y-m-d H:i:s");

            $stmt1 = $this->conn->prepare("select usrID from users where usrName = ?");
            $stmt1->execute([$user]);
            $row1 = $stmt1->fetch(PDO::FETCH_ASSOC);
            $usrID = $row1['usrID'];

            $stmt2 = $this->conn->prepare("INSERT into userActivityLog (ualUser, ualType, ualDetails, ualIP, ualDevice, ualTiming) values (?, ?, ?, ?, ?, ?)");
            $stmt2->execute([$usrID, $logType, $logdetails, $ip, $device, $entryDateTime]);
            

        } catch (\Throwable $th) {
            echo json_encode([
                "msg" => "failed",
                "error" => $th->getMessage(),
                "line" => $th->getLine(),
                "file" => $th->getFile()
            ]);
        }
    }

    public function printCashTransaction($ref){
        $sql = "select tr.trnReference, tr.trnType, td.trdAmount, td.trdCcy, td.trdBranch, td.trdAccount, accName, td.trdNarration,
            td.trdEntryDate as trnEntryDate, mk.usrName as maker, ck.usrname as checker from transactions tr
            join trnDetails td on td.trdReference = tr.trnReference
            join users mk on mk.usrID = tr.trnMaker
            left join users ck on ck.usrID = tr.trnAuthorizer
            join accounts acc on acc.accNumber = td.trdAccount
            where 
                td.trdAccount != 10101010 and
                td.trdReference = :ref
            order by td.trdEntryDate desc  limit 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":ref", $ref, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data;
    }
}

function current_user_id(): ?int {
    static $uid = null;
    static $resolved = false;
    if ($resolved) return $uid;
    $resolved = true;

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization']
        ?? $headers['authorization']
        ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    if (!preg_match('/Bearer\s+(\S+)/i', $auth, $m)) return null;

    global $conn;
    if (!($conn instanceof PDO)) return null;

    $hash = hash('sha256', $m[1]);
    $stmt = $conn->prepare(
        "SELECT utkUserID
           FROM userTokens
          WHERE utkTokenHash = :h AND utkExpiresAt > NOW()
          LIMIT 1"
    );
    $stmt->execute([':h' => $hash]);
    $id = $stmt->fetchColumn();
    $uid = $id ? (int) $id : null;
    return $uid;
}

const TRANSPORT_CLEARING_ACCOUNT = 20202028;
/**
 * Fetch the base currency (company local currency). Cached per request.
 */
function baseCurrency(PDO $conn): string {
    static $ccy = null;
    if ($ccy !== null) return $ccy;
    $stmt = $conn->query("SELECT comLocalCcy FROM companyProfile LIMIT 1");
    $ccy = (string)($stmt->fetchColumn() ?: 'USD');
    return $ccy;
}

/**
 * Validate an account number: must exist in accountDetails AND accounts.
 * Returns ['accNumber'=>int, 'accCcy'=>string, 'accName'=>string] or null.
 */
function loadAccount(PDO $conn, $accNumber): ?array {
    if ($accNumber === null || $accNumber === '') return null;

    $stmt = $conn->prepare(
        "SELECT ad.actAccount AS accNumber,
                ad.actCurrency AS accCcy,
                a.accName      AS accName
           FROM accountDetails ad
           JOIN accounts a ON a.accNumber = ad.actAccount
          WHERE ad.actAccount = ?
            AND ad.actStatus = 1
          LIMIT 1"
    );
    $stmt->execute([(int)$accNumber]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Narration tag so we can find/update/delete the trnDetails line
 * belonging to a specific shipment item without a schema change.
 */
function itemNarrationTag(int $shpItmId): string {
    return "item#$shpItmId";
}

/**
 * Returns true when the item has enough rate data to compute a non-zero rent.
 * Used to delay posting until rates are filled in (e.g. after addItem without rate).
 */
function hasBillableRate(array $item): bool {
    $cbm    = (float)($item['cbm']           ?? 0);
    $rcbm   = (float)($item['ratePerCbm']    ?? 0);
    $wt     = (float)($item['weight']        ?? 0);
    $rwt    = (float)($item['ratePerWeight'] ?? 0);
    $extra  = (float)($item['extraCharges']  ?? 0);

    // Billable if the rent is > 0 by any component
    return ($cbm * $rcbm) + ($wt * $rwt) + $extra > 0.0001;
}

/**
 * Upserts the two trnDetails lines (Dr customer, Cr clearing) for
 * a (reference, account) pair so their amount equals the sum of all
 * item rents for that account in that shipment.
 *
 * If the account has no items left in the shipment, both lines are deleted.
 */
/**
 * Upserts the two trnDetails lines (Dr customer, Cr clearing 20202028) for
 * a (reference, account) pair so their amount equals the sum of all
 * billable item rents for that account in that shipment.
 *
 * If the account has no billable items left in the shipment, both lines are deleted.
 *
 * ── Fix note ────────────────────────────────────────────────────────────
 * Every Cr row lives on account 20202028 with otherwise identical narration,
 * so a plain `trdAccount = 20202028 AND DrCr = 'Cr' AND LIKE 'Container:%'`
 * lookup collides across customer accounts on the same reference.
 *
 * To disambiguate without a schema change, the Cr narration carries an
 * internal tag ` [acct:<customerAccount>]` that the Dr row does NOT have.
 * Lookups/deletes for the Cr row match that tag, so each (ref, customer
 * account) has exactly one Cr row that is updated in place — never duplicated.
 *
 * The tag is only on the clearing (Cr) row; the customer (Dr) row stays clean.
 */
function syncAccountTrn(
    PDO $conn,
    string $ref,
    string $containerNo,
    int $account,
    int $branch
): void {
    // ── Aggregate billable totals for (ref, account) ────────────────
    // Reference lives on shipmentItemsTbl.trnReference now, not on
    // shipmentTbl.shpReference.
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt,
                COALESCE(SUM(i.qty), 0) AS totalQty,
                COALESCE(SUM(
                    (COALESCE(i.cbm, 0)    * COALESCE(i.ratePerCbm, 0)) +
                    (COALESCE(i.weight, 0) * COALESCE(i.ratePerWeight, 0)) +
                    COALESCE(i.extraCharges, 0)
                ), 0) AS totalRent,
                COALESCE(
                    (SELECT s2.sequenceNo
                       FROM shipmentTbl s2
                       JOIN shipmentItemsTbl i2 ON i2.shpId = s2.shpId
                      WHERE i2.trnReference = ?
                      LIMIT 1), ''
                ) AS sequenceNo
           FROM shipmentItemsTbl i
          WHERE i.trnReference = ?
            AND i.AccountNo   = ?
            AND (
                  (COALESCE(i.cbm, 0)    * COALESCE(i.ratePerCbm, 0))
                + (COALESCE(i.weight, 0) * COALESCE(i.ratePerWeight, 0))
                + COALESCE(i.extraCharges, 0)
                ) > 0.0001"
    );
    $stmt->execute([$ref, $ref, $account]);
    $agg = $stmt->fetch(PDO::FETCH_ASSOC)
        ?: ['cnt' => 0, 'totalQty' => 0, 'totalRent' => 0, 'sequenceNo' => ''];

    $cnt      = (int)$agg['cnt'];
    $qty      = (float)$agg['totalQty'];
    $rent     = round((float)$agg['totalRent'], 4);
    $sequence = trim((string)$agg['sequenceNo']);

    // ── Lookup keys ─────────────────────────────────────────────────
    // Dr row: unique by (trdReference, trdAccount, 'Dr').
    // Cr row: unique by (trdReference, 20202028, 'Cr', [acct:<this>]).
    $drLike = 'Cont No. :%';
    $crTag  = sprintf(' [acct:%d]', $account);   // per-customer marker on Cr
    $crLike = '%' . $crTag;

    // ── No billable items left → delete both sides for THIS account ──
    if ($cnt === 0) {
        $conn->prepare(
            "DELETE FROM trnDetails
              WHERE trdReference = ?
                AND trdAccount   = ?
                AND trdDrCr      = 'Dr'
                AND trdNarration LIKE ?"
        )->execute([$ref, $account, $drLike]);

        $conn->prepare(
            "DELETE FROM trnDetails
              WHERE trdReference = ?
                AND trdAccount   = ?
                AND trdDrCr      = 'Cr'
                AND trdNarration LIKE ?"
        )->execute([$ref, TRANSPORT_CLEARING_ACCOUNT, $crLike]);

        return;
    }

    $ccy = baseCurrency($conn);

    // Customer row: clean narration
    $drNarration = sprintf(
        'Cont No. : %s, SEQ: %s, ITEMS: %d, QTY: %s, TOTAL RENT: %s',
        $containerNo,
        $sequence !== '' ? $sequence : '—',
        $cnt,
        rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.'),
        number_format($rent, 2, '.', '')
    );

    // Clearing row: same text + hidden per-account tag
    $crNarration = $drNarration . $crTag;

    // ── Dr side: customer account ──────────────────────────────────
    $stmt = $conn->prepare(
        "SELECT trdID FROM trnDetails
          WHERE trdReference = ?
            AND trdAccount   = ?
            AND trdDrCr      = 'Dr'
            AND trdNarration LIKE ?
          LIMIT 1"
    );
    $stmt->execute([$ref, $account, $drLike]);
    $drId = $stmt->fetchColumn();

    if ($drId) {
        $conn->prepare(
            "UPDATE trnDetails
                SET trdAmount = ?, trdNarration = ?, trdCcy = ?, trdBranch = ?
              WHERE trdID = ?"
        )->execute([$rent, $drNarration, $ccy, $branch, $drId]);
    } else {
        $conn->prepare(
            "INSERT INTO trnDetails
                (trdReference, trdCcy, trdBranch, trdAccount,
                 trdDrCr, trdAmount, trdNarration, trdEntryDate)
             VALUES (?, ?, ?, ?, 'Dr', ?, ?, NOW(3))"
        )->execute([$ref, $ccy, $branch, $account, $rent, $drNarration]);
    }

    // ── Cr side: clearing 20202028, scoped to THIS account via the tag ──
    $stmt = $conn->prepare(
        "SELECT trdID FROM trnDetails
          WHERE trdReference = ?
            AND trdAccount   = ?
            AND trdDrCr      = 'Cr'
            AND trdNarration LIKE ?
          LIMIT 1"
    );
    $stmt->execute([$ref, TRANSPORT_CLEARING_ACCOUNT, $crLike]);
    $crId = $stmt->fetchColumn();

    if ($crId) {
        $conn->prepare(
            "UPDATE trnDetails
                SET trdAmount = ?, trdNarration = ?, trdCcy = ?, trdBranch = ?
              WHERE trdID = ?"
        )->execute([$rent, $crNarration, $ccy, $branch, $crId]);
    } else {
        $conn->prepare(
            "INSERT INTO trnDetails
                (trdReference, trdCcy, trdBranch, trdAccount,
                 trdDrCr, trdAmount, trdNarration, trdEntryDate)
             VALUES (?, ?, ?, ?, 'Cr', ?, ?, NOW(3))"
        )->execute([$ref, $ccy, $branch, TRANSPORT_CLEARING_ACCOUNT, $rent, $crNarration]);
    }
}
/**
 * Compute the item rent from its inputs (same formula as the SELECT in `get`).
 */
function itemRentOf(array $in): float {
    $cbm   = (float)($in['cbm']          ?? 0);
    $rcbm  = (float)($in['ratePerCbm']   ?? 0);
    $wt    = (float)($in['weight']       ?? 0);
    $rwt   = (float)($in['ratePerWeight']?? 0);
    $extra = (float)($in['extraCharges'] ?? 0);
    return round($cbm * $rcbm + $wt * $rwt + $extra, 4);
}

/**
 * Validate a GL account exists in `accounts` (chart of accounts).
 * GL control accounts are not currency-denominated, so no accountDetails check.
 */
function loadGlAccount(PDO $conn, $accNumber): ?array {
    $stmt = $conn->prepare(
        "SELECT accNumber, accName, accCategory
           FROM accounts
          WHERE accNumber = ?
          LIMIT 1"
    );
    $stmt->execute([(int)$accNumber]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

?>