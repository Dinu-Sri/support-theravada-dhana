<?php
/**
 * Report Generation System
 * Generates comprehensive reports in CSV or PDF format
 */

session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminRequireLogin(true);

try {
    $db = getDB();
    adminRequirePermission($db, 'export_data', true);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        adminJsonResponse(['success' => false, 'error' => 'Reports must be requested with POST.'], 405);
    }
    adminRequireCsrf(null, true);
    
    // Get form parameters
    $reportType = $_POST['report_type'] ?? 'monthly';
    $format = $_POST['format'] ?? 'csv';
    $status = $_POST['report_status'] ?? 'all';
    $dhanaTypeId = $_POST['report_dhana_type'] ?? 'all';
    $includeSummary = isset($_POST['include_summary']);
    $includeAnnualEvents = isset($_POST['include_annual_events']);
    $includeCustomerDetails = isset($_POST['include_customer_details']);

    $validReportTypes = ['monthly', 'quarterly', 'custom'];
    $validFormats = ['csv', 'print'];
    $validStatuses = ['all', 'pending', 'receipt_submitted', 'payment_pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($reportType, $validReportTypes, true) ||
        !in_array($format, $validFormats, true) ||
        !in_array($status, $validStatuses, true)) {
        throw new InvalidArgumentException('Invalid report options.');
    }
    
    // Determine date range based on report type
    $startDate = '';
    $endDate = '';
    $reportTitle = '';

    switch ($reportType) {
        case 'monthly':
            $month = (int)($_POST['monthly_month'] ?? date('n'));
            $year = (int)($_POST['monthly_year'] ?? date('Y'));
            if (!checkdate($month, 1, $year) || $year < 2000 || $year > 2100) {
                throw new InvalidArgumentException('Invalid report month.');
            }
            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $endDate = date('Y-m-t', strtotime($startDate));
            $reportTitle = date('F Y', strtotime($startDate)) . ' Report';
            break;

        case 'quarterly':
            $quarter = (int)($_POST['quarterly_quarter'] ?? 1);
            $year = (int)($_POST['quarterly_year'] ?? date('Y'));
            if ($quarter < 1 || $quarter > 4 || $year < 2000 || $year > 2100) {
                throw new InvalidArgumentException('Invalid report quarter.');
            }
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $quarter * 3;
            $startDate = sprintf('%04d-%02d-01', $year, $startMonth);
            $endDate = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $endMonth)));
            $reportTitle = "Q{$quarter} {$year} Report";
            break;

        case 'custom':
            $startDate = $_POST['custom_start_date'] ?? '';
            $endDate = $_POST['custom_end_date'] ?? '';
            $start = DateTime::createFromFormat('!Y-m-d', $startDate);
            $end = DateTime::createFromFormat('!Y-m-d', $endDate);
            if (!$start || !$end || $start->format('Y-m-d') !== $startDate ||
                $end->format('Y-m-d') !== $endDate || $start > $end) {
                throw new InvalidArgumentException('Enter a valid custom date range.');
            }
            $reportTitle = 'Custom Report (' . date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate)) . ')';
            break;
    }
    
    // Build query conditions
    $whereConditions = [];
    $queryParams = [$startDate, $endDate];

    $whereConditions[] = "b.booking_date BETWEEN ? AND ?";

    if ($status !== 'all') {
        $whereConditions[] = "b.status = ?";
        $queryParams[] = $status;
    }

    if ($dhanaTypeId !== 'all') {
        $dhanaTypeId = (int)$dhanaTypeId;
        if ($dhanaTypeId < 1 || !$db->fetchOne("SELECT id FROM dhana_types WHERE id = ? AND is_active = 1", [$dhanaTypeId])) {
            throw new InvalidArgumentException('Invalid dāna type.');
        }
        $whereConditions[] = "b.dhana_type_id = ?";
        $queryParams[] = $dhanaTypeId;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Get bookings data
    $bookings = $db->fetchAll(
        "SELECT b.*, dt.name as dhana_type_name, dt.price as dhana_type_price, dt.time_slot,
                u.first_name, u.last_name, u.email, u.contact_number,
                pr.receipt_filename, pr.verified as receipt_verified, pr.payment_method,
                ab.year_start, ab.year_end,
                CASE
                    WHEN b.is_annual_event = 1 AND b.parent_booking_id IS NULL THEN 'Main Annual'
                    WHEN b.is_annual_event = 1 AND b.parent_booking_id IS NOT NULL THEN 'Annual Instance'
                    ELSE 'Regular'
                END as booking_type
         FROM bookings b
         JOIN dhana_types dt ON b.dhana_type_id = dt.id
         JOIN users u ON b.user_id = u.id
         LEFT JOIN payment_receipts pr ON pr.id = (
         SELECT pr_latest.id FROM payment_receipts pr_latest
         WHERE pr_latest.booking_id = b.id
         ORDER BY pr_latest.upload_date DESC, pr_latest.id DESC
         LIMIT 1
     )
         LEFT JOIN annual_bookings ab ON ab.booking_id = COALESCE(b.parent_booking_id, b.id)
         WHERE {$whereClause}
         ORDER BY b.booking_date DESC, b.created_at DESC",
        $queryParams
    );
    
    // Filter annual events if not included
    if (!$includeAnnualEvents) {
        $bookings = array_filter($bookings, function($booking) {
            return !$booking['is_annual_event'];
        });
    }
    
    // Generate summary statistics
    $summary = generateSummaryStats($bookings);
    
    // Generate report based on format
    if ($format === 'csv') {
        generateCSVReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails);
    } elseif ($format === 'print') {
        generatePrintReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails);
    }
    
} catch (Exception $e) {
    adminLogException('Report generation failed', $e);
    adminJsonResponse([
        'success' => false,
        'error' => $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'Unable to generate the report. Please try again.'
    ], $e instanceof InvalidArgumentException ? 400 : 500);
}

function csvSafeCell($value) {
    if (!is_string($value)) {
        return $value;
    }
    return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
}

function writeSafeCsvRow($output, $row) {
    fputcsv($output, array_map('csvSafeCell', $row));
}

function generateSummaryStats($bookings) {
    $summary = [
        'total_bookings' => count($bookings),
        'total_revenue' => 0,
        'status_breakdown' => [],
        'dhana_type_breakdown' => [],
        'monthly_breakdown' => [],
        'payment_status' => ['verified' => 0, 'unverified' => 0],
        'annual_events' => 0,
        'regular_bookings' => 0
    ];
    
    foreach ($bookings as $booking) {
        // Revenue
        $summary['total_revenue'] += $booking['total_amount'];
        
        // Status breakdown
        $status = $booking['status'];
        $summary['status_breakdown'][$status] = ($summary['status_breakdown'][$status] ?? 0) + 1;
        
        // Dhana type breakdown
        $dhanaType = $booking['dhana_type_name'];
        if (!isset($summary['dhana_type_breakdown'][$dhanaType])) {
            $summary['dhana_type_breakdown'][$dhanaType] = ['count' => 0, 'revenue' => 0];
        }
        $summary['dhana_type_breakdown'][$dhanaType]['count']++;
        $summary['dhana_type_breakdown'][$dhanaType]['revenue'] += $booking['total_amount'];
        
        // Monthly breakdown
        $month = date('Y-m', strtotime($booking['booking_date']));
        if (!isset($summary['monthly_breakdown'][$month])) {
            $summary['monthly_breakdown'][$month] = ['count' => 0, 'revenue' => 0];
        }
        $summary['monthly_breakdown'][$month]['count']++;
        $summary['monthly_breakdown'][$month]['revenue'] += $booking['total_amount'];
        
        // Payment status
        if ($booking['receipt_verified']) {
            $summary['payment_status']['verified']++;
        } else {
            $summary['payment_status']['unverified']++;
        }
        
        // Annual vs Regular
        if ($booking['is_annual_event']) {
            $summary['annual_events']++;
        } else {
            $summary['regular_bookings']++;
        }
    }
    
    return $summary;
}

function generateCSVReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails) {
    $filename = sanitizeFilename($reportTitle) . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    $output = fopen('php://output', 'w');
    
    // Report header
    writeSafeCsvRow($output, [$reportTitle]);
    writeSafeCsvRow($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    writeSafeCsvRow($output, ['Generated by: ' . $_SESSION['admin_username']]);
    writeSafeCsvRow($output, []);
    
    // Summary section
    if ($includeSummary) {
        writeSafeCsvRow($output, ['SUMMARY STATISTICS']);
        writeSafeCsvRow($output, ['Total Bookings', $summary['total_bookings']]);
        writeSafeCsvRow($output, ['Total Revenue', 'Rs. ' . number_format($summary['total_revenue'], 2)]);
        writeSafeCsvRow($output, []);
        
        // Status breakdown
        writeSafeCsvRow($output, ['STATUS BREAKDOWN']);
        foreach ($summary['status_breakdown'] as $status => $count) {
            writeSafeCsvRow($output, [ucfirst(str_replace('_', ' ', $status)), $count]);
        }
        writeSafeCsvRow($output, []);
        
        // Dāna type breakdown
        writeSafeCsvRow($output, ['DĀNA TYPE BREAKDOWN']);
        writeSafeCsvRow($output, ['Type', 'Count', 'Revenue']);
        foreach ($summary['dhana_type_breakdown'] as $type => $data) {
            writeSafeCsvRow($output, [$type, $data['count'], 'Rs. ' . number_format($data['revenue'], 2)]);
        }
        writeSafeCsvRow($output, []);
    }
    
    // Bookings data
    writeSafeCsvRow($output, ['BOOKING DETAILS']);
    
    // Headers
    $headers = ['Booking ID', 'Date', 'Dāna Type', 'Time Slot', 'Amount', 'Status', 'Booking Type'];
    if ($includeCustomerDetails) {
        $headers = array_merge($headers, ['Customer Name', 'Email', 'Contact', 'Payment Verified']);
    }
    writeSafeCsvRow($output, $headers);
    
    // Data rows
    foreach ($bookings as $booking) {
        $row = [
            '#' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT),
            date('Y-m-d', strtotime($booking['booking_date'])),
            $booking['dhana_type_name'],
            ucfirst(str_replace('_', ' ', $booking['booking_time_slot'])),
            'Rs. ' . number_format($booking['total_amount'], 2),
            ucfirst(str_replace('_', ' ', $booking['status'])),
            $booking['booking_type']
        ];
        
        if ($includeCustomerDetails) {
            $row = array_merge($row, [
                $booking['first_name'] . ' ' . $booking['last_name'],
                $booking['email'],
                $booking['contact_number'],
                $booking['receipt_verified'] ? 'Yes' : 'No'
            ]);
        }
        
        writeSafeCsvRow($output, $row);
    }
    
    fclose($output);
}

function generatePrintReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails) {
    // Generate HTML report for printing in new window
    header('Content-Type: text/html; charset=UTF-8');

    echo generateHTMLReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails);
}

function generateHTMLReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($reportTitle); ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                line-height: 1.4;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #d4822a;
                padding-bottom: 20px;
            }
            .header h1 {
                color: #d4822a;
                margin-bottom: 10px;
            }
            .header p {
                margin: 5px 0;
                color: #666;
            }
            .summary {
                margin-bottom: 30px;
                page-break-inside: avoid;
            }
            .summary h3 {
                color: #333;
                border-bottom: 2px solid #d4822a;
                padding-bottom: 5px;
                margin-top: 25px;
                margin-bottom: 15px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 12px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
                vertical-align: top;
            }
            th {
                background-color: #f8f9fa;
                font-weight: bold;
                color: #333;
            }
            .status-confirmed { color: #8b4513; font-weight: bold; }
            .status-pending { color: #d4822a; font-weight: bold; }
            .status-cancelled { color: #a0522d; font-weight: bold; }
            .status-completed { color: #b8860b; font-weight: bold; }
            .status-payment_pending { color: #cd853f; font-weight: bold; }
            .total-row { font-weight: bold; background-color: #f8f9fa; }
            .booking-id { font-family: monospace; }
            .amount { text-align: right; font-weight: bold; }
            .no-data {
                text-align: center;
                color: #666;
                font-style: italic;
                padding: 20px;
            }

            /* Print styles */
            @media print {
                body { margin: 0; }
                .header { page-break-after: avoid; }
                table { page-break-inside: avoid; }
                th { background-color: #f0f0f0 !important; }
                .status-confirmed, .status-pending, .status-cancelled,
                .status-completed, .status-payment_pending {
                    color: #000 !important;
                }
            }

            /* Responsive for smaller screens */
            @media (max-width: 768px) {
                body { margin: 10px; }
                table { font-size: 10px; }
                th, td { padding: 4px; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo htmlspecialchars($reportTitle); ?></h1>
            <p><strong>Generated on:</strong> <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p><strong>Generated by:</strong> <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
            <p><strong>Total Records:</strong> <?php echo count($bookings); ?> bookings</p>
        </div>
        
        <?php if ($includeSummary): ?>
        <div class="summary">
            <h3>Summary Statistics</h3>
            <table>
                <tr><td><strong>Total Bookings</strong></td><td><?php echo $summary['total_bookings']; ?></td></tr>
                <tr><td><strong>Total Revenue</strong></td><td>Rs. <?php echo number_format($summary['total_revenue'], 2); ?></td></tr>
            </table>
            
            <h3>Status Breakdown</h3>
            <table>
                <tr><th>Status</th><th>Count</th></tr>
                <?php foreach ($summary['status_breakdown'] as $status => $count): ?>
                <tr><td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?></td><td><?php echo (int)$count; ?></td></tr>
                <?php endforeach; ?>
            </table>
            
            <h3>Dāna Type Breakdown</h3>
            <table>
                <tr><th>Type</th><th>Count</th><th>Revenue</th></tr>
                <?php foreach ($summary['dhana_type_breakdown'] as $type => $data): ?>
                <tr><td><?php echo htmlspecialchars($type); ?></td><td><?php echo $data['count']; ?></td><td>Rs. <?php echo number_format($data['revenue'], 2); ?></td></tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <h3>Booking Details</h3>
        <?php if (empty($bookings)): ?>
            <div class="no-data">
                <p><strong>No bookings found for the selected criteria.</strong></p>
                <p>Try adjusting your date range or filters.</p>
            </div>
        <?php else: ?>
        <table>
            <tr>
                <th>Booking ID</th>
                <th>Date</th>
                <th>Dāna Type</th>
                <th>Time Slot</th>
                <th class="amount">Amount</th>
                <th>Status</th>
                <th>Type</th>
                <?php if ($includeCustomerDetails): ?>
                <th>Customer</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Payment Verified</th>
                <?php endif; ?>
            </tr>
            <?php foreach ($bookings as $booking): ?>
            <tr>
                <td class="booking-id">#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                <td><?php echo htmlspecialchars($booking['dhana_type_name']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['booking_time_slot']))); ?></td>
                <td class="amount">Rs. <?php echo number_format($booking['total_amount'], 2); ?></td>
                <?php $safeStatus = in_array($booking['status'], ['pending', 'receipt_submitted', 'payment_pending', 'confirmed', 'completed', 'cancelled'], true) ? $booking['status'] : 'pending'; ?>
                <td class="status-<?php echo $safeStatus; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $safeStatus))); ?></td>
                <td><?php echo htmlspecialchars($booking['booking_type']); ?></td>
                <?php if ($includeCustomerDetails): ?>
                <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                <td><?php echo htmlspecialchars($booking['email']); ?></td>
                <td><?php echo htmlspecialchars($booking['contact_number']); ?></td>
                <td><?php echo $booking['receipt_verified'] ? 'Yes' : 'No'; ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}
?>
