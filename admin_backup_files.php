<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!class_exists('ZipArchive')) {
    die("Error: Your server does not have the ZipArchive extension enabled.");
}

$zip = new ZipArchive();
$filename = "code_backup_" . date("Y-m-d") . ".zip";

if ($zip->open($filename, ZipArchive::CREATE) !== TRUE) {
    die("Could not open archive");
}

// Path to your project folder
$path = realpath('.'); 

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    // Skip directories (they are added automatically)
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($path) + 1);

        // Don't add the backup zip itself into the backup!
        if ($relativePath !== $filename) {
            $zip->addFile($filePath, $relativePath);
        }
    }
}

$zip->close();

// Download and clean up
header("Content-Type: application/zip");
header("Content-Length: " . filesize($filename));
header("Content-Disposition: attachment; filename=\"$filename\"");
readfile($filename);
unlink($filename); 
exit;