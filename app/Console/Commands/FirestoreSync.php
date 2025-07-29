<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FirestoreSync extends Command
{
    protected $signature = 'firestore:sync {collection? : The collection to sync} {--all : Sync all configured collections}';
    protected $description = 'Sync Firestore data to MySQL with support for complex schemas';

    public function handle()
    {
        $collection = $this->argument('collection');
        $syncAll = $this->option('all');

        if ($syncAll) {
            $this->syncAllCollections();
        } elseif ($collection) {
            $this->syncCollection($collection);
        } else {
            $this->syncCollection('users'); // Default to users
        }

        return 0;
    }

    private function syncAllCollections()
    {
        $collections = config('firestore-sync.collections');
        
        foreach (array_keys($collections) as $collectionName) {
            $this->info("Syncing collection: $collectionName");
            $this->syncCollection($collectionName);
            $this->newLine();
        }
    }

    private function syncCollection($collectionName)
    {
        try {
            $this->info("Initializing Firestore connection for collection: $collectionName");
            
            // Read Firebase credentials
            $credentialsPath = config('firebase.credentials');
            if (!file_exists($credentialsPath)) {
                throw new \Exception("Firebase credentials file not found at: $credentialsPath");
            }
            
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            $projectId = config('firebase.project_id');
            
            // Get access token
            $token = $this->getAccessToken($credentials);
            
            $this->info("Fetching documents from Firestore collection: $collectionName");
            
            // Fetch documents from Firestore REST API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->get("https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/$collectionName");
            
            if (!$response->successful()) {
                throw new \Exception('Failed to fetch documents from Firestore: ' . $response->body());
            }
            
            $data = $response->json();
            $documents = $data['documents'] ?? [];
            $total = count($documents);
            
            if ($total === 0) {
                $this->warn("No documents found in Firestore collection: $collectionName");
                return;
            }
            
            $this->info("Found $total documents to sync in collection: $collectionName");
            
            // Get collection configuration
            $config = config("firestore-sync.collections.$collectionName");
            if (!$config) {
                throw new \Exception("No configuration found for collection: $collectionName");
            }
            
            // Process documents with a progress bar
            $this->withProgressBar($total, function () use ($documents, $config, $collectionName) {
                $count = 0;
                foreach ($documents as $document) {
                    $fields = $document['fields'] ?? [];
                    $data = $this->extractDocumentData($fields, $config);
                    
                    try {
                        $modelClass = $config['model'];
                        $uniqueKey = $config['unique_key'];
                        $uniqueValue = $data[$uniqueKey] ?? null;
                        
                        if (!$uniqueValue) {
                            $this->warn("Skipping document without unique key: $uniqueKey");
                            continue;
                        }
                        
                        // Apply transformations
                        $data = $this->applyTransformations($data, $config);
                        
                        // Add defaults
                        $data = array_merge($config['defaults'] ?? [], $data);
                        
                        // Create or update record
                        $record = $modelClass::updateOrCreate(
                            [$uniqueKey => $uniqueValue],
                            $data
                        );
                        
                        $this->line("Synced: " . ($record->name ?? $record->email ?? $uniqueValue));
                        $count++;
                    } catch (\Exception $e) {
                        $this->error("Failed to sync document: " . $e->getMessage());
                        Log::error("Failed to sync document in collection $collectionName", [
                            'error' => $e->getMessage(),
                            'data' => $data ?? null
                        ]);
                    }
                }
                return $count;
            });
            
            $this->info("Successfully synced collection: $collectionName");
            
        } catch (\Exception $e) {
            Log::error("Firestore sync failed for collection: $collectionName", [
                'error' => $e->getMessage(), 
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("Sync failed for collection $collectionName: " . $e->getMessage());
            return 1;
        }
    }

    private function extractDocumentData($fields, $config)
    {
        $data = [];
        $fieldMappings = $config['field_mappings'] ?? [];
        
        foreach ($fieldMappings as $firestoreField => $mysqlField) {
            $value = $this->extractNestedField($fields, $firestoreField);
            if ($value !== null) {
                $data[$mysqlField] = $value;
            }
        }
        
        return $data;
    }

    private function extractNestedField($fields, $fieldPath)
    {
        $parts = explode('.', $fieldPath);
        $current = $fields;
        
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }
        
        return $this->extractFieldValue($current);
    }

    private function applyTransformations($data, $config)
    {
        $transformations = $config['transformations'] ?? [];
        
        foreach ($transformations as $field => $transformation) {
            if (isset($data[$field])) {
                switch ($transformation) {
                    case 'ucwords':
                        $data[$field] = ucwords($data[$field]);
                        break;
                    case 'strtolower':
                        $data[$field] = strtolower($data[$field]);
                        break;
                    case 'intval':
                        $data[$field] = intval($data[$field]);
                        break;
                    case 'floatval':
                        $data[$field] = floatval($data[$field]);
                        break;
                    case 'json_encode':
                        if (is_array($data[$field]) || is_object($data[$field])) {
                            $data[$field] = json_encode($data[$field]);
                        }
                        break;
                    default:
                        // Custom transformation function
                        if (function_exists($transformation)) {
                            $data[$field] = $transformation($data[$field]);
                        }
                        break;
                }
            }
        }
        
        return $data;
    }

    private function extractFieldValue($fieldData)
    {
        if (isset($fieldData['stringValue'])) {
            return $fieldData['stringValue'];
        } elseif (isset($fieldData['integerValue'])) {
            return (int) $fieldData['integerValue'];
        } elseif (isset($fieldData['doubleValue'])) {
            return (float) $fieldData['doubleValue'];
        } elseif (isset($fieldData['booleanValue'])) {
            return $fieldData['booleanValue'];
        } elseif (isset($fieldData['arrayValue'])) {
            return $this->extractArrayValue($fieldData['arrayValue']);
        } elseif (isset($fieldData['mapValue'])) {
            return $this->extractMapValue($fieldData['mapValue']);
        } elseif (isset($fieldData['timestampValue'])) {
            return $fieldData['timestampValue'];
        } elseif (isset($fieldData['nullValue'])) {
            return null;
        } else {
            return null;
        }
    }

    private function extractArrayValue($arrayValue)
    {
        $values = [];
        foreach ($arrayValue['values'] as $item) {
            $values[] = $this->extractFieldValue($item);
        }
        return $values;
    }

    private function extractMapValue($mapValue)
    {
        $values = [];
        foreach ($mapValue['fields'] as $key => $value) {
            $values[$key] = $this->extractFieldValue($value);
        }
        return $values;
    }

    private function getAccessToken($credentials)
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->createJWT($credentials),
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Failed to get access token: ' . $response->body());
        }
        
        $data = $response->json();
        return $data['access_token'];
    }

    private function createJWT($credentials)
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        
        $time = time();
        $payload = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $time + 3600,
            'iat' => $time
        ];
        
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = '';
        openssl_sign(
            $headerEncoded . '.' . $payloadEncoded,
            $signature,
            $credentials['private_key'],
            'SHA256'
        );
        
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
