<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once "../db.php";
require_once "../functions.php";

$db   = new Database();
$conn = $db->getConnection();
$dv   = new DataValues($conn);

if (!$conn instanceof PDO) {
    http_response_code(500);
    echo json_encode(["ok" => false, "msg" => "Database connection failed"]);
    exit;
}

/* ------------------------------------------------------------------ *
 * Helpers
 * ------------------------------------------------------------------ */
function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
function fail(string $msg, int $code = 400, array $extra = []): void {
    jsonOut(array_merge(['ok' => false, 'msg' => $msg], $extra), $code);
}
function ok(array $data = []): void {
    jsonOut(array_merge(['ok' => true], $data));
}
function body(): array {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    return is_array($in) ? $in : ($_POST ?: []);
}
function need(array $in, array $keys): void {
    foreach ($keys as $k) {
        if (!isset($in[$k]) || $in[$k] === '' || $in[$k] === null) {
            fail("Missing required field: $k");
        }
    }
}

/* ------------------------------------------------------------------ *
 * Reference helpers
 * ------------------------------------------------------------------ */

/**
 * Returns the existing trnReference for (shpId, AccountNo), or null.
 * A customer has at most ONE reference per container — all their items
 * on that container share it.
 */
function findReferenceForCustomer(PDO $conn, int $shpId, int $account): ?string {
    $stmt = $conn->prepare(
        "SELECT trnReference
           FROM shipmentItemsTbl
          WHERE shpId = ?
            AND AccountNo = ?
            AND trnReference IS NOT NULL
            AND trnReference <> ''
          LIMIT 1"
    );
    $stmt->execute([$shpId, $account]);
    $ref = $stmt->fetchColumn();
    return ($ref !== false && $ref !== null && $ref !== '') ? (string)$ref : null;
}

/**
 * Create a brand-new TRPT reference + transactions row, return it.
 * Caller must already be inside a DB transaction.
 */
function createReference(DataValues $dv, PDO $conn, int $branch, int $uid): string {
    $ref = $dv->generateTrnRef($branch, 'TRPT');
    if (!$ref) throw new RuntimeException('Could not generate reference');

    $conn->prepare(
        "INSERT INTO transactions
            (trnReference, trnType, trnStatus, trnStateText,
             trnMaker, trnAuthorizer, trnEntryDate)
         VALUES (?, 'TRPT', 1, 'Pending', ?, NULL, NOW())"
    )->execute([$ref, $uid]);

    return $ref;
}

/**
 * If a reference has zero remaining items, remove its transactions row
 * (and any trnDetails tied to it). Used after deleteItem / updateItem.
 */
function cleanupReferenceIfOrphan(PDO $conn, string $ref): void {
    if ($ref === '') return;

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM shipmentItemsTbl WHERE trnReference = ?"
    );
    $stmt->execute([$ref]);
    if ((int)$stmt->fetchColumn() > 0) return;

    // No items left — remove accounting + audit rows for this ref
    $conn->prepare("DELETE FROM trnDetails  WHERE trdReference = ?")->execute([$ref]);
    $conn->prepare("DELETE FROM transactions WHERE trnReference = ?")->execute([$ref]);
}

/* ------------------------------------------------------------------ *
 * Read body + route
 * ------------------------------------------------------------------ */
$in     = body();
$action = $_GET['action'] ?? ($in['action'] ?? '');
if ($action === '') fail('Missing action');

/* ------------------------------------------------------------------ *
 * Auth
 * ------------------------------------------------------------------ */
$username = trim((string)($_GET['usrName'] ?? $in['usrName'] ?? ''));
$uid = 0;
$branch = 1000;
$me = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($username === '') fail('Missing usrName', 401);

    $stmt = $conn->prepare(
        "SELECT usrID, usrName, usrBranch, usrRole, usrStatus
           FROM users WHERE usrName = ? LIMIT 1"
    );
    $stmt->execute([$username]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) fail('User not found', 401);
    if ((int)$me['usrStatus'] !== 1) fail('User is inactive', 403);

    $uid    = (int)$me['usrID'];
    $branch = (int)($me['usrBranch'] ?? 1000);
}

/* ------------------------------------------------------------------ *
 * Router
 * ------------------------------------------------------------------ */
try {
    switch ($action) {

    /* ============================================================
     * LIST CONTAINERS
     * GET  shipment.php?action=list
     * ============================================================ */
    case 'list': {

        $where = ['1=1'];
        $params = [];

        $status = trim((string)($_GET['status'] ?? ''));
        if ($status === '') $status = 'pending';

        if ($status !== 'all') {
            $allowedStatuses = ['pending', 'shipped', 'delivered'];
            if (!in_array($status, $allowedStatuses, true)) {
                fail('Invalid shipment status');
            }
            $where[] = 's.status = :status';
            $params[':status'] = $status;
        }

        // General search: containerNo, sequenceNo, or any trnReference on its items
        $search = trim((string)($_GET['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(
                s.containerNo LIKE :searchContainer
                OR s.sequenceNo LIKE :searchSequence
                OR EXISTS (
                    SELECT 1 FROM shipmentItemsTbl si
                     WHERE si.shpId = s.shpId
                       AND si.trnReference LIKE :searchRef
                )
            )';
            $sv = '%' . $search . '%';
            $params[':searchContainer'] = $sv;
            $params[':searchSequence']  = $sv;
            $params[':searchRef']       = $sv;
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

        $page = max(1, (int)($_GET['page'] ?? 1));
        $size = min(200, max(1, (int)($_GET['size'] ?? 50)));
        $off  = ($page - 1) * $size;

        $sql = "
            SELECT
                s.shpId,
                s.containerNo,
                s.entryDate,
                s.sequenceNo,
                s.loadingDate,
                s.unloadingDate,
                s.portName,
                s.description,
                s.status,
                s.createdBy,
                u.usrName AS createdByName,
                COUNT(i.shpItmId) AS totalItems,
                COUNT(DISTINCT i.trnReference) AS totalReferences,
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
            LIMIT :lim OFFSET :off
        ";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':lim', $size, PDO::PARAM_INT);
        $stmt->bindValue(':off', $off, PDO::PARAM_INT);
        $stmt->execute();

        ok([
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'page' => $page,
            'size' => $size,
            'statusFilter' => $status
        ]);
    }

    /* ============================================================
     * GET ONE CONTAINER (with items)
     * GET  shipment.php?action=get&shpId=1
     * ============================================================ */
    case 'get': {
        $shpId = (int)($_GET['shpId'] ?? 0);
        if ($shpId <= 0) fail('shpId required');

        $stmt = $conn->prepare("SELECT * FROM shipmentTbl WHERE shpId = ? LIMIT 1");
        $stmt->execute([$shpId]);
        $shp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$shp) fail('Container not found', 404);

        $stmt = $conn->prepare("
            SELECT
                i.*,
                p.perID, p.perCode, p.perName, p.perLastName,
                CONCAT_WS(' ',
                    NULLIF(TRIM(p.perName), ''),
                    NULLIF(TRIM(p.perLastName), '')
                ) AS fullName,
                ROUND(
                    COALESCE(i.weight, 0) / NULLIF(i.cbm, 0),
                    4
                ) AS weightAvg,
                ROUND(
                    (COALESCE(i.cbm, 0)    * COALESCE(i.ratePerCbm, 0)) +
                    (COALESCE(i.weight, 0) * COALESCE(i.ratePerWeight, 0)) +
                    COALESCE(i.extraCharges, 0),
                    2
                ) AS itemRent,
                COALESCE((
                    SELECT SUM(m.quantity) FROM shipmentItemMovements m
                    WHERE m.shpItmId = i.shpItmId AND m.movementType = 'deliver'
                ), 0) AS delivered,
                COALESCE((
                    SELECT SUM(m.quantity) FROM shipmentItemMovements m
                    WHERE m.shpItmId = i.shpItmId AND m.movementType = 'return'
                ), 0) AS returned,
                COALESCE((
                    SELECT SUM(m.quantity) FROM shipmentItemMovements m
                    WHERE m.shpItmId = i.shpItmId AND m.movementType = 'unload'
                ), 0) AS unloaded
            FROM shipmentItemsTbl i
            LEFT JOIN accountDetails ad ON ad.actAccount = i.AccountNo
            LEFT JOIN personal p        ON p.perID        = ad.actSignatory
            WHERE i.shpId = ?
            ORDER BY i.shpItmId ASC
        ");
        $stmt->execute([$shpId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("
            SELECT
                COUNT(*)                              AS totalItems,
                COUNT(DISTINCT i.trnReference)        AS totalReferences,
                COALESCE(SUM(i.qty),    0)            AS totalQty,
                COALESCE(SUM(i.cbm),    0)            AS totalCbm,
                COALESCE(SUM(i.weight), 0)            AS totalWeight,
                COALESCE(SUM(
                    (COALESCE(i.cbm, 0)    * COALESCE(i.ratePerCbm, 0)) +
                    (COALESCE(i.weight, 0) * COALESCE(i.ratePerWeight, 0)) +
                    COALESCE(i.extraCharges, 0)
                ), 0) AS totalRent
            FROM shipmentItemsTbl i
            WHERE i.shpId = ?
        ");
        $stmt->execute([$shpId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalCbm    = (float)($totals['totalCbm']    ?? 0);
        $totalWeight = (float)($totals['totalWeight'] ?? 0);

        ok([
            'shipment' => $shp,
            'items'    => $items,
            'totals'   => [
                'totalItems'         => (int)($totals['totalItems']      ?? 0),
                'totalReferences'    => (int)($totals['totalReferences'] ?? 0),
                'totalQty'           => (float)($totals['totalQty']       ?? 0),
                'totalCbm'           => $totalCbm,
                'totalWeight'        => $totalWeight,
                'totalRent'          => round((float)($totals['totalRent'] ?? 0), 2),
                'containerWeightAvg' => $totalCbm > 0
                    ? round($totalWeight / $totalCbm, 4) : 0,
            ],
        ]);
    }

    /* ============================================================
     * CREATE CONTAINER  (NO transactions row, NO reference yet)
     * POST shipment.php?action=create
     * body: { portName, containerNo, sequenceNo?, loadingDate,
     *         unloadingDate?, description? }
     * ============================================================ */
    case 'create': {
        $in = body();
        need($in, ['portName', 'containerNo', 'loadingDate']);

        $conn->prepare(
            "INSERT INTO shipmentTbl
                (createdBy, portName, containerNo, sequenceNo,
                 loadingDate, unloadingDate, description, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
        )->execute([
            $uid,
            $in['portName'],
            $in['containerNo'],
            $in['sequenceNo']    ?? null,
            $in['loadingDate'],
            $in['unloadingDate'] ?? null,
            $in['description']   ?? null,
        ]);

        $shpId = (int)$conn->lastInsertId();

        $dv->generateUserActivityLog($me['usrName'], 'Create Shipment',
            "Created container {$in['containerNo']} (shpId=$shpId)");

        ok(['shpId' => $shpId]);
    }

    /* ============================================================
     * EDIT CONTAINER HEADER
     * POST shipment.php?action=update
     * body: { shpId, portName?, containerNo?, sequenceNo?,
     *         loadingDate?, unloadingDate?, description?, status? }
     * ============================================================ */
    case 'update': {
        $in = body();
        need($in, ['shpId']);
        $shpId = (int)$in['shpId'];

        $stmt = $conn->prepare("SELECT shpId FROM shipmentTbl WHERE shpId = ? LIMIT 1");
        $stmt->execute([$shpId]);
        if (!$stmt->fetchColumn()) fail('Container not found', 404);

        $fields = [];
        $params = [':shpId' => $shpId];
        foreach (['portName','containerNo','sequenceNo','loadingDate',
                  'unloadingDate','description','status'] as $f) {
            if (array_key_exists($f, $in)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $in[$f] === '' ? null : $in[$f];
            }
        }
        if (!$fields) fail('Nothing to update');

        $conn->prepare(
            "UPDATE shipmentTbl SET " . implode(', ', $fields) . " WHERE shpId = :shpId"
        )->execute($params);

        // If containerNo or sequenceNo changed, re-sync every (reference, account)
        // pair on this container so narrations match the new header.
        $affectsNarration = array_key_exists('containerNo', $in)
                         || array_key_exists('sequenceNo',  $in);

        if ($affectsNarration) {
            $stmt = $conn->prepare("SELECT containerNo FROM shipmentTbl WHERE shpId = ? LIMIT 1");
            $stmt->execute([$shpId]);
            $containerNo = (string)$stmt->fetchColumn();

            $stmt = $conn->prepare(
                "SELECT DISTINCT trnReference, AccountNo
                   FROM shipmentItemsTbl
                  WHERE shpId = ?
                    AND trnReference IS NOT NULL
                    AND trnReference <> ''"
            );
            $stmt->execute([$shpId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                syncAccountTrn(
                    $conn,
                    (string)$row['trnReference'],
                    $containerNo,
                    (int)$row['AccountNo'],
                    (int)$branch
                );
            }
        }

        $dv->generateUserActivityLog($me['usrName'], 'Update Shipment', "Updated shipment shpId=$shpId");
        ok();
    }

    /* ============================================================
     * DELETE CONTAINER (only if no items)
     * POST shipment.php?action=delete
     * body: { shpId }
     *
     * Also removes any lingering references/transactions on the
     * container's items (defensive — normally none exist because
     * the container is empty).
     * ============================================================ */
    case 'delete': {
        $in = body();
        need($in, ['shpId']);
        $shpId = (int)$in['shpId'];

        $chk = $conn->prepare("SELECT COUNT(*) FROM shipmentItemsTbl WHERE shpId = ?");
        $chk->execute([$shpId]);
        if ((int)$chk->fetchColumn() > 0) {
            fail('Cannot delete: container already has items');
        }

        $refs = $conn->prepare(
            "SELECT DISTINCT trnReference
               FROM shipmentItemsTbl
              WHERE shpId = ?
                AND trnReference IS NOT NULL"
        );
        $refs->execute([$shpId]);
        $orphanRefs = $refs->fetchAll(PDO::FETCH_COLUMN);

        $conn->beginTransaction();
        try {
            $conn->prepare("DELETE FROM shipmentTbl WHERE shpId = ?")->execute([$shpId]);

            foreach ($orphanRefs as $r) {
                $conn->prepare("DELETE FROM trnDetails  WHERE trdReference = ?")->execute([$r]);
                $conn->prepare("DELETE FROM transactions WHERE trnReference = ?")->execute([$r]);
            }

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            fail('Failed to delete container: ' . $e->getMessage(), 500);
        }

        $dv->generateUserActivityLog($me['usrName'], 'Delete Shipment',
            "Deleted shipment shpId=$shpId");

        ok();
    }

    /* ============================================================
     * ADD ITEM
     * POST shipment.php?action=addItem
     * body: { shpId, AccountNo, itemDescription, qty,
     *         cbm?, weight?, ratePerCbm?, ratePerWeight?, extraCharges? }
     *
     * Reference resolution:
     *   - If (shpId, AccountNo) already has a trnReference → reuse it.
     *   - Else → create a new TRPT ref + transactions row, then use it.
     * ============================================================ */
    case 'addItem': {
        $in = body();
        need($in, ['shpId', 'AccountNo', 'itemDescription', 'qty']);
        if ((float)$in['qty'] <= 0) fail('qty must be > 0');

        $shpId = (int)$in['shpId'];

        $stmt = $conn->prepare(
            "SELECT shpId, containerNo FROM shipmentTbl WHERE shpId = ? LIMIT 1"
        );
        $stmt->execute([$shpId]);
        $shp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$shp) fail('Container not found', 404);

        $containerNo = (string)$shp['containerNo'];

        $account = loadAccount($conn, $in['AccountNo']);
        if (!$account) fail('Account not found: ' . (string)$in['AccountNo'], 404);

        $base = baseCurrency($conn);
        if (strcasecmp($account['accCcy'], $base) !== 0) {
            fail("Account currency ({$account['accCcy']}) does not match shipment base currency ($base)");
        }
        $accNo = (int)$account['accNumber'];

        $conn->beginTransaction();
        try {
            $ref = findReferenceForCustomer($conn, $shpId, $accNo);
            if ($ref === null) {
                $ref = createReference($dv, $conn, (int)$branch, $uid);
            }

            $conn->prepare(
                "INSERT INTO shipmentItemsTbl
                    (shpId, AccountNo, trnReference, itemDescription, qty,
                     cbm, weight, ratePerCbm, ratePerWeight, extraCharges, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            )->execute([
                $shpId,
                $accNo,
                $ref,
                $in['itemDescription'],
                $in['qty'],
                $in['cbm']           ?? null,
                $in['weight']        ?? null,
                $in['ratePerCbm']    ?? null,
                $in['ratePerWeight'] ?? null,
                $in['extraCharges']  ?? 0,
            ]);
            $itmId = (int)$conn->lastInsertId();

            syncAccountTrn($conn, $ref, $containerNo, $accNo, (int)$branch);

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            fail('Failed to add item: ' . $e->getMessage(), 500);
        }

        $dv->generateUserActivityLog(
            $me['usrName'],
            'Add Shipment Item',
            "Added item $itmId to container $shpId (ref $ref)"
        );

        ok(['shpItmId' => $itmId, 'trnReference' => $ref]);
    }

    /* ============================================================
     * UPDATE ITEM
     * POST shipment.php?action=updateItem
     * body: { shpItmId, AccountNo?, itemDescription?, qty?, cbm?,
     *         weight?, ratePerCbm?, ratePerWeight?, extraCharges? }
     * ============================================================ */
    case 'updateItem': {
        $in = body();
        need($in, ['shpItmId']);

        $itmId = (int)$in['shpItmId'];

        $stmt = $conn->prepare(
            "SELECT i.shpItmId, i.shpId, i.AccountNo, i.trnReference,
                    s.containerNo
               FROM shipmentItemsTbl i
               JOIN shipmentTbl s ON s.shpId = i.shpId
              WHERE i.shpItmId = ? LIMIT 1"
        );
        $stmt->execute([$itmId]);
        $cur = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cur) fail('Item not found', 404);

        $shpId       = (int)$cur['shpId'];
        $containerNo = (string)$cur['containerNo'];
        $oldAccount  = (int)$cur['AccountNo'];
        $oldRef      = (string)($cur['trnReference'] ?? '');
        $newAccount  = $oldAccount;

        // If AccountNo is changing, validate the new account
        if (array_key_exists('AccountNo', $in) && $in['AccountNo'] !== '') {
            $account = loadAccount($conn, $in['AccountNo']);
            if (!$account) fail('Account not found: ' . (string)$in['AccountNo'], 404);
            $base = baseCurrency($conn);
            if (strcasecmp($account['accCcy'], $base) !== 0) {
                fail("Account currency ({$account['accCcy']}) does not match shipment base currency ($base)");
            }
            $newAccount = (int)$account['accNumber'];
        }

        // Build update fields
        $allowed = ['AccountNo','itemDescription','qty','cbm','weight',
                    'ratePerCbm','ratePerWeight','extraCharges'];
        $fields = [];
        $params = [':id' => $itmId];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $in)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $in[$f] === '' ? null : $in[$f];
            }
        }
        if (!$fields) fail('Nothing to update');

        $conn->beginTransaction();
        try {
            // If the account changed, decide which reference this row should carry
            $newRef = $oldRef;
            if ($oldAccount !== $newAccount) {
                $existing = findReferenceForCustomer($conn, $shpId, $newAccount);
                if ($existing === null) {
                    $newRef = createReference($dv, $conn, (int)$branch, $uid);
                } else {
                    $newRef = $existing;
                }
                // Persist the new reference alongside the other fields
                $fields[] = "trnReference = :trnRef";
                $params[':trnRef'] = $newRef;
            }

            $sql = "UPDATE shipmentItemsTbl SET "
                 . implode(', ', $fields)
                 . ", updatedAt = NOW() WHERE shpItmId = :id";
            $conn->prepare($sql)->execute($params);

            // Re-sync old ref (if it changed) and current ref
            if ($oldAccount !== $newAccount && $oldRef !== '') {
                syncAccountTrn($conn, $oldRef, $containerNo, $oldAccount, (int)$branch);
                cleanupReferenceIfOrphan($conn, $oldRef);
            }
            syncAccountTrn($conn, $newRef, $containerNo, $newAccount, (int)$branch);

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            fail('Failed to update item: ' . $e->getMessage(), 500);
        }

        $dv->generateUserActivityLog(
            $me['usrName'],
            'Update Shipment Item',
            "Updated item $itmId (ref " . ($newRef ?? $oldRef) . ")"
        );

        ok(['trnReference' => $newRef ?? $oldRef]);
    }

    /* ============================================================
     * DELETE ITEM
     * POST shipment.php?action=deleteItem
     * body: { shpItmId }
     * ============================================================ */
    case 'deleteItem': {
        $in = body();
        need($in, ['shpItmId']);

        $itmId = (int)$in['shpItmId'];

        $stmt = $conn->prepare(
            "SELECT i.shpItmId, i.shpId, i.AccountNo, i.trnReference,
                    s.containerNo
               FROM shipmentItemsTbl i
               JOIN shipmentTbl s ON s.shpId = i.shpId
              WHERE i.shpItmId = ? LIMIT 1"
        );
        $stmt->execute([$itmId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) fail('Item not found', 404);

        $chk = $conn->prepare(
            "SELECT COUNT(*) FROM shipmentItemMovements WHERE shpItmId = ?"
        );
        $chk->execute([$itmId]);
        if ((int)$chk->fetchColumn() > 0) {
            fail('Cannot delete: item already has movements');
        }

        $ref         = (string)($row['trnReference'] ?? '');
        $containerNo = (string)$row['containerNo'];
        $account     = (int)$row['AccountNo'];

        $conn->beginTransaction();
        try {
            $conn->prepare("DELETE FROM shipmentItemsTbl WHERE shpItmId = ?")->execute([$itmId]);

            if ($ref !== '') {
                syncAccountTrn($conn, $ref, $containerNo, $account, (int)$branch);
                cleanupReferenceIfOrphan($conn, $ref);
            }

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            fail('Failed to delete item: ' . $e->getMessage(), 500);
        }

        $dv->generateUserActivityLog(
            $me['usrName'],
            'Delete Shipment Item',
            "Deleted item $itmId (ref $ref)"
        );

        ok();
    }

    /* ============================================================
     * RECORD A MOVEMENT (unload / shift / deliver / return)
     * POST shipment.php?action=move
     * body: { shpItmId, movementType, fromStorage?, toStorage?,
     *         quantity, remarks? }
     * ============================================================ */
    case 'move': {
        $in = body();
        need($in, ['shpItmId', 'movementType', 'quantity']);

        $allowed = ['unload','shift','deliver','return'];
        if (!in_array($in['movementType'], $allowed, true)) {
            fail('Invalid movementType');
        }
        $qty = (float)$in['quantity'];
        if ($qty <= 0) fail('quantity must be > 0');

        $itmId = (int)$in['shpItmId'];

        $stmt = $conn->prepare("SELECT qty FROM shipmentItemsTbl WHERE shpItmId = ? LIMIT 1");
        $stmt->execute([$itmId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) fail('Item not found', 404);
        $itemQty = (float)$row['qty'];

        if ($in['movementType'] === 'deliver') {
            $stmt = $conn->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN movementType='deliver' THEN quantity END),0) AS del,
                    COALESCE(SUM(CASE WHEN movementType='return'  THEN quantity END),0) AS ret
                 FROM shipmentItemMovements WHERE shpItmId = ?"
            );
            $stmt->execute([$itmId]);
            $m = $stmt->fetch(PDO::FETCH_ASSOC);
            $available = $itemQty - (float)$m['del'] + (float)$m['ret'];
            if ($qty > $available + 0.0001) {
                fail("Cannot deliver $qty — only $available available");
            }
        }

        $conn->prepare(
            "INSERT INTO shipmentItemMovements
                (shpItmId, movementType, fromStorage, toStorage,
                 quantity, remarks, movedBy, movedAt)
             VALUES (:i, :t, :f, :to, :q, :r, :u, NOW())"
        )->execute([
            ':i'  => $itmId,
            ':t'  => $in['movementType'],
            ':f'  => $in['fromStorage'] ?? null,
            ':to' => $in['toStorage']   ?? null,
            ':q'  => $qty,
            ':r'  => $in['remarks']     ?? null,
            ':u'  => $uid,
        ]);
        $mvId = (int)$conn->lastInsertId();

        recalcItemStatus($conn, $itmId);

        $dv->generateUserActivityLog($me['usrName'], 'Shipment Movement',
            "Item $itmId {$in['movementType']} qty $qty");
        ok(['shpItmMvId' => $mvId]);
    }

    /* ============================================================
     * UPDATE CONTAINER STATUS
     * ============================================================ */
    case 'updateStatus': {
        $in = body();
        need($in, ['shpId', 'status']);
        $allowed = ['pending','shipped','delivered'];
        if (!in_array($in['status'], $allowed, true)) fail('Invalid status');

        $conn->prepare("UPDATE shipmentTbl SET status = ? WHERE shpId = ?")
             ->execute([$in['status'], (int)$in['shpId']]);

        $dv->generateUserActivityLog($me['usrName'], 'Shipment Status',
            "Set shipment {$in['shpId']} → {$in['status']}");
        ok();
    }

    /* ============================================================
   UPDATE ITEM DELIVERY STATUS
   ============================================================ */
case 'updateItemStatus': {
    $in = body();
    need($in, ['shpItmId', 'status']);

    $itmId = (int)$in['shpItmId'];
    $status = strtolower(trim($in['status']));

    $allowed = ['pending', 'delivered'];

    if (!in_array($status, $allowed, true)) {
        fail('Invalid item status');
    }

    $stmt = $conn->prepare("
        UPDATE shipmentItemsTbl
        SET status = ?, updatedAt = NOW()
        WHERE shpItmId = ?
    ");

    $stmt->execute([$status, $itmId]);

    if ($stmt->rowCount() === 0) {
        $check = $conn->prepare("
            SELECT shpItmId
            FROM shipmentItemsTbl
            WHERE shpItmId = ?
        ");
        $check->execute([$itmId]);

        if (!$check->fetch()) {
            fail('Shipment item not found');
        }
    }

    $dv->generateUserActivityLog(
        $me['usrName'],
        'Shipment Item Status',
        "Set shipment item {$itmId} → {$status}"
    );

    ok(['status' => $status]);
}

    /* ============================================================
     * LIST MOVEMENTS
     * ============================================================ */
    case 'movements': {
        $where = [];
        $params = [];
        if (!empty($_GET['shpItmId'])) {
            $where[] = 'm.shpItmId = :i';
            $params[':i'] = (int)$_GET['shpItmId'];
        }
        if (!empty($_GET['shpId'])) {
            $where[] = 'it.shpId = :s';
            $params[':s'] = (int)$_GET['shpId'];
        }
        if (!$where) fail('shpItmId or shpId required');

        $sql = "SELECT m.*, u.usrName AS movedByName
                  FROM shipmentItemMovements m
                  JOIN shipmentItemsTbl it ON it.shpItmId = m.shpItmId
                  LEFT JOIN users u        ON u.usrID    = m.movedBy
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY m.movedAt DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        ok(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /* ============================================================
     * ITEMS OF A CONTAINER
     * ============================================================ */
    case 'items': {
        $shpId = (int)($_GET['shpId'] ?? 0);
        if ($shpId <= 0) fail('shpId required');

        $stmt = $conn->prepare(
            "SELECT i.*,
                p.perID,
                p.perCode,
                TRIM(CONCAT_WS(' ',
                    NULLIF(p.perName, ''),
                    NULLIF(p.perLastName, '')
                )) AS customerName,
                a.accNumber,
                a.accName,
                c.ccyCode AS accountCurrency,
                CASE
                    WHEN COALESCE(i.cbm, 0) > 0
                    THEN ROUND(i.weight / i.cbm, 3)
                    ELSE 0
                END AS weightAverage
            FROM shipmentItemsTbl i
            LEFT JOIN accounts a        ON a.accNumber  = i.AccountNo
            LEFT JOIN accountDetails ad ON ad.actAccount = i.AccountNo
            LEFT JOIN personal p        ON p.perID       = ad.actSignatory
            LEFT JOIN currency c        ON c.ccyCode     = ad.actCurrency
            WHERE i.shpId = ?
            ORDER BY i.shpItmId ASC"
        );
        $stmt->execute([$shpId]);
        ok(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    default:
        fail('Unknown action: ' . $action, 404);
    }
} catch (\Throwable $th) {
    if ($conn->inTransaction()) $conn->rollBack();
    fail($th->getMessage(), 500, ['line' => $th->getLine(), 'file' => $th->getFile()]);
}

/* ------------------------------------------------------------------ *
 * Helper: recompute item status from its movements
 * ------------------------------------------------------------------ */
function recalcItemStatus(PDO $conn, int $itmId): string {
    $stmt = $conn->prepare(
        "SELECT i.qty,
                COALESCE(SUM(CASE WHEN m.movementType='deliver' THEN m.quantity END),0) AS del
           FROM shipmentItemsTbl i
           LEFT JOIN shipmentItemMovements m ON m.shpItmId = i.shpItmId
          WHERE i.shpItmId = ?
          GROUP BY i.shpItmId"
    );
    $stmt->execute([$itmId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return 'pending';

    $qty = (float)$r['qty'];
    $del = (float)$r['del'];

    if ($del <= 0)                $st = 'pending';
    elseif ($del < $qty - 0.0001) $st = 'partiallyDelivered';
    else                          $st = 'delivered';

    $conn->prepare("UPDATE shipmentItemsTbl SET status = ?, updatedAt = NOW() WHERE shpItmId = ?")
         ->execute([$st, $itmId]);
    return $st;
}