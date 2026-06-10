<?php
declare(strict_types=1);

$dir = __DIR__ . '/../public/assets/img';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$target = $dir . '/signature.png';

if (function_exists('imagecreatetruecolor')) {
    $img = imagecreatetruecolor(120, 40);
    $bg = imagecolorallocate($img, 255, 255, 255);
    $blue = imagecolorallocate($img, 0, 80, 160);
    imagefill($img, 0, 0, $bg);
    imagestring($img, 3, 10, 12, 'Signature', $blue);
    imagepng($img, $target);
    imagedestroy($img);
    echo "signature.png created (GD)\n";
    exit(0);
}

// Fallback sans extension GD : PNG minimal 120x40 (blanc)
$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAHgAAAAoCAYAAACO6X+2AAAABHNCSVQICAgIfAhkiAAAAAlwSFlz'
    . 'AAALEgAACxIB0t1+/AAAABZ0RVh0Q3JlYXRpb24gVGltZQAxMC8wNi8yMDI2fQ==',
    true
);
if ($png === false || strlen($png) < 50) {
    // PNG 1x1 blanc si le fallback échoue
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        true
    );
}
file_put_contents($target, $png);
echo "signature.png created (fallback)\n";
