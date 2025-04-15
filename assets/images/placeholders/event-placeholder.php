<?php
// Set content type to image
header('Content-Type: image/png');

// Configuration
$width = isset($_GET['width']) ? intval($_GET['width']) : 350;
$height = isset($_GET['height']) ? intval($_GET['height']) : 180;
$text = isset($_GET['text']) ? $_GET['text'] : 'Event Image';

// Limit dimensions for security
$width = min($width, 800);
$height = min($height, 600);

// Create image
$image = imagecreatetruecolor($width, $height);

// Colors
$bg_color = imagecolorallocate($image, 242, 242, 242);
$border_color = imagecolorallocate($image, 220, 220, 220);
$text_color = imagecolorallocate($image, 100, 100, 100);
$accent_color = imagecolorallocate($image, 0, 123, 255);

// Fill background
imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

// Draw border
imagerectangle($image, 0, 0, $width - 1, $height - 1, $border_color);

// Draw accent line at top
imagefilledrectangle($image, 0, 0, $width, 5, $accent_color);

// Add text
$font = 5; // Built-in font size (1-5)
$text_width = imagefontwidth($font) * strlen($text);
$text_height = imagefontheight($font);
$text_x = ($width - $text_width) / 2;
$text_y = ($height - $text_height) / 2;

imagestring($image, $font, $text_x, $text_y, $text, $text_color);

// Output image
imagepng($image);

// Free memory
imagedestroy($image);
?> 