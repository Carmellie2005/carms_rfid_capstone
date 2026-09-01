<?php

$root = dirname(__DIR__);
$sourcePath = $root.'/public/images/slsu-rfid-system-logo-ai-v2.png';

if (! extension_loaded('gd')) {
    fwrite(STDERR, "The PHP GD extension is required.\n");
    exit(1);
}

if (! file_exists($sourcePath)) {
    fwrite(STDERR, "Source logo was not found: {$sourcePath}\n");
    exit(1);
}

$source = imagecreatefrompng($sourcePath);

if (! $source) {
    fwrite(STDERR, "Source logo could not be opened.\n");
    exit(1);
}

$sourceWidth = imagesx($source);
$sourceHeight = imagesy($source);
$sourceBounds = findVisibleBounds($source, $sourceWidth, $sourceHeight);

$icons = [
    ['path' => $root.'/public/pwa-icon-192.png', 'size' => 192, 'padding' => 0.035, 'rounded' => true],
    ['path' => $root.'/public/pwa-icon-512.png', 'size' => 512, 'padding' => 0.035, 'rounded' => true],
    ['path' => $root.'/public/pwa-icon-maskable-192.png', 'size' => 192, 'padding' => 0.09, 'rounded' => false],
    ['path' => $root.'/public/pwa-icon-maskable-512.png', 'size' => 512, 'padding' => 0.09, 'rounded' => false],
    ['path' => $root.'/public/apple-touch-icon.png', 'size' => 180, 'padding' => 0.035, 'rounded' => false],
    ['path' => $root.'/public/favicon.png', 'size' => 512, 'padding' => 0.035, 'rounded' => true],
    ['path' => $root.'/public/favicon-32x32.png', 'size' => 32, 'padding' => 0.035, 'rounded' => true],
];

foreach ($icons as $icon) {
    generateIcon($source, $sourceBounds, $icon['path'], $icon['size'], $icon['padding'], $icon['rounded']);
    echo "Generated {$icon['path']}\n";
}

imagedestroy($source);

function generateIcon($source, array $sourceBounds, string $outputPath, int $finalSize, float $paddingRatio, bool $rounded): void
{
    $scale = 4;
    $size = $finalSize * $scale;
    $padding = (int) round($size * $paddingRatio);
    $radius = (int) round($size * 0.22);

    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);

    $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
    imagefill($canvas, 0, 0, $transparent);

    if ($rounded) {
        $shadow = imagecolorallocatealpha($canvas, 29, 78, 216, 108);
        drawRoundedRectangle(
            $canvas,
            (int) round($size * 0.035),
            (int) round($size * 0.05),
            (int) round($size * 0.965),
            (int) round($size * 0.98),
            $radius,
            $shadow
        );
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    $border = imagecolorallocate($canvas, 191, 219, 254);

    if ($rounded) {
        drawRoundedRectangle($canvas, 0, 0, $size - 1, $size - 1, $radius, $white);
        drawRoundedBorder($canvas, 0, 0, $size - 1, $size - 1, $radius, $border, max(4, (int) round($scale * 1.5)));
    } else {
        imagefilledrectangle($canvas, 0, 0, $size, $size, $white);
    }

    $boxSize = $size - ($padding * 2);
    $sourceRatio = $sourceBounds['width'] / $sourceBounds['height'];

    if ($sourceRatio >= 1) {
        $targetWidth = $boxSize;
        $targetHeight = (int) round($targetWidth / $sourceRatio);
    } else {
        $targetHeight = $boxSize;
        $targetWidth = (int) round($targetHeight * $sourceRatio);
    }

    $targetX = (int) round(($size - $targetWidth) / 2);
    $targetY = (int) round(($size - $targetHeight) / 2);

    imagecopyresampled(
        $canvas,
        $source,
        $targetX,
        $targetY,
        $sourceBounds['x'],
        $sourceBounds['y'],
        $targetWidth,
        $targetHeight,
        $sourceBounds['width'],
        $sourceBounds['height']
    );

    $final = imagecreatetruecolor($finalSize, $finalSize);
    imagealphablending($final, false);
    imagesavealpha($final, true);
    imagecopyresampled($final, $canvas, 0, 0, 0, 0, $finalSize, $finalSize, $size, $size);

    imagepng($final, $outputPath, 9);
    imagedestroy($canvas);
    imagedestroy($final);
}

function findVisibleBounds($source, int $sourceWidth, int $sourceHeight): array
{
    $minX = $sourceWidth;
    $minY = $sourceHeight;
    $maxX = -1;
    $maxY = -1;

    for ($y = 0; $y < $sourceHeight; $y++) {
        for ($x = 0; $x < $sourceWidth; $x++) {
            $rgba = imagecolorat($source, $x, $y);
            $alpha = ($rgba & 0x7F000000) >> 24;
            $red = ($rgba >> 16) & 255;
            $green = ($rgba >> 8) & 255;
            $blue = $rgba & 255;

            if ($alpha < 120 && ($red < 246 || $green < 246 || $blue < 246)) {
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
    }

    if ($maxX < $minX || $maxY < $minY) {
        return [
            'x' => 0,
            'y' => 0,
            'width' => $sourceWidth,
            'height' => $sourceHeight,
        ];
    }

    $margin = (int) round(max($maxX - $minX + 1, $maxY - $minY + 1) * 0.025);
    $minX = max(0, $minX - $margin);
    $minY = max(0, $minY - $margin);
    $maxX = min($sourceWidth - 1, $maxX + $margin);
    $maxY = min($sourceHeight - 1, $maxY + $margin);

    return [
        'x' => $minX,
        'y' => $minY,
        'width' => $maxX - $minX + 1,
        'height' => $maxY - $minY + 1,
    ];
}

function drawRoundedRectangle($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
{
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}

function drawRoundedBorder($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color, int $thickness): void
{
    imagesetthickness($image, $thickness);
    imagearc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color);
    imagearc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color);
    imagearc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color);
    imagearc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color);
    imageline($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
    imageline($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
    imageline($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
    imageline($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
    imagesetthickness($image, 1);
}
