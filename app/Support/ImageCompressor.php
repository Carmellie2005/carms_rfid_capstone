<?php

namespace App\Support;

use GdImage;

class ImageCompressor
{
    public const DEFAULT_MAX_DIMENSION = 1280;

    public const DEFAULT_JPEG_QUALITY = 72;

    public static function compressedJpeg(
        string $contents,
        int $maxDimension = self::DEFAULT_MAX_DIMENSION,
        int $quality = self::DEFAULT_JPEG_QUALITY,
        ?string $sourcePath = null,
    ): ?array {
        $metadata = @getimagesizefromstring($contents);

        if ($metadata === false) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            return self::originalImage($contents, $metadata);
        }

        $source = self::applyJpegOrientation($source, $metadata['mime'] ?? '', $sourcePath);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);

            return null;
        }

        $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas instanceof GdImage) {
            imagedestroy($source);

            return self::originalImage($contents, $metadata);
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        imageinterlace($canvas, true);

        ob_start();
        $saved = imagejpeg($canvas, null, max(1, min(100, $quality)));
        $compressed = ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        if (! $saved || ! is_string($compressed) || $compressed === '') {
            return self::originalImage($contents, $metadata);
        }

        return [
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'contents' => $compressed,
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }

    private static function originalImage(string $contents, array $metadata): array
    {
        $mimeType = $metadata['mime'] ?? 'image/jpeg';

        return [
            'extension' => self::extensionForMimeType($mimeType),
            'mime_type' => $mimeType,
            'contents' => $contents,
            'width' => $metadata[0] ?? null,
            'height' => $metadata[1] ?? null,
        ];
    }

    private static function applyJpegOrientation(GdImage $source, string $mimeType, ?string $sourcePath): GdImage
    {
        if ($mimeType !== 'image/jpeg' || ! $sourcePath || ! function_exists('exif_read_data')) {
            return $source;
        }

        $exif = @exif_read_data($sourcePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $oriented = $source;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($oriented, IMG_FLIP_HORIZONTAL);
        }

        $rotated = match ($orientation) {
            3, 4 => imagerotate($oriented, 180, 0),
            5, 6 => imagerotate($oriented, -90, 0),
            7, 8 => imagerotate($oriented, 90, 0),
            default => $oriented,
        };

        if ($rotated instanceof GdImage && $rotated !== $source) {
            imagedestroy($source);

            return $rotated;
        }

        return $oriented;
    }

    private static function extensionForMimeType(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }
}
