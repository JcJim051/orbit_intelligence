<?php

namespace App\Contracts;

interface AudioInspector
{
    /** @return array{duration_ms:int,mime_type:string} */
    public function inspect(string $path): array;
}
