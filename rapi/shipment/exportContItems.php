<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once "../db.php";
require_once "../functions.php";

// ── EXPORT CONTAINER ITEMS TO CSV ────────────────────────────────────────────
function exportContainerItems() {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Validate Input
    if (!isset($_GET['input']) || trim($_GET['input']) === '') {
        http_response_code(400);
        echo "Missing 'input' parameter.";
        return;
    }

    $id = trim($_GET['input']);
    
    // 2. Fetch Container Header
    $stmtCont = $conn->prepare("SELECT * FROM shipmentTbl where shpID = :input or containerNo = :input or sequenceNo = :input limit 1");
    $stmtCont->execute([':input' => $id]);
    $container = $stmtCont->fetch(PDO::FETCH_ASSOC);

    if (!$container) {
        http_response_code(404);
        echo "Container not found.";
        return;
    }
    $shpID = $container['shpId'];

    // 3. Fetch Items (Identical query to your getItem method)
    $stmtContItems = $conn->prepare("SELECT st.*, COALESCE(st.weight / NULLIF(st.cbm, 0), 0) AS weightAVG,
        case when st.ratePerCbm > 0 then (st.ratePerCbm*st.cbm)+st.extraCharges else (st.ratePerWeight*st.weight)+st.extraCharges end as itemRent,
        per.perID, per.perCode, per.perName, per.perLastName, per.perPhone from shipmentItemsTbl st
        left join accounts ac on ac.accNumber = st.accountNo
        left join accountDetails ad on ad.actAccount = ac.accNumber
        left join personal per on per.perID = ad.actSignatory where st.shpId = :shpId");
    $stmtContItems->execute([':shpId' => $shpID]);
    $items = $stmtContItems->fetchAll(PDO::FETCH_ASSOC);

    // 4. Set CSV Headers to force download
    $filename = "Container_" . ($container['containerNo'] ?? $shpID) . "_Items_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // 5. Open PHP Output Stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel (Crucial for Dari/Arabic characters and currency symbols)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // 6. Container Summary Info (Top of Sheet)
    fputcsv($output, ['CONTAINER INFORMATION']);
    fputcsv($output, ['Container No', $container['containerNo'] ?? '—', '', 'Sequence No', $container['sequenceNo'] ?? '—']);
    fputcsv($output, ['Port', $container['portName'] ?? '—', '', 'Status', strtoupper($container['status'] ?? 'PENDING')]);
    fputcsv($output, ['Loading Date', $container['loadingDate'] ?? '—', '', 'Unloading Date', $container['unloadingDate'] ?? '—']);
    fputcsv($output, ['Description', $container['description'] ?? '—']);
    fputcsv($output, []); // Empty line for spacing

    // 7. Table Headers
    fputcsv($output, [
        '#',
        'Item Description',
        'Customer Name',
        'Account No',
        'Qty',
        'CBM',
        'Weight (kg)',
        'Weight Avg',
        'Rate/CBM',
        'Rate/kg',
        'Extra Charges',
        'Rent',
        'Status'
    ]);

    // 8. Initialize Totals for Summary
    $totalQty = 0;
    $totalCbm = 0;
    $totalWeight = 0;
    $totalRent = 0;
    $totalPendingQty = 0;
    $totalDeliveredQty = 0;

    // 9. Loop through items and write to CSV
    $index = 1;
    foreach ($items as $item) {
        $qty = (float) ($item['qty'] ?? 0);
        $cbm = (float) ($item['cbm'] ?? 0);
        $weight = (float) ($item['weight'] ?? 0);
        $weightAVG = (float) ($item['weightAVG'] ?? 0);
        $rent = (float) ($item['itemRent'] ?? 0);
        $status = strtolower($item['status'] ?? 'pending');

        // Accumulate totals
        $totalQty += $qty;
        $totalCbm += $cbm;
        $totalWeight += $weight;
        $totalRent += $rent;
        
        if ($status === 'delivered') {
            $totalDeliveredQty += $qty;
        } else {
            $totalPendingQty += $qty;
        }

        $customerName = trim(($item['perName'] ?? '') . ' ' . ($item['perLastName'] ?? ''));
        
        fputcsv($output, [
            $index++,
            $item['itemDescription'],
            $customerName,
            $item['accountNo'],
            $qty,
            $cbm,
            $weight,
            $weightAVG,
            $item['ratePerCbm'],
            $item['ratePerWeight'],
            $item['extraCharges'],
            $rent,
            strtoupper($status)
        ]);
    }

    // 10. Empty Line before Summary
    fputcsv($output, []);

    // 11. Summary Section
    $containerWeightAvg = $totalCbm > 0 ? ($totalWeight / $totalCbm) : 0;

    fputcsv($output, ['', '', 'SUMMARY']);
    fputcsv($output, ['', '', 'Total Items:', count($items), 'Total Qty:', $totalQty]);
    fputcsv($output, ['', '', 'Total Weight:', $totalWeight, 'Total CBM:', $totalCbm]);
    fputcsv($output, ['', '', 'Weight Avg:', number_format($containerWeightAvg, 2), 'Delivered Qty:', $totalDeliveredQty]);
    fputcsv($output, ['', '', 'Pending Qty:', $totalPendingQty]);
    fputcsv($output, []); // Empty line
    fputcsv($output, ['', '', 'TOTAL RENT:', number_format($totalRent, 2)]);

    fclose($output);
    exit;
}

// Execute the function
exportContainerItems();
?>