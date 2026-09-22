<?php

namespace App\Modules\Candidate\Application\Support;

final class SafeDocumentFilename
{
    public function fromClientName(string $name): string
    {
        $filename = basename(str_replace('\\', '/', $name));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';
        $filename = trim($filename);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return 'document';
        }

        return mb_strcut($filename, 0, 255, 'UTF-8');
    }
}
