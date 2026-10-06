<?php

function prefaAttachmentDirectory(int $id): string
{
    return dirname(__DIR__) . '/uploads/prefa/' . $id;
}

function prefaAttachments(int $id): array
{
    $directory = prefaAttachmentDirectory($id);
    if (!is_dir($directory)) return [];
    $files = [];
    foreach (new DirectoryIterator($directory) as $file) {
        if (!$file->isFile() || !preg_match('/^[a-f0-9]{32}-(.+)$/', $file->getFilename(), $match)) continue;
        $files[] = ['key' => $file->getFilename(), 'name' => $match[1]];
    }
    return $files;
}
