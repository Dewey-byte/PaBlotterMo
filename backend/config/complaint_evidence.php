<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filesystem disk for complaint evidence uploads
    |--------------------------------------------------------------------------
    |
    | Use "public" for local development (storage/app/public). On ephemeral
    | hosts (e.g. Render) use "s3" with AWS_* / bucket settings so files survive
    | redeploys. Works with S3-compatible providers (R2, MinIO) via AWS_ENDPOINT.
    |
    */

    'disk' => env('COMPLAINT_EVIDENCE_DISK', 'public'),

];
