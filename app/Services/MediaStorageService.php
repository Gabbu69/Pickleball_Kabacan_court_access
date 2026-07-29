<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaStorageService
{
    /**
     * @return array{path:string,disk:string,url:?string,mime:?string,bytes:?int}
     */
    public function store(UploadedFile $file, string $directory, string $visibility): array
    {
        $token = $this->token($visibility);

        if ($token) {
            return $this->storeInVercelBlob($file, $directory, $visibility, $token);
        }

        $disk = $visibility === 'public' ? 'public' : 'local';
        $stored = $file->store($directory, $disk);

        if (! $stored) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        return [
            'path' => $visibility === 'public' ? 'storage/'.$stored : $stored,
            'disk' => $disk,
            'url' => $visibility === 'public' ? Storage::disk('public')->url($stored) : null,
            'mime' => $file->getMimeType(),
            'bytes' => $file->getSize(),
        ];
    }

    public function delete(?string $path, ?string $disk, ?string $url = null): void
    {
        if (! $path) {
            return;
        }

        if (str_starts_with((string) $disk, 'vercel_blob_')) {
            $visibility = str_ends_with((string) $disk, '_public') ? 'public' : 'private';
            $token = $this->token($visibility);

            if (! $token || ! $url) {
                return;
            }

            $this->blobRequest($token)
                ->asJson()
                ->post('https://vercel.com/api/blob/delete', ['urls' => [$url]])
                ->throw();

            return;
        }

        $localDisk = $disk ?: 'local';
        $localPath = $localDisk === 'public' ? Str::after($path, 'storage/') : $path;
        Storage::disk($localDisk)->delete($localPath);
    }

    public function privateDownload(string $path, string $disk, ?string $url = null): Response
    {
        if ($disk !== 'vercel_blob_private') {
            throw new RuntimeException('The requested media is not stored in Vercel Blob.');
        }

        $token = $this->token('private');

        if (! $token) {
            throw new RuntimeException('Private Blob credentials are not configured.');
        }

        $target = $url ?: sprintf(
            'https://%s.private.blob.vercel-storage.com/%s',
            $this->storeId($token),
            ltrim($path, '/'),
        );

        return Http::withToken($token)->timeout(20)->get($target)->throw();
    }

    private function storeInVercelBlob(UploadedFile $file, string $directory, string $visibility, string $token): array
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $pathname = trim($directory, '/').'/'.Str::uuid().'.'.$extension;
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        $response = $this->blobRequest($token)
            ->withHeaders([
                'x-content-type' => $file->getMimeType() ?: 'application/octet-stream',
                'x-vercel-blob-access' => $visibility,
                'x-add-random-suffix' => '0',
                'x-allow-overwrite' => '0',
                'x-cache-control-max-age' => $visibility === 'public' ? '2592000' : '60',
                'x-content-length' => (string) strlen($contents),
            ])
            ->withBody($contents, $file->getMimeType() ?: 'application/octet-stream')
            ->put('https://vercel.com/api/blob/?pathname='.rawurlencode($pathname))
            ->throw()
            ->json();

        if (! is_array($response) || empty($response['pathname']) || empty($response['url'])) {
            throw new RuntimeException('Vercel Blob returned an invalid upload response.');
        }

        return [
            'path' => $response['pathname'],
            'disk' => 'vercel_blob_'.$visibility,
            'url' => $response['url'],
            'mime' => $response['contentType'] ?? $file->getMimeType(),
            'bytes' => $file->getSize(),
        ];
    }

    private function blobRequest(string $token)
    {
        return Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 250)
            ->withHeaders([
                'x-api-version' => '12',
                'x-vercel-blob-store-id' => $this->storeId($token),
                'x-api-blob-request-id' => $this->storeId($token).':'.now()->getTimestampMs().':'.Str::random(12),
            ]);
    }

    private function token(string $visibility): ?string
    {
        $token = $visibility === 'public'
            ? config('services.vercel_blob.public_token')
            : config('services.vercel_blob.private_token');

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    private function storeId(string $token): string
    {
        $parts = explode('_', $token, 5);

        if (count($parts) < 5 || $parts[3] === '') {
            throw new RuntimeException('The Vercel Blob token format is invalid.');
        }

        return $parts[3];
    }
}
