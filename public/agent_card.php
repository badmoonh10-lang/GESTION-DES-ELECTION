<?php
require_once __DIR__ . '/../app/bootstrap.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit("FPDF non installé.");
}
require_once $autoload;

use FPDF as GlobalFPDF;

$matricule = trim((string)($_GET['matricule'] ?? ''));
if ($matricule === '') {
    http_response_code(400);
    exit('Matricule manquant.');
}

$st = $pdo->prepare(
    "SELECT fa.*, ac.lieu, ac.signature_path
     FROM field_agents fa
     LEFT JOIN agent_cards ac ON ac.field_agent_id = fa.id
     WHERE fa.matricule = ?"
);
$st->execute([$matricule]);
$row = $st->fetch();
if (!$row) {
    http_response_code(404);
    exit('Agent introuvable.');
}

$u = current_user($pdo);
if ($u) {
    if ($u['role'] === 'DIRECTION') {
        // accès total
    } elseif ($u['role'] === 'AGENT') {
        $st = $pdo->prepare('SELECT id FROM field_agents WHERE user_id = ? AND matricule = ?');
        $st->execute([(int)$u['id'], $matricule]);
        if (!$st->fetch()) {
            http_response_code(403);
            exit('Accès refusé.');
        }
    } else {
        http_response_code(403);
        exit('Accès refusé.');
    }
} elseif (!list_is_closed($pdo)) {
    http_response_code(403);
    exit('Carte non disponible.');
}

// ─── Génération QR Code (même logique que card.php électeurs) ───────────────
function generateAgentQrCodePng(array $payload): ?string
{
    $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmpFile  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_agent_' . bin2hex(random_bytes(6)) . '.png';

    // Tentative 1 : endroid/qr-code
    if (class_exists('Endroid\\QrCode\\QrCode')) {
        try {
            $qrCode = \Endroid\QrCode\QrCode::create($jsonData)
                ->setSize(300)
                ->setMargin(4);
            if (class_exists('Endroid\\QrCode\\Writer\\PngWriter')) {
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qrCode);
                file_put_contents($tmpFile, $result->getString());
                return $tmpFile;
            }
        } catch (Throwable $e) {}
    }

    // Tentative 2 : chillerlan/php-qrcode
    if (class_exists('chillerlan\\QRCode\\QRCode')) {
        try {
            $options = new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
                'scale'      => 5,
                'margin'     => 2,
            ]);
            $qr  = new \chillerlan\QRCode\QRCode($options);
            $img = $qr->render($jsonData);
            imagepng($img, $tmpFile);
            imagedestroy($img);
            return $tmpFile;
        } catch (Throwable $e) {}
    }

    // Tentative 3 : QR Server API via cURL
    if (function_exists('curl_init')) {
        try {
            $url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='
                 . urlencode($jsonData);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $pngData = curl_exec($ch);
            curl_close($ch);
            if ($pngData && strlen($pngData) > 100) {
                file_put_contents($tmpFile, $pngData);
                return $tmpFile;
            }
        } catch (Throwable $e) {}
    }

    // Tentative 4 : script Python gen_qr.py
    $scriptPath = realpath(__DIR__ . '/../scripts/gen_qr.py');
    if ($scriptPath !== false) {
        $inputJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_input_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($inputJson, $jsonData);
        $cmd = escapeshellcmd('python') . ' '
             . escapeshellarg($scriptPath) . ' '
             . escapeshellarg($inputJson)  . ' '
             . escapeshellarg($tmpFile);
        @shell_exec($cmd);
        @unlink($inputJson);
        if (is_file($tmpFile) && filesize($tmpFile) > 100) {
            return $tmpFile;
        }
    }

    return null;
}

// Payload QR de l'agent
$qrPayload = [
    'type'       => 'AGENT_TERRAIN',
    'matricule'  => $row['matricule'],
    'nom'        => $row['nom'],
    'prenom'     => $row['prenom'],
    'genre'      => $row['genre'],
    'age'        => $row['age'],
    'cni'        => $row['cni'],
    'bureau'     => $row['bureau_vote'] ?? '',
    'lieu'       => $row['lieu']        ?? 'IUT-Fv Bandjoun',
];

$qrAbs    = generateAgentQrCodePng($qrPayload);
$qrTmpGen = $qrAbs;

// ─── Résolution chemins photo & signature ────────────────────────────────────
$publicRoot = realpath(__DIR__);
$profileAbs = !empty($row['profile_photo'])
    ? resolve_storage_path((string)$row['profile_photo'], $publicRoot)
    : null;

$sigPath = (string)($row['signature_path'] ?? setting($pdo, 'card_signature_path', 'assets/img/signature.png'));
$sigAbs  = resolve_storage_path($sigPath, $publicRoot);
if (!$sigAbs && is_file(__DIR__ . '/assets/img/signature.png')) {
    $sigAbs = realpath(__DIR__ . '/assets/img/signature.png');
}

// ─── PDF FPDF ─────────────────────────────────────────────────────────────────
class AgentCardPDF extends GlobalFPDF {}

$pdf = new AgentCardPDF('L', 'mm', [85, 55]);
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);

// Fond général
$pdf->SetFillColor(245, 245, 245);
$pdf->Rect(0, 0, 85, 55, 'F');

// Bandeau supérieur bleu
$pdf->SetFillColor(0, 80, 160);
$pdf->Rect(0, 0, 85, 10, 'F');

// Titre
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(2, 2);
$pdf->Cell(60, 5, 'CARTE AGENT DE TERRAIN', 0, 0, 'C');

// Matricule (sous-titre)
$pdf->SetFont('Arial', '', 6);
$pdf->SetXY(2, 6);
$pdf->Cell(60, 3, 'Matricule : ' . $row['matricule'], 0, 0, 'C');

// Photo de profil (coin haut droit)
if ($profileAbs) {
    $pdf->Image($profileAbs, 63, 2, 18, 18);
} else {
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Rect(63, 2, 18, 18, 'F');
    $pdf->SetFont('Arial', 'I', 5);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY(63, 9);
    $pdf->MultiCell(18, 3, "Photo\nN/A", 0, 'C');
}

// Séparateur
$pdf->SetDrawColor(0, 80, 160);
$pdf->SetLineWidth(0.3);
$pdf->Line(2, 11, 60, 11);

// Champs agent
$fields = [
    ['Nom',    $row['nom']],
    ['Prenom', $row['prenom']],
    ['Genre',  $row['genre']],
    ['Age',    $row['age'] . ' ans'],
    ['CNI',    $row['cni']],
    ['Bureau', $row['bureau_vote'] ?? ''],
];

$y = 13;
foreach ($fields as [$label, $value]) {
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetTextColor(0, 80, 160);
    $pdf->SetXY(3, $y);
    $pdf->Cell(16, 3.5, $label . ' :', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetXY(19, $y);
    $pdf->Cell(41, 3.5, (string)$value, 0, 0, 'L');
    $y += 4;
}

// ── QR Code (colonne droite, sous la photo) ──────────────────────────────────
if ($qrAbs && is_file($qrAbs)) {
    $pdf->Image($qrAbs, 63, 21, 19, 19);
    $pdf->SetFont('Arial', 'I', 4.5);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY(63, 40);
    $pdf->Cell(19, 3, 'Scannez pour verifier', 0, 0, 'C');
} else {
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->SetLineWidth(0.2);
    $pdf->Rect(63, 21, 19, 19);
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY(63, 28);
    $pdf->MultiCell(19, 3, "QR\nindisponible", 0, 'C');
}

// Signature
if ($sigAbs) {
    $pdf->Image($sigAbs, 3, 42, 28, 8);
} else {
    $pdf->SetDrawColor(0, 80, 160);
    $pdf->SetLineWidth(0.2);
    $pdf->Line(3, 47, 35, 47);
}
$pdf->SetFont('Arial', 'I', 5);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetXY(3, 50);
$pdf->Cell(40, 3, 'Signature Direction', 0, 0, 'L');

// Bandeau bas
$pdf->SetFillColor(0, 80, 160);
$pdf->Rect(0, 52, 85, 3, 'F');
$pdf->SetFont('Arial', '', 4.5);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(2, 52.5);
$pdf->Cell(81, 2, 'Document officiel - Agent de terrain', 0, 0, 'C');

// Nettoyage fichier QR temporaire
if ($qrTmpGen && is_file($qrTmpGen)) {
    $tmpToDelete = $qrTmpGen;
    register_shutdown_function(function () use ($tmpToDelete) {
        if (is_file($tmpToDelete)) {
            @unlink($tmpToDelete);
        }
    });
}

$filename = 'carte_agent_' . $row['matricule'] . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
$pdf->Output('I', $filename);
