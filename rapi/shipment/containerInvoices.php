<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once "../db.php";
require_once "../functions.php";

$db   = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method Not Allowed"]);
    exit;
}

getContainerInvoices();

/* ============================================================
 * POST /shippingContainerInvoices.php
 * Body: { "containerNo": "EITU1055948" }  OR  { "sequenceNo": "471" }
 * Response: {
 *   "container": {...},
 *   "invoices": [ { customer, container, items, totals }, ... ]
 * }
 * ============================================================ */
function getContainerInvoices() {
    global $conn;

    $raw  = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["msg" => "Bad Request: invalid JSON body"]);
        return;
    }

    $containerNo = trim((string)($data['containerNo'] ?? ''));
    $sequenceNo  = trim((string)($data['sequenceNo']  ?? ''));

    if ($containerNo === '' && $sequenceNo === '') {
        http_response_code(400);
        echo json_encode(["msg" => "Bad Request: containerNo or sequenceNo is required"]);
        return;
    }

    // ---------- 1. Resolve container ----------
    $where = []; $params = [];
    if ($containerNo !== '') { $where[] = "s.containerNo = :c"; $params[':c'] = $containerNo; }
    if ($sequenceNo  !== '') { $where[] = "s.sequenceNo  = :s"; $params[':s'] = $sequenceNo; }

    $stmtCont = $conn->prepare("
        SELECT s.shpId, s.portName, s.containerNo, s.sequenceNo,
               s.loadingDate, s.unloadingDate, s.description,
               s.entryDate, s.status, u.usrName AS createdByName
        FROM shipmentTbl s
        LEFT JOIN users u ON u.usrID = s.createdBy
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.shpId DESC LIMIT 1
    ");
    $stmtCont->execute($params);
    $container = $stmtCont->fetch(PDO::FETCH_ASSOC);

    if (!$container) {
        http_response_code(404);
        echo json_encode(["msg" => "Not Found: no matching container"]);
        return;
    }
    $shpID = (int)$container['shpId'];

    // ---------- 2. All items in this container, ordered by account ----------
    $stmtItems = $conn->prepare("
        SELECT
            st.accountNo,
            st.shpItmId, st.itemDescription, st.qty, st.cbm, st.weight,
            COALESCE(st.weight / NULLIF(st.cbm, 0), 0) AS weightAVG,
            st.ratePerCbm, st.ratePerWeight, st.extraCharges,
            CASE WHEN st.ratePerCbm > 0
                 THEN (st.ratePerCbm * st.cbm) + st.extraCharges
                 ELSE (st.ratePerWeight * st.weight) + st.extraCharges
            END AS itemRent,
            st.status, st.entryDate, st.updatedAt
        FROM shipmentItemsTbl st
        WHERE st.shpId = :shpId
        ORDER BY st.accountNo, st.shpItmId
    ");
    $stmtItems->execute([':shpId' => $shpID]);
    $allItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    if (!$allItems) {
        http_response_code(404);
        echo json_encode(["msg" => "Not Found: no items in this container"]);
        return;
    }

    // ---------- 3. Group items by accountNo ----------
    $byAccount = [];
    foreach ($allItems as $it) {
        $acc = (string)$it['accountNo'];
        $byAccount[$acc][] = $it;
    }

    // ---------- 4. Load customer headers for all accounts (single query) ----------
    $accounts = array_keys($byAccount);
    $in     = implode(',', array_fill(0, count($accounts), '?'));
    $stmtCust = $conn->prepare("
        SELECT
            ac.accNumber AS accountNo, ac.accName AS accountName,
            ad.actCurrency AS currency, ad.actCreditLimit AS creditLimit,
            per.perID AS personID, per.perCode AS personCode,
            per.perName AS firstName, per.perLastName AS lastName,
            per.perPhone AS phone, per.perEmail AS email,
            adr.addName AS addressName, adr.addCity AS city,
            adr.addProvince AS province, adr.addCountry AS country,
            adr.addZipCode AS zipCode
        FROM accounts ac
        LEFT JOIN accountDetails ad  ON ad.actAccount = ac.accNumber
        LEFT JOIN personal       per ON per.perID     = ad.actSignatory
        LEFT JOIN address        adr ON adr.addID     = per.perAddress
        WHERE ac.accNumber IN ($in)
    ");
    $stmtCust->execute($accounts);
    $customers = [];
    foreach ($stmtCust->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $customers[(string)$c['accountNo']] = $c;
    }

    // ---------- 5. Build one invoice per account ----------
    $invoices = [];
    foreach ($byAccount as $acc => $items) {
        $cust = $customers[$acc] ?? null;
        if (!$cust) continue; // skip if account header missing

        $totalRent = 0; $totalQty = 0;
        $totalCbm = 0.0; $totalWeight = 0.0; $totalExtra = 0.0;
        foreach ($items as $it) {
            $totalRent   += (float)$it['itemRent'];
            $totalQty    += (int)  $it['qty'];
            $totalCbm    += (float)$it['cbm'];
            $totalWeight += (float)$it['weight'];
            $totalExtra  += (float)$it['extraCharges'];
        }

        // strip accountNo from item (already at invoice level)
        $cleanItems = array_map(function ($it) {
            unset($it['accountNo']);
            return $it;
        }, $items);

        $invoices[] = [
            "customer" => [
                "accountNo"   => (int)$cust['accountNo'],
                "accountName" => $cust['accountName'],
                "currency"    => $cust['currency'],
                "creditLimit" => $cust['creditLimit'],
                "person" => [
                    "id"       => $cust['personID'],
                    "code"     => $cust['personCode'],
                    "fullName" => trim(($cust['firstName'] ?? '') . ' ' . ($cust['lastName'] ?? '')),
                    "phone"    => $cust['phone'],
                    "email"    => $cust['email'],
                ],
                "address" => [
                    "name"     => $cust['addressName'],
                    "city"     => $cust['city'],
                    "province" => $cust['province'],
                    "country"  => $cust['country'],
                    "zipCode"  => $cust['zipCode'],
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
            "items"  => $cleanItems,
            "totals" => [
                "items"        => count($cleanItems),
                "qty"          => $totalQty,
                "cbm"          => round($totalCbm, 4),
                "weight"       => round($totalWeight, 3),
                "extraCharges" => round($totalExtra, 2),
                "totalRent"    => round($totalRent, 2),
                "currency"     => $cust['currency'],
            ],
        ];
    }

    echo json_encode([
        "container" => [
            "shpId"       => $shpID,
            "containerNo" => $container['containerNo'],
            "sequenceNo"  => $container['sequenceNo'],
        ],
        "count"    => count($invoices),
        "invoices" => $invoices,
        "meta"     => ["generatedAt" => date('Y-m-d H:i:s')],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}