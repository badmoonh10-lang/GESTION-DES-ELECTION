<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');

if (!list_is_closed($pdo)) {
    http_response_code(403);
    exit('Liste non arrêtée.');
}

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit("FPDF non installé. Lance 'composer install' à la racine du projet.");
}
require_once $autoload;
use FPDF as GlobalFPDF;

$st = $pdo->query('SELECT * FROM field_agents ORDER BY matricule ASC');
$rows = $st->fetchAll();
$publicRoot = realpath(__DIR__ . '/..');

class AgentsListPDF extends GlobalFPDF {}

$pdf = new AgentsListPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, 'Liste officielle des agents de terrain', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 6, 'Generee le ' . date('d/m/Y H:i'), 0, 1, 'R');
$pdf->Ln(2);

if (!$rows) {
    $pdf->Cell(0, 8, 'Aucun agent.', 0, 1);
} else {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(0, 80, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(18, 7, 'Photo', 1, 0, 'C', true);
    $pdf->Cell(22, 7, 'Matricule', 1, 0, 'L', true);
    $pdf->Cell(35, 7, 'Nom', 1, 0, 'L', true);
    $pdf->Cell(35, 7, 'Prenom', 1, 0, 'L', true);
    $pdf->Cell(12, 7, 'Genre', 1, 0, 'C', true);
    $pdf->Cell(10, 7, 'Age', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'CNI', 1, 0, 'L', true);
    $pdf->Cell(50, 7, 'Bureau', 1, 1, 'L', true);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(20, 20, 20);

    foreach ($rows as $r) {
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->Cell(18, 14, '', 1, 0);
        $img = !empty($r['profile_photo']) ? resolve_storage_path((string)$r['profile_photo'], $publicRoot) : null;
        if ($img) {
            $pdf->Image($img, $x + 2, $y + 2, 14, 10);
        }
        $pdf->SetXY($x + 18, $y);
        $pdf->Cell(22, 14, (string)$r['matricule'], 1, 0);
        $pdf->Cell(35, 14, (string)$r['nom'], 1, 0);
        $pdf->Cell(35, 14, (string)$r['prenom'], 1, 0);
        $pdf->Cell(12, 14, (string)$r['genre'], 1, 0, 'C');
        $pdf->Cell(10, 14, (string)$r['age'], 1, 0, 'C');
        $pdf->Cell(40, 14, (string)$r['cni'], 1, 0);
        $pdf->Cell(50, 14, (string)($r['bureau_vote'] ?? ''), 1, 1);
    }
}

$pdf->Output('I', 'liste_agents_officielle.pdf');
