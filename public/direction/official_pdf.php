<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit("FPDF non installé. Lance 'composer install' à la racine du projet.");
}
require_once $autoload;

use FPDF as GlobalFPDF;

$st = $pdo->query(
    "SELECT en.dossier_code, en.approved_at,
            el.nom, el.prenom, el.genre, el.age, el.cni, el.profile_photo
     FROM enrollments en
     JOIN electors el ON el.id = en.elector_id
     WHERE en.status = 'APPROVED'
     ORDER BY en.approved_at DESC"
);
$rows = $st->fetchAll();
$publicRoot = realpath(__DIR__ . '/..');

class OfficialListPDF extends GlobalFPDF {}

$pdf = new OfficialListPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, 'Liste electorale officielle', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 6, 'Generee le ' . date('d/m/Y H:i'), 0, 1, 'R');
$pdf->Ln(2);

if (!$rows) {
    $pdf->SetFont('Arial', 'I', 11);
    $pdf->Cell(0, 8, 'Aucun electeur approuve.', 0, 1, 'L');
} else {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(0, 80, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(16, 7, 'Photo', 1, 0, 'C', true);
    $pdf->Cell(24, 7, 'Dossier', 1, 0, 'L', true);
    $pdf->Cell(32, 7, 'Nom', 1, 0, 'L', true);
    $pdf->Cell(32, 7, 'Prenom', 1, 0, 'L', true);
    $pdf->Cell(12, 7, 'Genre', 1, 0, 'C', true);
    $pdf->Cell(10, 7, 'Age', 1, 0, 'C', true);
    $pdf->Cell(38, 7, 'CNI', 1, 1, 'L', true);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(20, 20, 20);

    foreach ($rows as $r) {
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->Cell(16, 12, '', 1, 0);
        $img = !empty($r['profile_photo']) ? resolve_storage_path((string)$r['profile_photo'], $publicRoot) : null;
        if ($img) {
            $pdf->Image($img, $x + 1, $y + 1, 14, 10);
        }
        $pdf->SetXY($x + 16, $y);
        $pdf->Cell(24, 12, (string)$r['dossier_code'], 1, 0);
        $pdf->Cell(32, 12, (string)$r['nom'], 1, 0);
        $pdf->Cell(32, 12, (string)$r['prenom'], 1, 0);
        $pdf->Cell(12, 12, (string)$r['genre'], 1, 0, 'C');
        $pdf->Cell(10, 12, (string)$r['age'], 1, 0, 'C');
        $pdf->Cell(38, 12, (string)$r['cni'], 1, 1);
    }
}

$filename = 'liste_electorale_officielle.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
$pdf->Output('I', $filename);
