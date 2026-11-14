<?php
declare(strict_types=1);

function foxyPrivateDir(): ?string
{
    $webRoot = dirname(__DIR__);
    // Beget keeps private data beside public_html. Masterhost keeps it beside the site directory.
    $parent = basename($webRoot) === 'public_html' ? dirname($webRoot) : dirname($webRoot, 2);
    $privateDir = realpath($parent . '/valera-private');
    if ($privateDir === false || !is_dir($privateDir)
        || $privateDir === $webRoot || strpos($privateDir, $webRoot . '/') === 0) {
        return null;
    }
    return $privateDir;
}
