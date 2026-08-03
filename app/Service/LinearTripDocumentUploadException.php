<?php

namespace App\Service;

use RuntimeException;

final class LinearTripDocumentUploadException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'document_upload_failed'
    ) {
        parent::__construct($message);
    }
}
