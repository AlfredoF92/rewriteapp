<?php
@ini_set('display_errors', 1);
@error_reporting(E_ALL);

echo "<h2>🗑️ WP Config Cleaner (Remove DISALLOW FILE EDIT / MODS)</h2>";

$config = find_config();
if (!$config) exit("❌ wp-config.php tidak ditemukan!");

echo "✅ Ditemukan: <code>$config</code><br>";

remove_from_config($config);
clean_plugins(dirname($config));

function find_config() {
    $d = __DIR__;
    while ($d !== dirname($d)) {
        if (file_exists("$d/wp-config.php")) return "$d/wp-config.php";
        $d = dirname($d);
    }
    return false;
}

function remove_from_config($file) {
    $cfg = file_get_contents($file);

    $removes = [
        "define('DISALLOW_FILE_EDIT', true);",
        "define('DISALLOW_FILE_MODS', true);"
    ];

    $changed = false;

    foreach ($removes as $line) {
        if (strpos($cfg, $line) !== false) {
            $cfg = str_replace($line, '', $cfg);
            echo "🗑️ Hapus: <code>$line</code><br>";
            $changed = true;
        }
    }

    if ($changed && is_writable($file)) {
        // rapikan newline ganda
        $cfg = preg_replace("/\n{2,}/", "\n", $cfg);

        file_put_contents($file, $cfg);
        echo "✅ wp-config.php sudah dibersihkan<br>";
    } else {
        echo "ℹ️ Tidak ada yang dihapus atau file tidak bisa ditulis<br>";
    }
}

function clean_plugins($wp_root) {
    $dir = "$wp_root/wp-content/plugins";
    $bad = ['wp-file-manager', 'wpspy', 'file-manager-advanced', 'malicious-uploader'];

    foreach ($bad as $p) {
        $path = "$dir/$p";
        if (is_dir($path)) {
            delete_recursive($path);
            echo "🗑️ Plugin dihapus: <code>$p</code><br>";
        }
    }
}

function delete_recursive($d) {
    foreach (scandir($d) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = "$d/$f";
        is_dir($path) ? delete_recursive($path) : unlink($path);
    }
    rmdir($d);
}
?>
