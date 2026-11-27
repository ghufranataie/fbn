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

    /**
 * Build a standard narration string.
 * Format: "Cont no: MS3352352, SEQ: 354, ITEMS: 200"
 *
 * @param string $containerNo
 * @param string $seqNo
 * @param int|float $items
 * @return string
 */
private function buildNarration($containerNo, $seqNo, $items) {
    return sprintf(
        "Cont no: %s, SEQ: %s, ITEMS: %s",
        $containerNo !== null && $containerNo !== '' ? $containerNo : 'N/A',
        $seqNo       !== null && $seqNo       !== '' ? $seqNo       : 'N/A',
        $items
    );
}

/**
 * Fetch a shipment header row by shpId.
 * Returns ['containerNo' => ..., 'sequenceNo' => ...] or null.
 */
public function getShipmentHeader($shpId) {
    $stmt = $this->conn->prepare(
        "SELECT containerNo, sequenceNo FROM shipmentTbl WHERE shpId = :shpId LIMIT 1"
    );
    $stmt->execute([':shpId' => $shpId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Narration for a shipment item.
 * @param int    $shpId
 * @param int    $itemQty   quantity of the item being added/updated
 * @return string
 */
public function narrationForItem($shpId, $itemQty) {
    $hdr = $this->getShipmentHeader($shpId);
    if (!$hdr) {
        return "Cont no: N/A, SEQ: N/A, ITEMS: " . (int)$itemQty;
    }
    return $this->buildNarration($hdr['containerNo'], $hdr['sequenceNo'], (int)$itemQty);
}

/**
 * Narration for a container-level edit.
 * Uses the SUM of all items in that container as the ITEMS value.
 * @param int $shpId
 * @return string
 */
public function narrationForContainer($shpId) {
    $hdr = $this->getShipmentHeader($shpId);
    if (!$hdr) {
        return "Cont no: N/A, SEQ: N/A, ITEMS: 0";
    }
    $stmt = $this->conn->prepare(
        "SELECT COALESCE(SUM(qty), 0) FROM shipmentItemsTbl WHERE shpId = :shpId"
    );
    $stmt->execute([':shpId' => $shpId]);
    $totalItems = (int)$stmt->fetchColumn();

    return $this->buildNarration($hdr['containerNo'], $hdr['sequenceNo'], $totalItems);
}

/**
 * Update narration (and optionally amount) for every trnDetails row
 * tied to a given container's shippingPayments.
 *
 * Call this whenever containerNo / sequenceNo changes.
 * @param int $shpId
 */
public function refreshContainerNarrations($shpId): void {
    if (!$shpId) return;

    $narration = $this->narrationForContainer($shpId);

    $stmt = $this->conn->prepare(
        "SELECT sp.sPayTrnReference AS trnRef
           FROM shippingPayments sp
          WHERE sp.sPayShipping = :shpId"
    );
    $stmt->execute([':shpId' => $shpId]);
    $refs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($refs)) return;

    $placeholders = implode(',', array_fill(0, count($refs), '?'));
    $upd = $this->conn->prepare(
        "UPDATE trnDetails
            SET trdNarration = ?
          WHERE trdReference IN ($placeholders)"
    );
    $upd->execute(array_merge([$narration], $refs));
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