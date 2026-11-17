<?php
#flat-file JSON storage in data/, no database
declare(strict_types=1);

#the data folder, DATA_DIR in the environment overrides it for tests
function storage_dir(): string
{
    $override = getenv('DATA_DIR');
    if (is_string($override) && $override !== '') {
        return rtrim($override, '/\\');
    }
    return dirname(__DIR__) . '/data';
}

#full path of one data file
function storage_path(string $name): string
{
    return storage_dir() . '/' . $name;
}

#create the data folder and block web access to it on Apache hosts
function storage_ensure_dir(): void
{
    $dir = storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create the data folder. Make it writable by PHP.');
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
}

#decode file contents, or return the default when they are empty or invalid
function storage_decode(string $raw, mixed $default): mixed
{
    if (trim($raw) === '') {
        return $default;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

#encode data for a file
function storage_encode(mixed $data): string
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

#read a JSON file under a shared lock
function read_json(string $name, mixed $default = []): mixed
{
    $path = storage_path($name);
    if (!is_file($path)) {
        return $default;
    }
    $handle = fopen($path, 'r');
    if ($handle === false) {
        return $default;
    }
    try {
        if (!flock($handle, LOCK_SH)) {
            return $default;
        }
        $raw = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    return storage_decode($raw === false ? '' : $raw, $default);
}

#replace a JSON file under an exclusive lock
function write_json(string $name, mixed $data): void
{
    update_json($name, [], fn () => $data);
}

#read, change and write a JSON file under one exclusive lock, so two requests cannot overwrite each other
#$change receives the current data and returns the new data, or null to leave the file alone
function update_json(string $name, mixed $default, callable $change): mixed
{
    storage_ensure_dir();
    $handle = fopen(storage_path($name), 'c+');
    if ($handle === false) {
        throw new RuntimeException('Cannot open ' . $name . '. Make the data folder writable by PHP.');
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Cannot lock ' . $name . '.');
        }
        $raw = stream_get_contents($handle);
        $current = storage_decode($raw === false ? '' : $raw, $default);
        $next = $change($current);
        if ($next !== null) {
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, storage_encode($next));
            fflush($handle);
        }
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    return $next ?? $current;
}
