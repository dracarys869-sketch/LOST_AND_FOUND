<?php
/**
 * Reports — PDF Report (using FPDF)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');
ob_start();

$fpdfFile = BASE_PATH . '/includes/fpdf/fpdf.php';
if (!file_exists($fpdfFile)) {
    die('FPDF library not found. Please place fpdf.php in includes/fpdf/. Download from http://www.fpdf.org/');
}
require_once $fpdfFile;

$type      = sanitize($_GET['type'] ?? 'lost');
$schoolName= getSetting('school_name', APP_NAME);

// ─── Fetch Data ─────────────────────────────────────────────
switch ($type) {
    case 'found':
        $title = 'Found Item Reports';
        $rows  = db()->fetchAll(
            'SELECT fi.report_number, fi.item_name, c.name AS cat, l.name AS loc, fi.status, fi.date_found
             FROM found_items fi LEFT JOIN categories c ON fi.category_id=c.id LEFT JOIN locations l ON fi.location_id=l.id
             ORDER BY fi.created_at DESC'
        );
        $colWidths = [30, 60, 35, 35, 20, 24];
        $colHeaders= ['Report #', 'Item Name', 'Category', 'Location', 'Status', 'Date Found'];
        break;

    case 'match':
        $title = 'Item Match Report';
        $rows  = db()->fetchAll(
            'SELECT m.id, li.item_name AS lost, fi.item_name AS found_item, m.similarity_score, m.status, m.created_at
             FROM matches m LEFT JOIN lost_items li ON m.lost_item_id=li.id LEFT JOIN found_items fi ON m.found_item_id=fi.id
             ORDER BY m.similarity_score DESC'
        );
        $colWidths = [15, 60, 60, 20, 20, 30];
        $colHeaders= ['ID', 'Lost Item', 'Found Item', 'Score%', 'Status', 'Date'];
        break;

    default: // lost
        $title = 'Lost Item Reports';
        $rows  = db()->fetchAll(
            'SELECT li.report_number, li.item_name, c.name AS cat, l.name AS loc, li.status, li.date_lost
             FROM lost_items li LEFT JOIN categories c ON li.category_id=c.id LEFT JOIN locations l ON li.location_id=l.id
             ORDER BY li.created_at DESC'
        );
        $colWidths = [30, 60, 35, 35, 20, 24];
        $colHeaders= ['Report #', 'Item Name', 'Category', 'Location', 'Status', 'Date Lost'];
        break;
}

// ─── Build PDF ───────────────────────────────────────────────
class PDF extends FPDF
{
    public string $docTitle = '';
    public string $school   = '';

    public function Header()
    {
        // Logo-like colored block
        $this->SetFillColor(45, 106, 79);
        $this->Rect(10, 8, 190, 14, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 13);
        $this->SetXY(12, 11);
        $this->Cell(0, 8, $this->school . ' — Lost & Found Management System', 0, 0, 'L');

        $this->SetFont('Arial', '', 9);
        $this->SetXY(12, 19);
        $this->SetTextColor(200, 240, 220);
        $this->Cell(0, 6, $this->docTitle . ' — Generated: ' . date('M j, Y h:i A'), 0, 0, 'L');

        $this->SetTextColor(0, 0, 0);
        $this->Ln(18);
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 6, 'Page ' . $this->PageNo() . ' — Confidential', 0, 0, 'C');
    }

    public function TableHeader(array $headers, array $widths)
    {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(45, 106, 79);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(45, 106, 79);
        foreach ($headers as $i => $h) {
            $this->Cell($widths[$i], 7, $h, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
    }

    public function TableRow(array $data, array $widths, bool $alt = false)
    {
        $this->SetFont('Arial', '', 8);
        $this->SetFillColor($alt ? 245 : 255, $alt ? 252 : 255, $alt ? 247 : 255);
        $this->SetDrawColor(200, 200, 200);
        foreach ($data as $i => $cell) {
            $this->Cell($widths[$i], 6, iconv('UTF-8', 'cp1252//TRANSLIT', (string)$cell), 1, 0, 'L', true);
        }
        $this->Ln();
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->docTitle = $title;
$pdf->school   = $schoolName;
$pdf->SetCreator('School L&F System');
$pdf->SetAuthor($schoolName);
$pdf->SetTitle($title);
$pdf->AddPage();

// Summary stats box
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(232, 248, 240);
$pdf->SetDrawColor(45, 106, 79);
$pdf->Cell(0, 8, '  Total Records: ' . count($rows), 'LTRB', 1, 'L', true);
$pdf->Ln(3);

// Table
$pdf->TableHeader($colHeaders, $colWidths);
foreach ($rows as $i => $row) {
    $pdf->TableRow(array_values($row), $colWidths, $i % 2 === 1);
}

auditLog($user['id'], 'EXPORT_PDF', $type . '_report', null);
$filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', $title) . '_' . date('Y-m-d') . '.pdf';
if (ob_get_length() !== false) {
    ob_end_clean();
}
$pdf->Output('D', $filename);
exit;
