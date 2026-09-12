<?php

$root = dirname(__DIR__);

function removePath(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }

    if (! is_dir($path)) {
        return;
    }

    foreach (new FilesystemIterator($path) as $item) {
        removePath($item->getPathname());
    }

    rmdir($path);
}

function clearRuntimeDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    foreach (new FilesystemIterator($path) as $item) {
        if ($item->getFilename() === '.gitignore') {
            continue;
        }

        if ($item->isDir() && ! $item->isLink()) {
            clearRuntimeDirectory($item->getPathname());

            if (count(scandir($item->getPathname())) === 2) {
                rmdir($item->getPathname());
            }

            continue;
        }

        unlink($item->getPathname());
    }
}

foreach ([
    $root.'/backend/vendor',
    $root.'/frontend/node_modules',
    $root.'/frontend/dist',
] as $path) {
    removePath($path);
}

clearRuntimeDirectory($root.'/backend/storage');
clearRuntimeDirectory($root.'/backend/bootstrap/cache');

if (is_file($root.'/.env')) {
    unlink($root.'/.env');
}

echo "Generated files removed.\n";
