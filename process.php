<?php

define('MAX_DEPTH', 3);

$imageFile = 'http://animalia-life.com/data_images/fish/fish3.jpg';

function getRGBFromColor($color, &$r, &$g, &$b) {
    $r = ($color & 0xff0000) >> 16;
    $g = ($color & 0xff00) >> 8;
    $b = $color & 0xff;
}

/**
 * Calculates the distance between two colors
 * @param int $color1 The first color
 * @param int $color2 The second color
 * @return int The distance between the two colors
 */
function colorDistance($color1, $color2) {
    $r1 = 0; $g1 = 0; $b1 = 0;
    getRGBFromColor($color1, $r1, $g1, $b1);
    $r2 = 0; $g2 = 0; $b2 = 0;
    getRGBFromColor($color2, $r2, $g2, $b2);
    return abs($r1 - $r2) + abs($g1 - $g2) + abs($b1 - $b2);
}

/**
 * Draws border lines on an image
 * @param handle $img The GD image to draw to
 * @param int $borderMask A bitmask of the borders to draw. Follows CSS shorthand convention
 */
function drawBorder($img, $borderMask = 0b1111) {

    $black = imagecolorallocate($img, 0, 0, 0);
    $width = imagesx($img);
    $height = imagesy($img);

    // Top
    if ($borderMask & 0b1) {
        imageline($img, 0, 0, $width, 0, $black);
    }

    // Right
    if ($borderMask & 0b10) {
        imageline($img, $width - 1, 0, $width - 1, $height, $black);
    }

    // Bottom
    if ($borderMask & 0b100) {
        imageline($img, 0, $height - 1, $width, $height - 1, $black);
    }

    // Left
    if ($borderMask & 0b1000) {
        imageline($img, 0, 0, 0, $height, $black);
    }
}

function processRect($src, $depth = 0, &$tiles = 0) {

    $width = imagesx($src);
    $height = imagesy($src);

    // Trim for odd number sizes
    $width = $width % 2 === 1 ? $width + 1 : $width;
    $height = $height % 2 === 1 ? $height + 1 : $height;

    $sectorWidth = floor($width / 2);
    $sectorHeight = floor($height / 2);
    $pixels = $sectorWidth * $sectorHeight;

    $retVal = imagecreatetruecolor($width, $height);

    for ($col = 0; $col < 2; $col++) {
        for ($row = 0; $row < 2; $row++) {

            $sectorImg = imagecreatetruecolor($sectorWidth, $sectorHeight);
            $xOffset = $col * $sectorWidth;
            $yOffset = $row * $sectorHeight;

            // Get the average RGB value
            $avgR = 0;
            $avgG = 0;
            $avgB = 0;
            for ($x = 0; $x < $sectorWidth; $x++) {
                for ($y = 0; $y < $sectorHeight; $y++) {
                    $color = @imagecolorat($src, $xOffset + $x, $yOffset + $y);
                    $r = 0; $g = 0; $b = 0;
                    getRGBFromColor($color, $r, $g, $b);
                    $avgR += $r;
                    $avgG += $g;
                    $avgB += $b;
                }
            }

            $avgR = round($avgR / $pixels);
            $avgG = round($avgG / $pixels);
            $avgB = round($avgB / $pixels);
            $avgColor = imagecolorallocate($sectorImg, $avgR, $avgG, $avgB);

            // Calculate the average color distance
            $avgDist = 0;
            for ($x = 0; $x < $sectorWidth; $x++) {
                for ($y = 0; $y < $sectorHeight; $y++) {
                    $color = @imagecolorat($src, $xOffset + $x, $yOffset + $y);
                    $avgDist += colorDistance($color, $avgColor);
                }
            }

            $avgDist = $avgDist / $pixels;

            if ($avgDist > 40 && $depth < MAX_DEPTH && $sectorWidth > 2 && $sectorHeight > 2) {
                imagecopy($sectorImg, $src, 0, 0, $xOffset, $yOffset, $sectorWidth, $sectorHeight);
                imagecopy($retVal, processRect($sectorImg, $depth + 1, $tiles), $xOffset, $yOffset, 0, 0, $sectorWidth, $sectorHeight);
            } else {
                imagefill($sectorImg, 0, 0, $avgColor);
                drawBorder($sectorImg, 0b1001);
                imagecopy($retVal, $sectorImg, $xOffset, $yOffset, 0, 0, $sectorWidth, $sectorHeight);
                $tiles++;
            }

            imagedestroy($sectorImg);

        }
    }

    // Draw the right and bottom edges on the first call
    if ($depth === 0) {
        drawBorder($retVal, 0b0110);
        echo 'Tiles drawn: ', $tiles, PHP_EOL;
    }

    return $retVal;

}

function processImage($imageUri) {

    $image = imagecreatefromjpeg($imageUri);

    $width = imagesx($image);
    $height = imagesy($image);

    return processRect($image);

}

imagepng(processImage($imageFile), 'out' . MAX_DEPTH . '.png');