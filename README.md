# Pagoti PHP

PHP client for the [Pagoti](https://pagoti.com) content API.

## Installation

```bash
composer require metrique/pagoti-php
```

## Requirements

- PHP 8.5+
- A PSR-18 HTTP client (Guzzle is included by default)
- A PSR-16 cache implementation (optional, recommended)

## Quick Start

```php
use Metrique\Pagoti\PagotiClient;

$client = new PagotiClient('your-api-key');
```

## Configuration

```php
use Metrique\Pagoti\PagotiClient;

$client = new PagotiClient(
    apiKey: 'your-api-key',
    cache: $cache,        // PSR-16 CacheInterface — optional but recommended
    cacheTtl: 3600,       // cache TTL in seconds, default 3600
    baseUrl: 'https://pagoti.com/api',  // default
    version: 'v1',        // default
);
```

### With Laravel

Pass Laravel's cache directly — it implements PSR-16 out of the box:

```php
$client = new PagotiClient(
    apiKey: config('services.pagoti.key'),
    cache: cache()->store(),
);
```

### Custom HTTP Client

By default Guzzle is used. You can inject any PSR-18 compatible client:

```php
use Metrique\Pagoti\PagotiClient;
use Symfony\Component\HttpClient\Psr18Client;

$client = new PagotiClient(
    apiKey: 'your-api-key',
    httpClient: new Psr18Client(),
);
```

## Usage

### Projects

```php
// List all projects (paginated)
$projects = $client->projects()->get();

// Page through results
$projects = $client->projects()->get(page: 2);

// Get a single project
$project = $client->project($projectId)->get();
```

### Pages

```php
// List pages for a project (paginated)
$pages = $client->project($projectId)->pages()->get();

// Page through results
$pages = $client->project($projectId)->pages()->get(page: 2);

// Get a single page
$page = $client->project($projectId)->page($pageId)->get();
```

### Media

```php
// List media for a project (paginated)
$media = $client->project($projectId)->media()->get();

// Page through results
$media = $client->project($projectId)->media()->get(page: 2);
```

### Write Operations

Project and page write endpoints are supported.

```php
// Create a project
$project = $client->projects()->create([
    'name' => 'My Project',
    'description' => 'Project description',
]);

// Update a project
$project = $client->project($projectId)->update([
    'name' => 'Updated Project Name',
    'description' => 'Updated description',
    'public_at' => '2026-03-31T10:00:00.000000Z',
    'published_at' => '2026-03-31T10:00:00.000000Z',
]);

// Delete a project
$client->project($projectId)->delete();

// Create a page
$page = $client->project($projectId)->pages()->create([
    // ...
]);

// Update a page
$page = $client->project($projectId)->page($pageId)->update([
    // ...
]);

// Delete a page
$client->project($projectId)->page($pageId)->delete();
```

### Project Images

When creating or updating a project, pass `image` as a file path, stream resource, or `SplFileInfo`. The client will automatically send the request as `multipart/form-data`.

```php
$project = $client->projects()->create([
    'name' => 'My Project',
    'description' => 'Project description',
    'image' => __DIR__ . '/project-image.jpg',
]);

$project = $client->project($projectId)->update([
    'name' => 'Updated Project Name',
    'image' => new SplFileInfo(__DIR__ . '/project-image.jpg'),
]);
```

## Paginated Responses

List endpoints return a `PaginatedResponse` object:

```php
$projects = $client->projects()->get();

$projects->items();        // array of results for this page
$projects->currentPage();  // current page number
$projects->lastPage();     // last page number
$projects->perPage();      // results per page
$projects->total();        // total number of results
$projects->hasMore();      // true if there are more pages
```

## Caching

Responses are cached automatically when a PSR-16 cache is provided. The default TTL is 3600 seconds (1 hour).

### Bypass Cache

Use `fresh()` to skip the cache and always fetch from the API:

```php
$client->projects()->fresh()->get();
$client->project($projectId)->fresh()->get();
$client->project($projectId)->pages()->fresh()->get();
$client->project($projectId)->media()->fresh()->get();
```

### Flush Cache

Flush specific resources or everything at once:

```php
// Flush the projects list
$client->projects()->flush();

// Flush a project and all its pages and media
$client->project($projectId)->flush();

// Flush just the pages for a project
$client->project($projectId)->pages()->flush();

// Flush just the media for a project
$client->project($projectId)->media()->flush();

// Flush the entire Pagoti cache
$client->flush();
```

Successful project writes automatically invalidate related cached GET responses:

- `projects()->create(...)` flushes the projects list cache
- `project($id)->update(...)` flushes the project, its pages/media caches, and the projects list cache
- `project($id)->delete()` flushes the project, its pages/media caches, and the projects list cache

## Error Handling

All exceptions extend `Metrique\Pagoti\Exceptions\PagotiException`.

```php
use Metrique\Pagoti\Exceptions\PagotiApiException;
use Metrique\Pagoti\Exceptions\PagotiAuthException;
use Metrique\Pagoti\Exceptions\PagotiException;
use Metrique\Pagoti\Exceptions\PagotiNotFoundException;

try {
    $project = $client->project($projectId)->get();
} catch (PagotiNotFoundException $e) {
    // 404 — resource not found
} catch (PagotiAuthException $e) {
    // 401/403 — invalid or missing API key
} catch (PagotiApiException $e) {
    // other API error
    $e->getStatusCode(); // HTTP status code
    $e->getErrors();     // validation errors array from the API
} catch (PagotiException $e) {
    // network or transport failure
}
```

## License

MIT
