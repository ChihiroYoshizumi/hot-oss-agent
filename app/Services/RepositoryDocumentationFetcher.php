<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class RepositoryDocumentationFetcher
{
    private const BASE_URL = 'https://api.github.com';
    private const MAX_DOCUMENTS = 4; // Including README
    private const MAX_DOCUMENT_CHARACTERS = 6000;
    private const MAX_TOTAL_CHARACTERS = 20000;

    /**
     * @return array<string, string> path => content
     */
    public function fetchDocumentation(string $owner, string $repository): array
    {
        $documents = [];
        $totalCharacters = 0;

        if ($readme = $this->fetchReadme($owner, $repository)) {
            $this->addDocument($documents, $totalCharacters, $readme['path'], $readme['content']);
        }

        if (count($documents) >= self::MAX_DOCUMENTS) {
            return $documents;
        }

        $contents = $this->githubRequest("repos/{$owner}/{$repository}/contents");

        if (! is_array($contents)) {
            return $documents;
        }

        foreach ($contents as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (count($documents) >= self::MAX_DOCUMENTS || $totalCharacters >= self::MAX_TOTAL_CHARACTERS) {
                break;
            }

            $type = $item['type'] ?? '';
            $name = $item['name'] ?? '';

            if ($type === 'file' && $this->looksLikeDocumentation($name)) {
                if ($file = $this->fetchFile($owner, $repository, $item['path'] ?? $name)) {
                    $this->addDocument($documents, $totalCharacters, $file['path'], $file['content']);
                }

                continue;
            }

            if ($type === 'dir' && $this->looksLikeDocumentationDirectory($name)) {
                $this->ingestDirectory($documents, $totalCharacters, $owner, $repository, $item['path'] ?? $name);
            }
        }

        return $documents;
    }

    private function ingestDirectory(array &$documents, int &$totalCharacters, string $owner, string $repository, string $path): void
    {
        $items = $this->githubRequest("repos/{$owner}/{$repository}/contents/{$path}");

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if (! is_array($item) || ($item['type'] ?? '') !== 'file') {
                continue;
            }

            if (! $this->looksLikeDocumentation($item['name'] ?? '')) {
                continue;
            }

            if (count($documents) >= self::MAX_DOCUMENTS || $totalCharacters >= self::MAX_TOTAL_CHARACTERS) {
                break;
            }

            if ($file = $this->fetchFile($owner, $repository, $item['path'] ?? '')) {
                $this->addDocument($documents, $totalCharacters, $file['path'], $file['content']);
            }
        }
    }

    private function fetchReadme(string $owner, string $repository): ?array
    {
        $payload = $this->githubRequest("repos/{$owner}/{$repository}/readme");

        if (! is_array($payload)) {
            return null;
        }

        return $this->extractContent($payload);
    }

    private function fetchFile(string $owner, string $repository, string $path): ?array
    {
        if ($path === '') {
            return null;
        }

        $payload = $this->githubRequest("repos/{$owner}/{$repository}/contents/{$path}");

        if (! is_array($payload)) {
            return null;
        }

        if (($payload['type'] ?? '') !== 'file') {
            return null;
        }

        return $this->extractContent($payload);
    }

    private function extractContent(array $payload): ?array
    {
        $content = $payload['content'] ?? null;

        if (! is_string($content) || $content === '') {
            return null;
        }

        $encoding = $payload['encoding'] ?? 'base64';
        $decoded = $encoding === 'base64'
            ? base64_decode(str_replace("\n", '', $content), true)
            : $content;

        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        if (str_contains($decoded, "\0")) {
            return null;
        }

        $normalized = str_replace("\r\n", "\n", $decoded);
        $normalized = trim($normalized);

        if ($normalized === '') {
            return null;
        }

        return [
            'path' => $payload['path'] ?? ($payload['name'] ?? 'document'),
            'content' => $normalized,
        ];
    }

    private function addDocument(array &$documents, int &$totalCharacters, string $path, string $content): void
    {
        if ($totalCharacters >= self::MAX_TOTAL_CHARACTERS) {
            return;
        }

        $truncated = Str::limit($content, self::MAX_DOCUMENT_CHARACTERS, '...');

        if ($truncated === '') {
            return;
        }

        $documents[$path] = $truncated;
        $totalCharacters += strlen($truncated);
    }

    private function looksLikeDocumentation(string $name): bool
    {
        $lower = Str::lower($name);

        return Str::endsWith($lower, ['.md', '.mdx', '.markdown'])
            || in_array($lower, ['readme', 'readme.md', 'readme.txt'], true);
    }

    private function looksLikeDocumentationDirectory(string $name): bool
    {
        $lower = Str::lower($name);

        return in_array($lower, ['docs', 'documentation', 'doc'], true);
    }

    private function githubRequest(string $path): mixed
    {
        $response = $this->http()->get($path);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('GitHub API request failed for path [%s].', $path));
        }

        return $response->json();
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl(self::BASE_URL)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => config('app.name', 'hot-oss-agent'),
            ])
            ->timeout(20)
            ->retry(2, 500);

        $token = config('services.github.token') ?? env('GITHUB_TOKEN');

        if ($token) {
            $request = $request->withToken($token);
        }

        return $request;
    }
}
