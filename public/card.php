<?php
require_once __DIR__ . '/../app/bootstrap.php';

// FPDF via Composer
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit("FPDF non installé. Lance 'composer install' à la racine du projet.");
}
require_once $autoload;

use FPDF as GlobalFPDF;

$dossier = trim((string)($_GET['dossier'] ?? ''));
if ($dossier === '') {
    http_response_code(400);
    exit('Paramètre dossier manquant.');
}

$u = current_user($pdo);
$cniProvided = trim((string)($_GET['cni'] ?? ''));

// Charge dossier + vérifie APPROVED
$st = $pdo->prepare(
    "SELECT en.id AS enrollment_id, en.dossier_code, en.status,
            el.user_id, el.nom, el.prenom, el.genre, el.age, el.cni, el.profile_photo,
            c.lieu, c.signature_path, c.qr_code
     FROM enrollments en
     JOIN electors el ON el.id = en.elector_id
     LEFT JOIN cards c ON c.enrollment_id = en.id
     WHERE en.dossier_code = ?"
);
$st->execute([$dossier]);
$row = $st->fetch();
if (!$row) {
    http_response_code(404);
    exit('Dossier introuvable.');
}
if ($row['status'] !== 'APPROVED') {
    http_response_code(403);
    exit('Carte non disponible (dossier non approuvé).');
}

// Contrôle d'accès
if ($u) {
    if ($u['role'] === 'ELECTOR' && (int)$row['user_id'] !== (int)$u['id']) {
        http_response_code(403);
        exit('Accès refusé.');
    }
} else {
    if ($cniProvided === '' || !hash_equals((string)$row['cni'], $cniProvided)) {
        http_response_code(403);
        exit('Accès refusé (CNI requis).');
    }
}


function generateQrCodePng(array $payload): ?string
{
    $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmpFile  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_elec_' . bin2hex(random_bytes(6)) . '.png';

    // Tentative 1 : endroid/qr-code (^4 ou ^5)
    if (class_exists('Endroid\QrCode\QrCode')) {
        try {
            $qrCode = \Endroid\QrCode\QrCode::create($jsonData)
                ->setSize(300)
                ->setMargin(4);

            if (class_exists('Endroid\QrCode\Writer\PngWriter')) {
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qrCode);
                file_put_contents($tmpFile, $result->getString());
                return $tmpFile;
            }
        } catch (Throwable $e) { /* on essaie le suivant */ }
    }

    // Tentative 2 : chillerlan/php-qrcode
    if (class_exists('chillerlan\QRCode\QRCode')) {
        try {
            $options = new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
                'scale'      => 5,
                'margin'     => 2,
            ]);
            $qr  = new \chillerlan\QRCode\QRCode($options);
            $img = $qr->render($jsonData);           // retourne image GD
            imagepng($img, $tmpFile);
            imagedestroy($img);
            return $tmpFile;
        } catch (Throwable $e) { /* on essaie le suivant */ }
    }

    // Tentative 3 : génération GD manuelle via QR Server API (si curl disponible)
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
        } catch (Throwable $e) { /* échec silencieux */ }
    }

    // Tentative 4 : script Python gen_qr.py (conservé comme dernier recours)
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

    return null;  // impossible de générer le QR
}

// Construit le payload complet de l'électeur
$qrPayload = [
    'dossier'       => $row['dossier_code'],
    'nom'           => $row['nom'],
    'prenom'        => $row['prenom'],
    'genre'         => $row['genre'],
    'age'           => $row['age'],
    'cni'           => $row['cni'],
    'lieu'          => $row['lieu'] ?? 'Centre de L\'IUT-FV Bandjoun',
    'statut'        => $row['status'],
    'enrollment_id' => $row['enrollment_id'],
];

// Résolution photo de profil
$profileAbs = null;
if (!empty($row['profile_photo'])) {
    $candidate = __DIR__ . '/../' . ltrim((string)$row['profile_photo'], '/');
    if (is_file($candidate)) {
        $resolved   = realpath($candidate);
        $profileAbs = $resolved !== false ? $resolved : $candidate;
    }
}

$qrAbs    = generateQrCodePng($qrPayload);
$qrTmpGen = $qrAbs; // pour nettoyage ultérieur si fichier temp

// ─────────────────────────────────────────────────────────────────────────────
// GÉNÉRATION PDF FPDF
// ─────────────────────────────────────────────────────────────────────────────

class CardPDF extends GlobalFPDF {}

$pdf = new CardPDF('L', 'mm', [85, 55]);
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);

// ── Fond général
$pdf->SetFillColor(245, 245, 245);
$pdf->Rect(0, 0, 85, 55, 'F');

// ── Bandeau supérieur bleu
$pdf->SetFillColor(0, 80, 160);
$pdf->Rect(0, 0, 85, 10, 'F');

// ── Titre
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(2, 2);
$pdf->Cell(60, 5, 'CARTE D\'ELECTEUR', 0, 0, 'C');

// ── Numéro de dossier (en-tête)
$pdf->SetFont('Arial', '', 6);
$pdf->SetXY(2, 6);
$pdf->Cell(60, 3, 'N Dossier : ' . $row['dossier_code'], 0, 0, 'C');

// ── Photo de profil (coin haut-droit du bandeau + débordement)
if ($profileAbs && is_file($profileAbs)) {
    $pdf->Image($profileAbs, 63, 2, 18, 18);
} else {
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Rect(63, 2, 18, 18, 'F');
    $pdf->SetFont('Arial', 'I', 5);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY(63, 9);
    $pdf->MultiCell(18, 3, "Photo\nN/A", 0, 'C');
}

// ── Séparateur gauche
$pdf->SetDrawColor(0, 80, 160);
$pdf->SetLineWidth(0.3);
$pdf->Line(2, 11, 60, 11);

// ── Infos électeur
$pdf->SetTextColor(20, 20, 20);
$pdf->SetFont('Arial', 'B', 7);

$fields = [
    ['Nom',        $row['nom']],
    ['Prenom',     $row['prenom']],
    ['Genre',      $row['genre']],
    ['Age',        $row['age'] . ' ans'],
    ['CNI',        $row['cni']],
    ['Lieu',       $row['lieu'] ?? 'Centre de L\'IUT-FV Bandjoun'],
];

$y = 13;
foreach ($fields as [$label, $value]) {
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetTextColor(0, 80, 160);
    $pdf->SetXY(3, $y);
    $pdf->Cell(14, 3.5, $label . ' :', 0, 0, 'L');

    $pdf->SetFont('Arial', '', 6);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetXY(17, $y);
    $pdf->Cell(43, 3.5, (string)$value, 0, 0, 'L');
    $y += 4;
}

// ── QR Code (colonne droite, sous la photo)
if ($qrAbs && is_file($qrAbs)) {
    $pdf->Image($qrAbs, 63, 21, 19, 19);
    // Légende sous le QR
    $pdf->SetFont('Arial', 'I', 4.5);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY(63, 40);
    $pdf->Cell(19, 3, 'Scannez pour verifier', 0, 0, 'C');
} else {
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->Rect(63, 21, 19, 19);
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY(63, 28);
    $pdf->MultiCell(19, 3, "QR\nindisponible", 0, 'C');
}

// ── Image de signature
$sigPath = (string)($row['signature_path'] ?? setting($pdo, 'card_signature_path', 'assets/img/signature.png'));
$sigAbs = resolve_storage_path($sigPath, realpath(__DIR__) ?: __DIR__);
if (!$sigAbs && is_file(__DIR__ . '/assets/img/signature.png')) {
    $sigAbs = realpath(__DIR__ . '/assets/img/signature.png');
}
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
$pdf->Cell(42, 3, 'Signature Direction', 0, 0, 'L');

// ── Bandeau bas
$pdf->SetFillColor(0, 80, 160);
$pdf->Rect(0, 52, 85, 3, 'F');
$pdf->SetFont('Arial', '', 4.5);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(2, 52.5);
$pdf->Cell(81, 2, 'Document officiel - Valide - Pret A imprimer', 0, 0, 'C');

// ── Nettoyage du fichier QR temporaire
if ($qrTmpGen && is_file($qrTmpGen)) {
    // On diffère la suppression après Output via register_shutdown_function
    $tmpToDelete = $qrTmpGen;
    register_shutdown_function(function () use ($tmpToDelete) {
        if (is_file($tmpToDelete)) {
            @unlink($tmpToDelete);
        }
    });
}

// ── Envoi du PDF
$filename = 'carte_' . $row['dossier_code'] . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
$pdf->Output('I', $filename);
