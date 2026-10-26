<?php

namespace App\Console\Commands;

use App\Models\EventsImges;
use App\Services\EventTagCacheService;
use App\Services\MediaAccessService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Closes the legacy public URLs of protected originals that media:security-plan
 * has already copied, verified and re-pointed to the private disk. The public
 * copy is moved (copy, verify, remove) to the private legacy-quarantine disk,
 * outside the webroot. Nothing is deleted, and --rollback restores it.
 */
class QuarantineLegacyMedia extends Command
{
    protected $signature = 'media:legacy-quarantine
        {manifest : Basename of a manifest in storage/app/security-audit (a security-plan manifest, or a quarantine manifest with --rollback)}
        {--execute : Move the verified public copies (default: dry run)}
        {--rollback : Copy the files of a quarantine manifest back to their public paths}
        {--references : With --rollback, also restore the legacy public references recorded by media:security-plan}';

    protected $description = 'Move verified public copies of protected legacy originals out of the webroot. Reversible; never deletes data.';

    public function handle(MediaAccessService $access): int
    {
        $name = (string) $this->argument('manifest');
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*\.json$/D', $name) || ! is_file($this->auditPath($name))) {
            $this->error('Pass the basename of an existing manifest in storage/app/security-audit.');

            return self::FAILURE;
        }
        $manifest = json_decode((string) file_get_contents($this->auditPath($name)), true);
        if (! is_array($manifest)) {
            $this->error('The manifest is not valid JSON.');

            return self::FAILURE;
        }

        return $this->option('rollback')
            ? $this->rollback($name, $manifest)
            : $this->quarantine($name, $manifest, $access, (bool) $this->option('execute'));
    }

    private function quarantine(string $name, array $plan, MediaAccessService $access, bool $execute): int
    {
        if (($plan['version'] ?? null) !== 1 || ! isset($plan['rows']) || isset($plan['quarantine'])) {
            $this->error('This is not a media:security-plan manifest.');

            return self::FAILURE;
        }
        $public = Storage::disk('public');
        $quarantine = Storage::disk('legacy-quarantine');
        $folder = pathinfo($name, PATHINFO_FILENAME);
        $result = [
            'version' => 1,
            'quarantine' => true,
            'source_manifest' => $name,
            'executed' => $execute,
            'created_at' => now()->toIso8601String(),
            'entries' => [],
        ];
        $failed = false;

        foreach ($plan['rows'] as $row) {
            if (($row['status'] ?? null) !== 'references_updated') {
                continue;
            }
            $mediaId = (int) ($row['media_id'] ?? 0);
            $media = EventsImges::with('events')->find($mediaId);
            $reason = $this->unsafeReason($media, $row, $access);
            foreach ($reason ? [null] : $this->publicPaths($media, $row, $access) as $path) {
                $entry = ['media_id' => $mediaId, 'public_path' => $path];
                if ($reason) {
                    $result['entries'][] = $entry + ['status' => 'skipped', 'reason' => $reason];

                    continue;
                }
                if ($this->sharedWithPublicMedia($path, $mediaId, $access)) {
                    $result['entries'][] = $entry + ['status' => 'skipped', 'reason' => 'shared_public_path'];

                    continue;
                }
                if (! $public->exists($path)) {
                    $result['entries'][] = $entry + ['status' => 'already_absent'];

                    continue;
                }
                $target = $folder.'/'.$mediaId.'/'.$path;
                $entry += ['quarantine_path' => $target, 'sha256' => $this->sha256($public, $path)];
                if (! $execute) {
                    $result['entries'][] = $entry + ['status' => 'planned'];

                    continue;
                }
                try {
                    if (! $quarantine->exists($target)) {
                        $quarantine->writeStream($target, $public->readStream($path));
                    }
                    if (! hash_equals($entry['sha256'], $this->sha256($quarantine, $target))) {
                        throw new \RuntimeException('Quarantine copy checksum mismatch; the public copy was kept.');
                    }
                    $public->delete($path);
                    if ($public->exists($path)) {
                        throw new \RuntimeException('The public copy could not be removed.');
                    }
                    $result['entries'][] = $entry + ['status' => 'quarantined', 'moved_at' => now()->toIso8601String()];
                } catch (\Throwable $exception) {
                    $failed = true;
                    $result['entries'][] = $entry + ['status' => 'failed', 'reason' => $exception->getMessage()];
                }
            }
        }

        $output = $this->write('quarantine-'.$folder.'-'.now()->format('Ymd-His-u').'-'.Str::random(6).'.json', $result);
        $counts = collect($result['entries'])->countBy('status')->all();
        $this->info(($execute ? 'Quarantine' : 'Dry run').' manifest: '.$output);
        $this->line(json_encode($counts));
        if (! $execute) {
            $this->warn('Dry run only. Re-run with --execute to move the planned public copies.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function rollback(string $name, array $manifest): int
    {
        if (! ($manifest['quarantine'] ?? false) || ! isset($manifest['entries'])) {
            $this->error('Rollback needs a quarantine manifest.');

            return self::FAILURE;
        }
        $public = Storage::disk('public');
        $quarantine = Storage::disk('legacy-quarantine');
        $result = ['version' => 1, 'rollback_of' => $name, 'created_at' => now()->toIso8601String(), 'entries' => []];
        $failed = false;

        foreach ($manifest['entries'] as $entry) {
            if (($entry['status'] ?? null) !== 'quarantined') {
                continue;
            }
            $path = (string) $entry['public_path'];
            $source = (string) $entry['quarantine_path'];
            try {
                if (! $quarantine->exists($source) || ! hash_equals((string) $entry['sha256'], $this->sha256($quarantine, $source))) {
                    throw new \RuntimeException('Quarantined file is missing or changed.');
                }
                if ($public->exists($path)) {
                    $status = hash_equals((string) $entry['sha256'], $this->sha256($public, $path)) ? 'already_restored' : 'public_path_occupied';
                    $failed = $failed || $status === 'public_path_occupied';
                    $result['entries'][] = ['media_id' => $entry['media_id'], 'public_path' => $path, 'status' => $status];

                    continue;
                }
                $public->writeStream($path, $quarantine->readStream($source));
                if (! hash_equals((string) $entry['sha256'], $this->sha256($public, $path))) {
                    throw new \RuntimeException('Restored copy checksum mismatch.');
                }
                $result['entries'][] = ['media_id' => $entry['media_id'], 'public_path' => $path, 'status' => 'restored'];
            } catch (\Throwable $exception) {
                $failed = true;
                $result['entries'][] = ['media_id' => $entry['media_id'], 'public_path' => $path, 'status' => 'failed', 'reason' => $exception->getMessage()];
            }
        }

        if ($this->option('references')) {
            $failed = ! $this->restoreReferences((string) ($manifest['source_manifest'] ?? ''), $result) || $failed;
        }

        $output = $this->write('rollback-'.pathinfo($name, PATHINFO_FILENAME).'-'.now()->format('Ymd-His-u').'.json', $result);
        $this->info('Rollback manifest: '.$output);
        $this->line(json_encode(collect($result['entries'])->countBy('status')->all()));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Point media back at the legacy public files, only where references are still
     * exactly what media:security-plan applied.
     */
    private function restoreReferences(string $planName, array &$result): bool
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*\.json$/D', $planName) || ! is_file($this->auditPath($planName))) {
            $this->error('The source security-plan manifest is missing; references were not restored.');

            return false;
        }
        $plan = json_decode((string) file_get_contents($this->auditPath($planName)), true);
        $ok = true;
        foreach ($plan['rows'] ?? [] as $row) {
            if (($row['status'] ?? null) !== 'references_updated') {
                continue;
            }
            $restored = DB::transaction(function () use ($row) {
                $media = EventsImges::lockForUpdate()->find((int) $row['media_id']);
                if (! $media || $media->only(array_keys($row['after'])) !== $row['after']) {
                    return false;
                }
                $media->update($row['before']);

                return true;
            });
            if ($restored) {
                app(EventTagCacheService::class)->invalidateEvent((int) EventsImges::find((int) $row['media_id'])->event_id, [(int) $row['media_id']]);
            }
            $ok = $ok && $restored;
            $result['entries'][] = ['media_id' => $row['media_id'], 'status' => $restored ? 'references_restored' : 'references_changed'];
        }

        return $ok;
    }

    private function unsafeReason(?EventsImges $media, array $row, MediaAccessService $access): ?string
    {
        if (! $media) {
            return 'media_missing';
        }
        $after = $row['after'] ?? [];
        if ($media->original_disk !== 'media' || $media->getRawOriginal('full_url') !== ($after['full_url'] ?? null)) {
            return 'references_changed';
        }
        $private = Storage::disk('media');
        if (! $private->exists($after['full_url']) || ! hash_equals((string) ($row['sha256'] ?? ''), $this->sha256($private, $after['full_url']))) {
            return 'private_copy_unverified';
        }

        return null;
    }

    /**
     * The same public paths media:security-plan lists for denial: the original,
     * and the old preview while the media is unpublished.
     */
    private function publicPaths(EventsImges $media, array $row, MediaAccessService $access): array
    {
        $before = $row['before'] ?? [];
        $paths = [];
        if (($before['original_disk'] ?? 'public') === 'public') {
            $paths[] = $access->relativePath($before['full_url'] ?? null);
        }
        if (($before['preview_disk'] ?? 'public') === 'public' && ! $access->isPublished($media)) {
            $paths[] = $access->relativePath($before['preview_url'] ?? null);
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private function sharedWithPublicMedia(string $path, int $mediaId, MediaAccessService $access): bool
    {
        $variants = [$path, '/'.$path, 'storage/'.$path, '/storage/'.$path, 'public/'.$path];

        return EventsImges::query()->whereKeyNot($mediaId)
            ->where(fn ($query) => $query->whereIn('full_url', $variants)->orWhereIn('preview_url', $variants))
            ->get()
            ->contains(fn (EventsImges $other) => (($other->original_disk ?: 'public') === 'public' && $access->relativePath($other->getRawOriginal('full_url')) === $path)
                || (($other->preview_disk ?: 'public') === 'public' && $access->relativePath($other->getRawOriginal('preview_url')) === $path));
    }

    private function sha256(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    private function auditPath(string $name): string
    {
        return storage_path('app/security-audit/'.$name);
    }

    private function write(string $name, array $data): string
    {
        $directory = storage_path('app/security-audit');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.'/'.$name;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        chmod($path, 0600);

        return $path;
    }
}
