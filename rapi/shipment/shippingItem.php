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
    
    if (!isset($_GET['input']) || trim($_GET['input']) === '') {
        http_response_code(400);
        echo json_encode([
            "msg" => "Bad Request: 'input' parameter is required"
        ]);
        return;
    }
    

    $id = trim($_GET['input']);
    $stmtCont = $conn->prepare("SELECT * FROM shipmentTbl where shpID = :input or containerNo = :input or sequenceNo = :input limit 1");
    $stmtCont->execute([':input' => $id]);
    $result = $stmtCont->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        http_response_code(404);
        echo json_encode([
            "msg" => "Not Found"
        ]);
        return;
    }
    $shpID = $result['shpId'];
    
    $stmtContItems = $conn->prepare("SELECT st.*, COALESCE(st.weight / NULLIF(st.cbm, 0), 0) AS weightAVG,
        case when st.ratePerCbm > 0 then (st.ratePerCbm*st.cbm)+st.extraCharges else (st.ratePerWeight*st.weight)+st.extraCharges end as itemRent,
        per.perID, per.perCode, per.perName, per.perLastName, per.perPhone from shipmentItemsTbl st
        left join accounts ac on ac.accNumber = st.accountNo
        left join accountDetails ad on ad.actAccount = ac.accNumber
        left join personal per on per.perID = ad.actSignatory where st.shpId = :shpId");
    $stmtContItems->execute([':shpId' => $shpID]);

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

        $stmtCheckAccount = $conn->prepare("SELECT sp.sPayTrnreference, sp.sPayId FROM shipmentItemsTbl si
        join shippingPayments sp on sp.sPayId = si.payReference WHERE shpId = :shpId AND accountNo = :accountNo limit 1");
        $stmtCheckAccount->execute([':shpId' => $shpID, ':accountNo' => $account]);
        $result = $stmtCheckAccount->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $trnRef = $result['sPayTrnreference'];
            $sPayID = $result['sPayId'];
            $stmtUpdateTrnDetails = $conn->prepare("UPDATE trnDetails SET trdAmount = trdAmount + :amount WHERE trdReference = :trnRef");
            $stmtUpdateTrnDetails->execute([':amount' => $amount, ':trnRef' => $trnRef]);

            $stmtShp = $conn->prepare("INSERT INTO shipmentItemsTbl (shpId, itemDescription, accountNo, qty, cbm, weight, ratePerCbm, ratePerWeight, extraCharges, status, payReference, entryDate, updatedAt) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmtShp->execute([$shpID, $itemDescription, $account, $qty, $cbm, $weight, $ratePerCbm, $ratePerWeight, $extraCharges, $status, $sPayID, $entryDateTime, $entryDateTime]);
        
        }else {
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

            $stmtShp = $conn->prepare("INSERT INTO shipmentItemsTbl (shpId, itemDescription, accountNo, qty, cbm, weight, ratePerCbm, ratePerWeight, extraCharges, status, payReference, entryDate, updatedAt) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmtShp->execute([$shpID, $itemDescription, $account, $qty, $cbm, $weight, $ratePerCbm, $ratePerWeight, $extraCharges, $status, $sPayID, $entryDateTime, $entryDateTime]);
        }

        $conn->commit();

        $value->generateUserActivityLog(
            $user,
            $type,
            "$trnRef,\n$newAccount,\n$amount-$accCcy"
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
    $shpItemID          = $data['shpItmId']   ?? null;
    $shpID              = $data['shpID']   ?? null;
    $itemDescription    = $data['itemDescription']   ?? null;
    $newAccount         = $data['accountNo']   ?? null;
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
    $accCcy = $value->getAccountDetails($newAccount, 'actCurrency');
    $userResult = $value->getUserDetails($user);
    $branch = $userResult['usrBranch'];
    $usrID  = $userResult['usrID'];
    $trnRef = $value->generateTrnRef($branch, $type);
    $brclimit   = $value->getBranchAuthLimit($branch, $accCcy);
    

    $accStatus = null;
    $acclimit  = null;
    if ($newAccount < 600000) {
        $accStatus = $value->getAccountDetails($newAccount, 'actStatus');
        $acclimit  = $value->checkForAccountLimit($newAccount, $amount);
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

        // Get the old account number and pay reference for the item before updated
        $stmtGetItem = $conn->prepare("SELECT * FROM shipmentItemsTbl si join shippingPayments sp on sp.sPayId = si.payReference WHERE si.shpItmId = :shpItmId");
        $stmtGetItem->execute([':shpItmId' => $shpItemID]);
        $item = $stmtGetItem->fetch(PDO::FETCH_ASSOC);
        $oldAccount = $item['accountNo'];
        $payTrnReference = $item['sPayTrnReference'];
        $payRefID = $item['sPayId'];


        // Check to get pay reference if there is other items associated with the new account for the same shipment before updating the Item. If there is, we will use the existing pay reference instead of creating a new one.
        $stmtCheckPayRef = $conn->prepare("SELECT payReference FROM shipmentItemsTbl WHERE shpId = :shpId AND accountNo = :accountNo AND payReference IS NOT NULL LIMIT 1");
        $stmtCheckPayRef->execute([':shpId' => $shpID, ':accountNo' => $newAccount]);
        $existingPayRef = $stmtCheckPayRef->fetchColumn();

        // If there is no existing pay reference, we will create a new one. If there is, we will update the transaction details for the new account.
        if(!$existingPayRef) {
            // If there is no existing pay reference, we will create a new one
            $stmtTrn = $conn->prepare("INSERT INTO transactions (trnReference, trnType, trnStatus, trnStateText, trnMaker, trnAuthorizer, trnEntryDate) 
            VALUES (?,?,?,?,?,?,?)");
            $stmtTrn->execute([$trnRef, $type, $status, $stateText, $usrID, $authUser, $entryDateTime]);

            $stmtTrnDetails = $conn->prepare("INSERT INTO trnDetails (trdReference, trdCcy, trdBranch, trdAccount, trdDrCr, trdAmount, trdNarration, trdEntryDate) 
            VALUES (?,?,?,?,?,?,?,?)");
            $stmtTrnDetails->execute([$trnRef, $accCcy, $branch, $valueGL, "Cr", $amount, $narration, $entryDateTime]);
            $stmtTrnDetails->execute([$trnRef, $accCcy, $branch, $newAccount, "Dr", $amount, $narration, $entryDateTime]);

            // Insert into shippingPayments table
            $stmtPay = $conn->prepare("INSERT INTO shippingPayments (sPayShipping, sPayTrnReference, sPayType) 
            VALUES (?, ?, ?)");
            $stmtPay->execute([$shpID, $trnRef, $sPayType]);
            $existingPayRef = $conn->lastInsertId();

            // Update the shipment item with the new details
            $stmtUpdateItem = $conn->prepare("UPDATE shipmentItemsTbl SET itemDescription=?, accountNo=?, qty=?, cbm=?, weight=?, ratePerCbm=?, ratePerWeight=?, extraCharges=?, status=?, payReference=? WHERE shpItmId=?");
            $stmtUpdateItem->execute([$itemDescription, $newAccount, $qty, $cbm, $weight, $ratePerCbm, $ratePerWeight, $extraCharges, $status, $existingPayRef, $shpItemID]);
        }else {
            // Get the total amount for the New Account
            $stmtGetTotalAmount = $conn->prepare("SELECT SUM(CASE WHEN ratePerCbm > 0 THEN (ratePerCbm * cbm) + extraCharges ELSE (ratePerWeight * weight) + extraCharges END) AS totalAmount FROM shipmentItemsTbl WHERE shpId = :shpId AND accountNo = :accountNo");
            $stmtGetTotalAmount->execute([':shpId' => $shpID, ':accountNo' => $newAccount]);
            $newTotalAmount = $stmtGetTotalAmount->fetchColumn();

            // Update the transaction details for the New Account
            $stmtUpdateTrnDetails = $conn->prepare("UPDATE trnDetails SET trdAmount = :totalAmount WHERE trdReference = :trnRef AND trdAccount = :accountNo");
            $stmtUpdateTrnDetails->execute([':totalAmount' => $newTotalAmount, ':trnRef' => $payTrnReference, ':accountNo' => $newAccount]);
            $stmtUpdateTrnDetails->execute([':totalAmount' => $newTotalAmount, ':trnRef' => $payTrnReference, ':accountNo' => $valueGL]);
            
            // Update the shipment item with the new details
            $stmtUpdateItem = $conn->prepare("UPDATE shipmentItemsTbl SET itemDescription=?, accountNo=?, qty=?, cbm=?, weight=?, ratePerCbm=?, ratePerWeight=?, extraCharges=?, status=?, payReference=? WHERE shpItmId=?");
            $stmtUpdateItem->execute([$itemDescription, $newAccount, $qty, $cbm, $weight, $ratePerCbm, $ratePerWeight, $extraCharges, $status, $existingPayRef, $shpItemID]);
        }
        
        // Check if the old account has any other items associated with the same shipment. If not, we will delete the transaction details for the old account.
        $stmtCheckOldAccount = $conn->prepare("SELECT COUNT(*) FROM shipmentItemsTbl WHERE shpId = :shpId AND accountNo = :accountNo");
        $stmtCheckOldAccount->execute([':shpId' => $shpID, ':accountNo' => $oldAccount]);
        $oldAccountCount = $stmtCheckOldAccount->fetchColumn();
        
        if($oldAccountCount == 0) {
            $stmtDeleteTrnDetails = $conn->prepare("DELETE FROM trnDetails WHERE trdReference = :trnRef");
            $stmtDeleteTrnDetails->execute([':trnRef' => $payTrnReference]);

            $stmtDeleteShippingPayments = $conn->prepare("DELETE FROM shippingPayments WHERE sPayId = :payRefId");
            $stmtDeleteShippingPayments->execute([':payRefId' => $payRefID]);
        }else {
            // If the old account still has other items, we will update the transaction details for the old account
            $stmtGetOldTotalAmount = $conn->prepare("SELECT SUM(CASE WHEN ratePerCbm > 0 THEN (ratePerCbm * cbm) + extraCharges ELSE (ratePerWeight * weight) + extraCharges END) AS totalAmount FROM shipmentItemsTbl WHERE shpId = :shpId AND accountNo = :accountNo");
            $stmtGetOldTotalAmount->execute([':shpId' => $shpID, ':accountNo' => $oldAccount]);
            $oldTotalAmount = $stmtGetOldTotalAmount->fetchColumn();

            $stmtUpdateOldTrnDetails = $conn->prepare("UPDATE trnDetails SET trdAmount = :totalAmount WHERE trdReference = :trnRef AND trdAccount = :accountNo");
            $stmtUpdateOldTrnDetails->execute([':totalAmount' => $oldTotalAmount, ':trnRef' => $payTrnReference, ':accountNo' => $oldAccount]);
            $stmtUpdateOldTrnDetails->execute([':totalAmount' => $oldTotalAmount, ':trnRef' => $payTrnReference, ':accountNo' => $valueGL]);
        }

        $conn->commit();

        $value->generateUserActivityLog(
            $user,
            $type,
            "$trnRef,\n$newAccount,\n$amount-$accCcy"
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