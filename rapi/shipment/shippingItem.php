<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once "../db.php";
require_once "../functions.php";

$db = new Database();
$conn = $db->getConnection();
$value = new DataValues($conn);

$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':    getItem();    break;
    case 'POST':   addItem();    break;
    case 'PUT':    updateItem(); break;
    case 'DELETE': deleteItem(); break;
    default:
        http_response_code(405);
        header('Allow: GET, POST, PUT, DELETE, OPTIONS');
        echo json_encode(["message" => "Method Not Allowed"]);
        break;
}

function getItem() {
    global $conn, $value;
    

    $stmtCont = $conn->prepare("SELECT * FROM shipmentTbl");
    $stmtCont->execute();
    $result = $stmtCont->fetchAll(PDO::FETCH_ASSOC);
    
    $shpID = isset($_GET['shpID']) ? $_GET['shpID'] : null;
    $stmtContItems = $conn->prepare("SELECT * FROM shipmentItemsTbl where shpId = :shpId");
    $stmtContItems->bindParam(':shpId', $shpID, PDO::PARAM_INT);
    $stmtContItems->execute();

    $resultItems = $stmtContItems->fetchAll(PDO::FETCH_ASSOC);
    $result['items'] = $resultItems;
    echo json_encode($result, JSON_PRETTY_PRINT);
}

function addItem() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        echo json_encode(["msg" => "failed", "error" => "invalid_json"], JSON_PRETTY_PRINT);
        return;
    }

    $user               = $data['user']   ?? null;
    $shpID              = $data['shpID']   ?? null;
    $itemDescription    = $data['itemDescription']   ?? null;
    $account            = $data['accountNo']   ?? null;
    $qty                = $data['qty']    ?? null;
    $cbm                = $data['cbm']    ?? null;
    $weight             = $data['weight'] ?? null;
    $ratePerCbm         = $data['ratePerCbm'] ?? null;
    $ratePerWeight      = $data['ratePerWeight'] ?? null;
    $extraCharges       = $data['extraCharges'] ?? null;

    if($ratePerCbm > 0) {
        $amount = $ratePerCbm * $cbm + $extraCharges;
    } else {
        $amount = $ratePerWeight * $weight + $extraCharges;
    }
   
    
    $valueGL = 20202020;
    $sPayType = "IN";
    $type = "TRPT";
    $narration = "Payment for shipment item $itemDescription";
    $entryDateTime = date("Y-m-d H:i:s");
    $accCcy = $value->getAccountDetails($account, 'actCurrency');
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID  = $userResult['usrID'];
    $trnRef = $value->generateTrnRef($branch, $type);
    $brclimit   = $value->getBranchAuthLimit($branch, $accCcy);
    

    $accStatus = null;
    $acclimit  = null;
    if ($account < 600000) {
        $accStatus = $value->getAccountDetails($account, 'actStatus');
        $acclimit  = $value->checkForAccountLimit($account, $amount);
    }

    if ($amount <= $brclimit) {
        $authUser  = $usrID;
        $stateText = "Authorized";
        $status    = 1;
    } else {
        $authUser  = NULL;
        $stateText = "Pending";
        $status    = 0;
    }

    try {
        $conn->beginTransaction();

        if ($acclimit != 1) {
            echo json_encode(["msg" => "over limit"], JSON_PRETTY_PRINT);
            return;
        }
        if ($accStatus != 1) {
            echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
            return;
        }

        $stmtTrn = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) 
        VALUES (?,?,?,?,?,?,?)");
        $stmtTrn->execute([$trnRef, $type, $status, $stateText, $usrID, $authUser, $entryDateTime]);

        $stmtTrnDetails = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) 
        VALUES (?,?,?,?,?,?,?,?)");
        $stmtTrnDetails->execute([$trnRef, $accCcy, $branch, $valueGL, "Cr", $amount, $narration, $entryDateTime]);
        $stmtTrnDetails->execute([$trnRef, $accCcy, $branch, $account, "Dr", $amount, $narration, $entryDateTime]);

        $stmtPay = $conn->prepare("INSERT INTO shippingPayments (sPayShipping, sPayTrnReference, sPayType) 
        VALUES (?, ?, ?)");
        $stmtPay->execute([$shpID, $trnRef, $sPayType]);
        $sPayID = $conn->lastInsertId();

        $stmtShp = $conn->prepare("INSERT INTO shipmentItemsTbl (shpId, itemDescription, accountNo, qty, cbm, weight, ratePerCbm, ratePerWeight, extraCharges, status, payReference, entryDate) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmtShp->execute([$shpID, $itemDescription, $account, $qty, $cbm, $weight, $ratePerCbm, $ratePerWeight, $extraCharges, $status, $sPayID, $entryDateTime]);        

        $conn->commit();

        $value->generateUserActivityLog(
            $user,
            $type,
            "$trnRef,\n$account,\n$amount-$accCcy"
        );

        echo json_encode([
            "msg" => "success",
            "trnRef" => $trnRef
        ]);

    } catch (\Throwable $th) {
        $conn->rollBack();
        echo json_encode([
            'msg'   => 'failed',
            'error' => $th->getMessage(),
            'line'  => $th->getLine(),
            'file'  => $th->getFile(),
        ], JSON_PRETTY_PRINT);
    }
}

function updateItem() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        echo json_encode(["msg" => "failed", "error" => "invalid_json"], JSON_PRETTY_PRINT);
        return;
    }

    $user               = $data['user']   ?? null;
    $shpID              = $data['shpID']   ?? null;
    $itemDescription    = $data['itemDescription']   ?? null;
    $account            = $data['accountNo']   ?? null;
    $qty                = $data['qty']    ?? null;
    $cbm                = $data['cbm']    ?? null;
    $weight             = $data['weight'] ?? null;
    $ratePerCbm         = $data['ratePerCbm'] ?? null;
    $ratePerWeight      = $data['ratePerWeight'] ?? null;
    $extraCharges       = $data['extraCharges'] ?? null;

    if($ratePerCbm > 0) {
        $amount = $ratePerCbm * $cbm + $extraCharges;
    } else {
        $amount = $ratePerWeight * $weight + $extraCharges;
    }
   
    
    $valueGL = 20202020;
    $sPayType = "IN";
    $type = "TRPT";
    $narration = "Payment for shipment item $itemDescription";
    $entryDateTime = date("Y-m-d H:i:s");
    $accCcy = $value->getAccountDetails($account, 'actCurrency');
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID  = $userResult['usrID'];
    $trnRef = $value->generateTrnRef($branch, $type);
    $brclimit   = $value->getBranchAuthLimit($branch, $accCcy);
    

    $accStatus = null;
    $acclimit  = null;
    if ($account < 600000) {
        $accStatus = $value->getAccountDetails($account, 'actStatus');
        $acclimit  = $value->checkForAccountLimit($account, $amount);
    }

    if ($amount <= $brclimit) {
        $authUser  = $usrID;
        $stateText = "Authorized";
        $status    = 1;
    } else {
        $authUser  = NULL;
        $stateText = "Pending";
        $status    = 0;
    }

    try {
        $conn->beginTransaction();

        if ($acclimit != 1) {
            echo json_encode(["msg" => "over limit"], JSON_PRETTY_PRINT);
            return;
        }
        if ($accStatus != 1) {
            echo json_encode(["msg" => "blocked"], JSON_PRETTY_PRINT);
            return;
        }

        $stmtUshpItem = $conn->prepare("UPDATE shipmentItemsTbl SET itemDescription=?, accountNo=?, qty=?, cbm=?, weight=?, ratePerCbm=?, ratePerWeight=?, extraCharges=?, status=? WHERE shpId=?");
        $stmtUshpItem->execute([$itemDescription, $account, $qty, $cbm, $weight, $ratePerCbm, $ratePerWeight, $extraCharges, $status, $shpID]);



        $stmtTrn = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) 
        VALUES (?,?,?,?,?,?,?)");
        $stmtTrn->execute([$trnRef, $type, $status, $stateText, $usrID, $authUser, $entryDateTime]);

        $stmtTrnDetails = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) 
        VALUES (?,?,?,?,?,?,?,?)");
        $stmtTrnDetails->execute([$trnRef, $accCcy, $branch, $valueGL, "Cr", $amount, $narration, $entryDateTime]);
        $stmtTrnDetails->execute([$trnRef, $accCcy, $branch, $account, "Dr", $amount, $narration, $entryDateTime]);

        $stmtPay = $conn->prepare("INSERT INTO shippingPayments (sPayShipping, sPayTrnReference, sPayType) 
        VALUES (?, ?, ?)");
        $stmtPay->execute([$shpID, $trnRef, $sPayType]);
        $sPayID = $conn->lastInsertId();

        $stmtShp = $conn->prepare("INSERT INTO shipmentItemsTbl (shpId, itemDescription, accountNo, qty, cbm, weight, ratePerCbm, ratePerWeight, extraCharges, status, payReference, entryDate) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmtShp->execute([$shpID, $itemDescription, $account, $qty, $cbm, $weight, $ratePerCbm, $ratePerWeight, $extraCharges, $status, $sPayID, $entryDateTime]);        

        $conn->commit();

        $value->generateUserActivityLog(
            $user,
            $type,
            "$trnRef,\n$account,\n$amount-$accCcy"
        );

        echo json_encode([
            "msg" => "success",
            "trnRef" => $trnRef
        ]);

    } catch (\Throwable $th) {
        $conn->rollBack();
        echo json_encode([
            'msg'   => 'failed',
            'error' => $th->getMessage(),
            'line'  => $th->getLine(),
            'file'  => $th->getFile(),
        ], JSON_PRETTY_PRINT);
    }
}

function deleteItem() {
    global $conn, $value;
    $data = json_decode(file_get_contents("php://input"), true);
    $result = $value->deleteShippingItem($data);
    echo json_encode($result);
}

?>