<?php

return [
    'default_branch_code' => env('FORMS_DEFAULT_BRANCH_CODE', 'CENTRAL'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:4200'),
    'file_disk' => env('FORMS_FILE_DISK', 'local'),
    'file_max_size_kb' => (int) env('FORMS_FILE_MAX_SIZE_KB', 5120),
];
