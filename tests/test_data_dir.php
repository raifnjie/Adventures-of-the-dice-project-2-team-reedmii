<?php
#shared test helper: a fresh temp data folder per run, removed afterward
declare(strict_types=1);

#create an empty folder and point DATA_DIR at it
function test_data_dir_create(string $label): string
{
    $dir = str_replace('\\', '/', sys_get_temp_dir()) . '/aod_' . $label . '_' . bin2hex(random_bytes(4));
    mkdir($dir, 0775, true);
    putenv('DATA_DIR=' . $dir);
    return $dir;
}

#delete the JSON files and the folder
function test_data_dir_remove(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if (is_file($dir . '/' . $name)) {
            unlink($dir . '/' . $name);
        }
    }
    rmdir($dir);
}
