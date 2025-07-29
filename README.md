# Firestore to MySQL Sync with Filament Admin

A Laravel-based solution that syncs data from Google Firestore to MySQL and displays it through Filament Admin Panel.

## 🎯 Problem Solved

**Challenge**: You have data stored in Firebase Firestore (NoSQL) but need to manage it through Laravel's Filament Admin Panel, which works best with MySQL.

**Solution**: A robust sync system that fetches data from Firestore, transforms it, and stores it in MySQL for seamless Filament integration.

## 🏗️ Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Firestore     │───▶│  Laravel Sync    │───▶│  MySQL +        │
│   (NoSQL)       │    │  Command         │    │  Filament       │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

## ✨ Features

- **🔄 Full Sync**: Syncs all documents from Firestore collections to MySQL
- **🔄 Incremental Updates**: Uses `updateOrCreate` to avoid duplicates
- **📊 Complex Data Handling**: Supports nested objects, arrays, and custom field types
- **⚙️ Configurable**: Easy field mapping and transformations
- **🛡️ Error Handling**: Robust error handling with logging
- **📈 Progress Tracking**: Visual progress bars for large datasets
- **🎨 Filament Integration**: Beautiful admin interface for data management

## 🚀 Quick Start

### 1. Prerequisites

- Laravel 10+ with Filament 3
- MySQL database
- Firebase project with Firestore
- Firebase service account credentials

### 2. Installation

```bash
# Clone the repository
git clone <repository-url>
cd firestore-sync-filament

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Configuration

#### Firebase Setup

1. **Download Firebase Credentials**:
   - Go to Firebase Console → Project Settings → Service Accounts
   - Generate new private key
   - Save as `storage/firebase_credentials.json`

2. **Update `.env`**:
```env
FIREBASE_CREDENTIALS=storage/firebase_credentials.json
FIREBASE_PROJECT_ID=your-project-id
```

#### Database Setup

```bash
# Run migrations
php artisan migrate

# Create database tables
php artisan migrate:fresh
```

### 4. Sync Data

```bash
# Sync all configured collections
php artisan firestore:sync --all

# Sync specific collection
php artisan firestore:sync users

# Test the sync
php artisan firestore:sync
```

### 5. Access Filament Admin

```bash
# Start the server
php artisan serve

# Visit: http://localhost:8000/admin
```

## 📋 Configuration

### Firestore Sync Configuration

Edit `config/firestore-sync.php`:

```php
'collections' => [
    'users' => [
        'table' => 'users',
        'model' => \App\Models\User::class,
        'unique_key' => 'email',
        'field_mappings' => [
            'name' => 'name',
            'email' => 'email',
            'age' => 'age',
            'profile.address.city' => 'city',
            'profile.address.country' => 'country',
        ],
        'transformations' => [
            'name' => 'ucwords',
            'email' => 'strtolower',
            'age' => 'intval',
        ],
        'defaults' => [
            'password' => 'password',
        ],
    ],
],
```

### Field Mappings

| Firestore Field | MySQL Column | Description |
|----------------|--------------|-------------|
| `name` | `name` | Direct mapping |
| `profile.address.city` | `city` | Nested field extraction |
| `tags` | `tags` | Array (JSON encoded) |
| `metadata` | `metadata` | Object (JSON encoded) |

### Transformations

| Transformation | Description |
|---------------|-------------|
| `ucwords` | Title case |
| `strtolower` | Lowercase |
| `intval` | Integer conversion |
| `floatval` | Float conversion |
| `json_encode` | JSON encoding |

## 🔧 Commands

### Sync Commands

```bash
# Sync all collections
php artisan firestore:sync --all

# Sync specific collection
php artisan firestore:sync users

# Sync with verbose output
php artisan firestore:sync users -v
```

### Test Commands

```bash
# Test Firestore connection
php artisan firestore:test

# Test MySQL data
php artisan mysql:test

# Test Filament resources
php artisan route:list --name=admin
```

## 📊 Data Flow

### 1. Firestore → Laravel

```php
// Fetch documents from Firestore REST API
$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $token,
])->get("https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/users");
```

### 2. Data Transformation

```php
// Extract nested fields
$data = $this->extractDocumentData($fields, $config);

// Apply transformations
$data = $this->applyTransformations($data, $config);

// Add defaults
$data = array_merge($config['defaults'] ?? [], $data);
```

### 3. MySQL Storage

```php
// Create or update record
$record = User::updateOrCreate(
    ['email' => $data['email']],
    $data
);
```

### 4. Filament Display

```php
// Display in Filament table
Tables\Columns\TextColumn::make('name')
    ->searchable()
    ->sortable(),
```

## 🛠️ Advanced Usage

### Custom Transformations

```php
'transformations' => [
    'name' => function($value) {
        return strtoupper($value);
    },
    'age' => 'intval',
],
```

### Complex Field Mapping

```php
'field_mappings' => [
    'profile.address.city' => 'city',
    'profile.address.country' => 'country',
    'preferences.theme' => 'theme',
    'metadata.tags' => 'tags',
],
```

### Batch Processing

```php
// Process in batches for large datasets
'batch_size' => 100,
'timeout' => 300,
'retry_attempts' => 3,
```

## 🔍 Troubleshooting

### Common Issues

1. **Firebase Credentials Error**:
   ```bash
   # Check file exists
   ls -la storage/firebase_credentials.json
   
   # Verify JSON format
   php -r "json_decode(file_get_contents('storage/firebase_credentials.json'));"
   ```

2. **Permission Issues**:
   ```bash
   # Set proper permissions
   chmod 644 storage/firebase_credentials.json
   ```

3. **Database Connection**:
   ```bash
   # Test database connection
   php artisan tinker
   DB::connection()->getPdo();
   ```

### Debug Commands

```bash
# Test Firestore connection
php artisan firestore:test

# Check sync configuration
php artisan config:show firestore-sync

# View logs
tail -f storage/logs/laravel.log
```

## 📈 Performance

### Optimization Tips

1. **Batch Processing**: Process documents in batches to avoid memory issues
2. **Indexing**: Add database indexes for frequently queried fields
3. **Caching**: Use Laravel's cache for frequently accessed data
4. **Queue Jobs**: Use Laravel queues for large sync operations

### Monitoring

```bash
# Monitor sync progress
php artisan firestore:sync --verbose

# Check database size
php artisan tinker
DB::table('users')->count();
```

## 🔐 Security

### Best Practices

1. **Credentials**: Store Firebase credentials securely
2. **Permissions**: Use least-privilege Firebase service accounts
3. **Validation**: Validate all incoming data
4. **Logging**: Log all sync operations for audit trails

### Environment Variables

```env
# Required
FIREBASE_CREDENTIALS=storage/firebase_credentials.json
FIREBASE_PROJECT_ID=your-project-id

# Optional
FIREBASE_SYNC_BATCH_SIZE=100
FIREBASE_SYNC_TIMEOUT=300
```

## 📚 API Reference

### FirestoreSync Command

```php
class FirestoreSync extends Command
{
    protected $signature = 'firestore:sync {collection?} {--all}';
    
    public function handle()
    {
        // Sync logic
    }
}
```

### Configuration Structure

```php
return [
    'collections' => [
        'collection_name' => [
            'table' => 'mysql_table',
            'model' => ModelClass::class,
            'unique_key' => 'email',
            'field_mappings' => [...],
            'transformations' => [...],
            'defaults' => [...],
        ],
    ],
];
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License.

## 🆘 Support

- **Issues**: Create an issue on GitHub
- **Documentation**: Check the Laravel and Filament documentation
- **Community**: Join the Laravel and Filament communities

---

**Made with ❤️ for the Laravel community**
