<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once "../db.php";
require_once "../functions.php";

$db    = new Database();
$conn  = $db->getConnection();
$value = new DataValues($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    echo json_encode(["message" => "Method Not Allowed"], JSON_PRETTY_PRINT);
    exit;
}

getInvoice();


/* ============================================================
 * POST /shippingInvoicePrint.php
 *
 * Body (JSON):
 *   {
 *     "accountNo":   "500000",        // REQUIRED
 *     "containerNo": "MS3352352",     // optional, but at least
 *     "sequenceNo":  "354"            // one of these REQUIRED
 *   }
 *
 * Response: customer header + container header + items + totals
 * ============================================================ */
function getInvoice() {
    global $conn, $value;

    // ---------- 1. Read + validate body ----------
    $raw  = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["msg" => "Bad Request: invalid JSON body"], JSON_PRETTY_PRINT);
        return;
    }

    $accountNo   = trim((string)($data['accountNo']   ?? ''));
    $containerNo = trim((string)($data['containerNo'] ?? ''));
    $sequenceNo  = trim((string)($data['sequenceNo']  ?? ''));

    if ($accountNo === '') {
        http_response_code(400);
        echo json_encode([
            "msg" => "Bad Request: 'accountNo' is required"
        ], JSON_PRETTY_PRINT);
        return;
    }
    if ($containerNo === '' && $sequenceNo === '') {
        http_response_code(400);
        echo json_encode([
            "msg" => "Bad Request: at least one of 'containerNo' or 'sequenceNo' is required"
        ], JSON_PRETTY_PRINT);
        return;
    }

    // ---------- 2. Load customer (account) header ----------
    $stmtCust = $conn->prepare("
        SELECT
            ac.accNumber      AS accountNo,
            ac.accName        AS accountName,
            ad.actCurrency    AS currency,
            ad.actCreditLimit AS creditLimit,
            per.perID         AS personID,
            per.perCode       AS personCode,
            per.perName       AS firstName,
            per.perLastName   AS lastName,
            per.perPhone      AS phone,
            per.perEmail      AS email,
            adr.addName       AS addressName,
            adr.addCity       AS city,
            adr.addProvince   AS province,
            adr.addCountry    AS country,
            adr.addZipCode    AS zipCode
        FROM accounts ac
        LEFT JOIN accountDetails ad  ON ad.actAccount  = ac.accNumber
        LEFT JOIN personal       per ON per.perID      = ad.actSignatory
        LEFT JOIN address        adr ON adr.addID      = per.perAddress
        WHERE ac.accNumber = :acc
        LIMIT 1
    ");
    $stmtCust->execute([':acc' => $accountNo]);
    $customer = $stmtCust->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        http_response_code(404);
        echo json_encode(["msg" => "Not Found: account does not exist"], JSON_PRETTY_PRINT);
        return;
    }

    // ---------- 3. Resolve the container (AND semantics) ----------
    $where  = [];
    $params = [];
    if ($containerNo !== '') {
        $where[] = "s.containerNo = :containerNo";
        $params[':containerNo'] = $containerNo;
    }
    if ($sequenceNo !== '') {
        $where[] = "s.sequenceNo = :sequenceNo";
        $params[':sequenceNo'] = $sequenceNo;
    }

    $stmtCont = $conn->prepare("
        SELECT
            s.shpId, s.portName, s.containerNo, s.sequenceNo,
            s.loadingDate, s.unloadingDate, s.description,
            s.entryDate, s.status,
            u.usrName AS createdByName
        FROM shipmentTbl s
        LEFT JOIN users u ON u.usrID = s.createdBy
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.shpId DESC
        LIMIT 1
    ");
    $stmtCont->execute($params);
    $container = $stmtCont->fetch(PDO::FETCH_ASSOC);

    if (!$container) {
        http_response_code(404);
        echo json_encode(["msg" => "Not Found: no matching container"], JSON_PRETTY_PRINT);
        return;
    }
    $shpID = (int)$container['shpId'];

    // ---------- 4. Load items for this customer in this container ----------
    $stmtItems = $conn->prepare("
        SELECT
            st.shpItmId,
            st.itemDescription,
            st.qty,
            st.cbm,
            st.weight,
            COALESCE(st.weight / NULLIF(st.cbm, 0), 0) AS weightAVG,
            st.ratePerCbm,
            st.ratePerWeight,
            st.extraCharges,
            CASE WHEN st.ratePerCbm > 0
                 THEN (st.ratePerCbm * st.cbm) + st.extraCharges
                 ELSE (st.ratePerWeight * st.weight) + st.extraCharges
            END AS itemRent,
            st.status,
            st.entryDate,
            st.updatedAt
        FROM shipmentItemsTbl st
        WHERE st.shpId     = :shpId
          AND st.accountNo = :acc
        ORDER BY st.shpItmId
    ");
    $stmtItems->execute([':shpId' => $shpID, ':acc' => $accountNo]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        http_response_code(404);
        echo json_encode([
            "msg" => "Not Found: no items for this customer in this container"
        ], JSON_PRETTY_PRINT);
        return;
    }

    // ---------- 5. Totals ----------
    $totalRent = $totalQty = 0;
    $totalCbm  = $totalWeight = $totalExtra = 0.0;
    foreach ($items as $it) {
        $totalRent   += (float)$it['itemRent'];
        $totalQty    += (int)  $it['qty'];
        $totalCbm    += (float)$it['cbm'];
        $totalWeight += (float)$it['weight'];
        $totalExtra  += (float)$it['extraCharges'];
    }

    $totals = [
        "items"        => count($items),
        "qty"          => $totalQty,
        "cbm"          => round($totalCbm,    4),
        "weight"       => round($totalWeight, 3),
        "extraCharges" => round($totalExtra,  2),
        "totalRent"    => round($totalRent,   2),
        "currency"     => $customer['currency'],
    ];

    // ---------- 6. Build response ----------
    $invoice = [
        "customer" => [
            "accountNo"   => (int)$customer['accountNo'],
            "accountName" => $customer['accountName'],
            "currency"    => $customer['currency'],
            "creditLimit" => $customer['creditLimit'],
            "person" => [
                "id"       => $customer['personID'],
                "code"     => $customer['personCode'],
                "fullName" => trim(($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? '')),
                "phone"    => $customer['phone'],
                "email"    => $customer['email'],
            ],
            "address" => [
                "name"     => $customer['addressName'],
                "city"     => $customer['city'],
                "province" => $customer['province'],
                "country"  => $customer['country'],
                "zipCode"  => $customer['zipCode'],
            ],
        ],
        "container" => [
            "shpId"         => $shpID,
            "portName"      => $container['portName'],
            "containerNo"   => $container['containerNo'],
            "sequenceNo"    => $container['sequenceNo'],
            "loadingDate"   => $container['loadingDate'],
            "unloadingDate" => $container['unloadingDate'],
            "description"   => $container['description'],
            "status"        => $container['status'],
            "entryDate"     => $container['entryDate'],
            "createdBy"     => $container['createdByName'],
        ],
        "items"  => $items,
        "totals" => $totals,
        "meta"   => [
            "generatedAt" => date('Y-m-d H:i:s'),
        ],
    ];

    echo json_encode($invoice, JSON_PRETTY_PRINT);
}