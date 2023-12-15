<?php

namespace RPurinton\modify2;

class WEBP
{
    public static function generateBarChart(int $message_id, int $width, int $height, array $values)
    {
        $im = imagecreatetruecolor($width, $height);
        $values_count = count($values);
        $bar_height = $height / $values_count;
        $y = 0;
        $text_color = imagecolorallocate($im, 255, 255, 255);
        // $key is a string, $value is a float from 0 to 1
        foreach ($values as $key => $value) {
            $r = round(255 * $value);
            $g = 255 - $r;
            $b = 0;
            $bar_width = round($width * $value);
            $bar_color = imagecolorallocate($im, $r, $g, $b);
            imagefilledrectangle($im, 0, $y, $bar_width, $y + $bar_height, $bar_color);
            imagestring($im, 5, 0, $y, $key, $text_color);
            $y += $bar_height;
        }
        imagewebp($im, $message_id . '.webp', 100);
    }
}
