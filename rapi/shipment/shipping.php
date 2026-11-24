<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once "../db.php";
require_once "../functions.php";

$db   = new Database();
$conn = $db->getConnection();
$value = new DataValues($conn);

$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':    getContainer();    break;
    case 'POST':   addContainer();    break;
    case 'PUT':    updateContainer(); break;
    case 'DELETE': deleteContainer(); break;
    default:
        http_response_code(405);
        header('Allow: GET, POST, PUT, DELETE, OPTIONS');
        echo json_encode(["message" => "Method Not Allowed"]);
        break;
}

 //GET — all containers
function getContainer() {
    global $conn, $value;

    // --- Single container by one "input" (shpId OR containerNo OR sequenceNo) ---
    if (isset($_GET['input']) && trim($_GET['input']) !== '') {
        $id = trim($_GET['input']);

        $stmtCont = $conn->prepare("
            SELECT * FROM shipmentTbl
             WHERE shpId = :input
                OR containerNo = :input
                OR sequenceNo = :input
             ORDER BY entryDate DESC
             LIMIT 1
        ");
        $stmtCont->execute([':input' => $id]);
        $shipment = $stmtCont->fetch(PDO::FETCH_ASSOC);

        if (!$shipment) {
            http_response_code(404);
            echo json_encode(["msg" => "Not Found"]);
            return;
        }

        $shpId = $shipment['shpId'];

        $stmtItems = $conn->prepare("
            SELECT st.*,
                   COALESCE(st.weight / NULLIF(st.cbm, 0), 0) AS weightAVG,
                   CASE
                       WHEN st.ratePerCbm > 0
                           THEN (st.ratePerCbm * st.cbm) + st.extraCharges
                       ELSE (st.ratePerWeight * st.weight) + st.extraCharges
                   END AS itemRent,
                   per.perID, per.perCode, per.perName, per.perLastName, per.perPhone
              FROM shipmentItemsTbl st
              LEFT JOIN accounts ac        ON ac.accNumber  = st.accountNo
              LEFT JOIN accountDetails ad  ON ad.actAccount = ac.accNumber
              LEFT JOIN personal per       ON per.perID     = ad.actSignatory
             WHERE st.shpId = :shpId
        ");
        $stmtItems->execute([':shpId' => $shpId]);

        $shipment['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($shipment, JSON_PRETTY_PRINT);
        return;
    }

    // --- List containers (no pagination) ---
    $status = trim($_GET['status'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $where  = ['1=1'];
    $params = [];

    // Status filter — 'pending' rows are ALWAYS included.
    if ($status !== '' && $status !== 'all') {
        $allowed = ['pending', 'shipped', 'delivered'];
        if (!in_array($status, $allowed, true)) {
            http_response_code(400);
            echo json_encode(["msg" => "Invalid status filter"]);
            return;
        }
        $where[] = "(s.status = 'pending' OR s.status = :status)";
        $params[':status'] = $status;
    }

    if ($search !== '') {
        $where[] = '(s.containerNo LIKE :searchContainer
                     OR s.sequenceNo LIKE :searchSequence)';
        $sv = '%' . $search . '%';
        $params[':searchContainer'] = $sv;
        $params[':searchSequence']  = $sv;
    }

    if (!empty($_GET['containerNo'])) {
        $where[] = 's.containerNo LIKE :cno';
        $params[':cno'] = '%' . trim($_GET['containerNo']) . '%';
    }
    if (!empty($_GET['sequenceNo'])) {
        $where[] = 's.sequenceNo LIKE :seq';
        $params[':seq'] = '%' . trim($_GET['sequenceNo']) . '%';
    }
    if (!empty($_GET['from'])) {
        $where[] = 's.loadingDate >= :from';
        $params[':from'] = $_GET['from'];
    }
    if (!empty($_GET['to'])) {
        $where[] = 's.loadingDate <= :to';
        $params[':to'] = $_GET['to'];
    }

    $sql = "
        SELECT
            s.shpId,
            s.portName,
            s.containerNo,
            s.sequenceNo,
            s.loadingDate,
            s.unloadingDate,
            s.description,
            s.entryDate,
            s.updatedAt,
            s.createdBy,
            s.status,
            u.usrName AS createdByName,
            COUNT(i.shpItmId) AS totalItems,
            COUNT(DISTINCT i.payReference) AS totalReferences,
            COALESCE(SUM(
                (COALESCE(i.cbm, 0) * COALESCE(i.ratePerCbm, 0))
                + (COALESCE(i.weight, 0) * COALESCE(i.ratePerWeight, 0))
                + COALESCE(i.extraCharges, 0)
            ), 0) AS totalRent
        FROM shipmentTbl s
        LEFT JOIN shipmentItemsTbl i ON i.shpId = s.shpId
        LEFT JOIN users u ON u.usrID = s.createdBy
        WHERE " . implode(' AND ', $where) . "
        GROUP BY s.shpId
        ORDER BY s.entryDate DESC
    ";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
}
// Add New Container
function addContainer() {
    global $conn, $value;

    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "invalid_json"], JSON_PRETTY_PRINT);
        return;
    }

    $user         = $data['user']         ?? null;
    $portName     = $data['portName']     ?? null;
    $containerNo  = $data['containerNo']  ?? null;
    $sequenceNo   = $data['sequenceNo']   ?? null;
    $loadingDate  = $data['loadingDate']  ?? null;
    $unloadingDate= $data['unloadingDate']?? null;
    $description  = $data['description']  ?? null;
    $status       = $data['status']       ?? 'pending';

    // Validation
    if (!$user) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "missing_user"], JSON_PRETTY_PRINT);
        return;
    }
    if (!$portName || trim($portName) === '') {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "portName_required"], JSON_PRETTY_PRINT);
        return;
    }
    if (!$containerNo || trim($containerNo) === '') {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "containerNo_required"], JSON_PRETTY_PRINT);
        return;
    }
    if (!$loadingDate) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "loadingDate_required"], JSON_PRETTY_PRINT);
        return;
    }
    if (!in_array($status, ['pending', 'shipped', 'delivered'], true)) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "invalid_status"], JSON_PRETTY_PRINT);
        return;
    }

    try {
        $userResult = $value->getUserDetails($user);
        if (!$userResult) {
            echo json_encode(["msg" => "failed", "error" => "user_not_found"], JSON_PRETTY_PRINT);
            return;
        }
        $usrID = $userResult['usrID'];

        $conn->beginTransaction();

        $stmt = $conn->prepare("
            INSERT INTO shipmentTbl
                (portName, containerNo, sequenceNo, loadingDate, unloadingDate,
                 description, createdBy, status, entryDate)
            VALUES
                (:portName, :containerNo, :sequenceNo, :loadingDate, :unloadingDate,
                 :description, :createdBy, :status, NOW())
        ");
        $stmt->execute([
            ':portName'      => $portName,
            ':containerNo'   => $containerNo,
            ':sequenceNo'    => $sequenceNo,
            ':loadingDate'   => $loadingDate,
            ':unloadingDate' => $unloadingDate,
            ':description'   => $description,
            ':createdBy'     => $usrID,
            ':status'        => $status,
        ]);
        $newShpId = (int)$conn->lastInsertId();

        $conn->commit();

        $value->generateUserActivityLog(
            $user,
            "TRPT",
            "Created shipment container #$newShpId ($containerNo)"
        );

        echo json_encode([
            "msg"   => "success",
            "shpId" => $newShpId
        ], JSON_PRETTY_PRINT);

    } catch (\Throwable $th) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode([
            'msg'   => 'failed',
            'error' => $th->getMessage(),
            'line'  => $th->getLine(),
            'file'  => $th->getFile(),
        ], JSON_PRETTY_PRINT);
    }
}

 // PUT — update an existing container
function updateContainer() {
    global $conn, $value;

    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "invalid_json"], JSON_PRETTY_PRINT);
        return;
    }

    $shpId        = isset($data['shpId']) ? (int)$data['shpId'] : 0;
    $user         = $data['user']         ?? null;
    $portName     = $data['portName']     ?? null;
    $containerNo  = $data['containerNo']  ?? null;
    $sequenceNo   = $data['sequenceNo']   ?? null;
    $loadingDate  = $data['loadingDate']  ?? null;
    $unloadingDate= $data['unloadingDate']?? null;
    $description  = $data['description']  ?? null;
    $status       = $data['status']       ?? null;

    if ($shpId <= 0) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "shpId_required"], JSON_PRETTY_PRINT);
        return;
    }
    if (!$user) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "missing_user"], JSON_PRETTY_PRINT);
        return;
    }
    if ($status !== null && !in_array($status, ['pending', 'shipped', 'delivered'], true)) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "invalid_status"], JSON_PRETTY_PRINT);
        return;
    }

    try {
        // Confirm container exists
        $check = $conn->prepare("SELECT shpId FROM shipmentTbl WHERE shpId = ? LIMIT 1");
        $check->execute([$shpId]);
        if (!$check->fetchColumn()) {
            http_response_code(404);
            echo json_encode(["msg" => "failed", "error" => "container_not_found"], JSON_PRETTY_PRINT);
            return;
        }

        $userResult = $value->getUserDetails($user);
        if (!$userResult) {
            echo json_encode(["msg" => "failed", "error" => "user_not_found"], JSON_PRETTY_PRINT);
            return;
        }

        // Build dynamic SET clause (only update provided fields)
        $fields = [];
        $params = [':shpId' => $shpId];

        if ($portName !== null)      { $fields[] = 'portName = :portName';         $params[':portName']     = $portName; }
        if ($containerNo !== null)   { $fields[] = 'containerNo = :containerNo';   $params[':containerNo']  = $containerNo; }
        if ($sequenceNo !== null)    { $fields[] = 'sequenceNo = :sequenceNo';     $params[':sequenceNo']   = $sequenceNo; }
        if ($loadingDate !== null)   { $fields[] = 'loadingDate = :loadingDate';   $params[':loadingDate']  = $loadingDate; }
        if ($unloadingDate !== null) { $fields[] = 'unloadingDate = :unloadingDate'; $params[':unloadingDate'] = $unloadingDate; }
        if ($description !== null)   { $fields[] = 'description = :description';   $params[':description']  = $description; }
        if ($status !== null)        { $fields[] = 'status = :status';             $params[':status']       = $status; }

        if (empty($fields)) {
            echo json_encode(["msg" => "failed", "error" => "no_fields_to_update"], JSON_PRETTY_PRINT);
            return;
        }

        $conn->beginTransaction();

        $sql = "UPDATE shipmentTbl SET " . implode(', ', $fields) . " WHERE shpId = :shpId";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $conn->commit();

        $value->generateUserActivityLog(
            $user,
            "TRPT",
            "Updated shipment container #$shpId"
        );

        echo json_encode([
            "msg"   => "success",
            "shpId" => $shpId
        ], JSON_PRETTY_PRINT);

    } catch (\Throwable $th) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode([
            'msg'   => 'failed',
            'error' => $th->getMessage(),
            'line'  => $th->getLine(),
            'file'  => $th->getFile(),
        ], JSON_PRETTY_PRINT);
    }
}

 // DELETE — delete a container (only if empty, or force flag)
function deleteContainer() {
    global $conn, $value;

    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "invalid_json"], JSON_PRETTY_PRINT);
        return;
    }

    $shpId = isset($data['shpId']) ? (int)$data['shpId'] : 0;
    $user  = $data['user']  ?? null;
    $force = !empty($data['force']);

    if ($shpId <= 0) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "shpId_required"], JSON_PRETTY_PRINT);
        return;
    }
    if (!$user) {
        http_response_code(400);
        echo json_encode(["msg" => "failed", "error" => "missing_user"], JSON_PRETTY_PRINT);
        return;
    }

    try {
        // Confirm container exists
        $check = $conn->prepare("SELECT shpId, containerNo FROM shipmentTbl WHERE shpId = ? LIMIT 1");
        $check->execute([$shpId]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(["msg" => "failed", "error" => "container_not_found"], JSON_PRETTY_PRINT);
            return;
        }

        // Count dependent items
        $countStmt = $conn->prepare("SELECT COUNT(*) FROM shipmentItemsTbl WHERE shpId = ?");
        $countStmt->execute([$shpId]);
        $itemCount = (int)$countStmt->fetchColumn();

        if ($itemCount > 0 && !$force) {
            http_response_code(409);
            echo json_encode([
                "msg"    => "failed",
                "error"  => "container_has_items",
                "detail" => "This container has $itemCount item(s). Use force=true to delete anyway.",
                "items"  => $itemCount
            ], JSON_PRETTY_PRINT);
            return;
        }

        $conn->beginTransaction();

        if ($force && $itemCount > 0) {
            // Remove dependent item rows first
            $conn->prepare("DELETE FROM shipmentItemsTbl WHERE shpId = ?")->execute([$shpId]);
        }

        // Remove shippingPayments tied to this container
        $conn->prepare("DELETE FROM shippingPayments WHERE sPayShipping = ?")->execute([$shpId]);

        // Remove container
        $conn->prepare("DELETE FROM shipmentTbl WHERE shpId = ?")->execute([$shpId]);

        $conn->commit();

        $value->generateUserActivityLog(
            $user,
            "TRPT",
            "Deleted shipment container #$shpId ({$row['containerNo']})"
        );

        echo json_encode([
            "msg"       => "success",
            "shpId"     => $shpId,
            "forced"    => $force && $itemCount > 0,
            "itemsDeleted" => $force ? $itemCount : 0
        ], JSON_PRETTY_PRINT);

    } catch (\Throwable $th) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode([
            'msg'   => 'failed',
            'error' => $th->getMessage(),
            'line'  => $th->getLine(),
            'file'  => $th->getFile(),
        ], JSON_PRETTY_PRINT);
    }
}
?>