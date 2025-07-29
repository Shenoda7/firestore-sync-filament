<?php
return [
    'credentials' => env('FIREBASE_CREDENTIALS') ? base_path(env('FIREBASE_CREDENTIALS')) : null,
    'project_id' => env('FIREBASE_PROJECT_ID', 'firestore-sync-b4f04'),
];
