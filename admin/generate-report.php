<?php
/**
 * Report Generation System
 * Generates comprehensive reports in CSV or PDF format
 */

session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = getDB();
    
    // Get form parameters
    $reportType = $_POST['report_type'] ?? 'monthly';
    $format = $_POST['format'] ?? 'csv';
    $status = $_POST['report_status'] ?? 'all';
    $dhanaTypeId = $_POST['report_dhana_type'] ?? 'all';
    $includeSummary = isset($_POST['include_summary']);
    $includeAnnualEvents = isset($_POST['include_annual_events']);
    $includeCustomerDetails = isset($_POST['include_customer_details']);

    // Debug: Log the received parameters
    error_log("Report Type: " . $reportType);
    error_log("Format: " . $format);
    error_log("Status: " . $status);
    error_log("Dhana Type ID: " . $dhanaTypeId);
    
    // Determine date range based on report type
    $startDate = '';
    $endDate = '';
    $reportTitle = '';

    switch ($reportType) {
        case 'monthly':
            $month = (int)($_POST['monthly_month'] ?? date('n'));
            $year = (int)($_POST['monthly_year'] ?? date('Y'));
            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $endDate = date('Y-m-t', strtotime($startDate));
            $reportTitle = date('F Y', strtotime($startDate)) . ' Report';
            error_log("Monthly Report - Month: $month, Year: $year, Start: $startDate, End: $endDate");
            break;

        case 'quarterly':
            $quarter = (int)($_POST['quarterly_quarter'] ?? 1);
            $year = (int)($_POST['quarterly_year'] ?? date('Y'));
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $quarter * 3;
            $startDate = sprintf('%04d-%02d-01', $year, $startMonth);
            $endDate = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $endMonth)));
            $reportTitle = "Q{$quarter} {$year} Report";
            error_log("Quarterly Report - Quarter: $quarter, Year: $year, Start: $startDate, End: $endDate");
            break;

        case 'custom':
            $startDate = $_POST['custom_start_date'] ?? '';
            $endDate = $_POST['custom_end_date'] ?? '';
            if (!$startDate || !$endDate) {
                throw new Exception('Start and end dates are required for custom reports');
            }
            $reportTitle = 'Custom Report (' . date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate)) . ')';
            error_log("Custom Report - Start: $startDate, End: $endDate");
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
        $whereConditions[] = "b.dhana_type_id = ?";
        $queryParams[] = $dhanaTypeId;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Debug: Log the query conditions
    error_log("Where Clause: " . $whereClause);
    error_log("Query Params: " . print_r($queryParams, true));
    
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
         LEFT JOIN payment_receipts pr ON b.id = pr.booking_id
         LEFT JOIN annual_bookings ab ON (b.id = ab.booking_id OR b.parent_booking_id = ab.booking_id)
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
    
    // Debug: Log the number of bookings found
    error_log("Number of bookings found: " . count($bookings));
    if (count($bookings) > 0) {
        error_log("First booking date: " . $bookings[0]['booking_date']);
        error_log("Last booking date: " . end($bookings)['booking_date']);
    }

    // Generate report based on format
    if ($format === 'csv') {
        generateCSVReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails);
    } elseif ($format === 'print') {
        generatePrintReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails);
    } else {
        generatePDFReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error generating report: ' . $e->getMessage();
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
    fputcsv($output, [$reportTitle]);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Generated by: ' . $_SESSION['admin_username']]);
    fputcsv($output, []);
    
    // Summary section
    if ($includeSummary) {
        fputcsv($output, ['SUMMARY STATISTICS']);
        fputcsv($output, ['Total Bookings', $summary['total_bookings']]);
        fputcsv($output, ['Total Revenue', 'Rs. ' . number_format($summary['total_revenue'], 2)]);
        fputcsv($output, []);
        
        // Status breakdown
        fputcsv($output, ['STATUS BREAKDOWN']);
        foreach ($summary['status_breakdown'] as $status => $count) {
            fputcsv($output, [ucfirst(str_replace('_', ' ', $status)), $count]);
        }
        fputcsv($output, []);
        
        // Dāna type breakdown
        fputcsv($output, ['DĀNA TYPE BREAKDOWN']);
        fputcsv($output, ['Type', 'Count', 'Revenue']);
        foreach ($summary['dhana_type_breakdown'] as $type => $data) {
            fputcsv($output, [$type, $data['count'], 'Rs. ' . number_format($data['revenue'], 2)]);
        }
        fputcsv($output, []);
    }
    
    // Bookings data
    fputcsv($output, ['BOOKING DETAILS']);
    
    // Headers
    $headers = ['Booking ID', 'Date', 'Dāna Type', 'Time Slot', 'Amount', 'Status', 'Booking Type'];
    if ($includeCustomerDetails) {
        $headers = array_merge($headers, ['Customer Name', 'Email', 'Contact', 'Payment Verified']);
    }
    fputcsv($output, $headers);
    
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
        
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function generatePrintReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails) {
    // Generate HTML report for printing in new window
    header('Content-Type: text/html; charset=UTF-8');

    echo generateHTMLReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails);
}

function generatePDFReport($bookings, $summary, $reportTitle, $includeSummary, $includeCustomerDetails) {
    // For PDF generation, we'll create an HTML version and suggest using a PDF library
    // This is a simplified version - in production, you'd use libraries like TCPDF or DOMPDF

    $filename = sanitizeFilename($reportTitle) . '_' . date('Y-m-d') . '.html';

    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

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
                <tr><td><?php echo ucfirst(str_replace('_', ' ', $status)); ?></td><td><?php echo $count; ?></td></tr>
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
                <td><?php echo ucfirst(str_replace('_', ' ', $booking['booking_time_slot'])); ?></td>
                <td class="amount">Rs. <?php echo number_format($booking['total_amount'], 2); ?></td>
                <td class="status-<?php echo $booking['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?></td>
                <td><?php echo $booking['booking_type']; ?></td>
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
