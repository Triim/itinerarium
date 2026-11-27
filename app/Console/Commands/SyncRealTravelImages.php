<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RealTravelImages;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Brings the seeded real-travel Places of an existing installation in line with the bundled photographs.
 *
 * Only media that came from a bundle are replaced or removed: images attached by the seeder from
 * images.json, or files from the retired bundle matched by name and size. Uploaded images and
 * anything else are reported and left alone.
 */
class SyncRealTravelImages extends Command
{
    protected $signature = 'real-travel:sync-images
        {--dry-run : List the changes without making them}
        {--force : Apply the changes without asking for confirmation}';

    protected $description = 'Replace the seeded real-travel Place images with the bundled, licensed photographs';

    public function handle(RealTravelImages $bundle): int
    {
        $author = User::where('email', 'test@example.com')->first();
        if ($author === null) {
            $this->error('The real-travel author (test@example.com) does not exist; nothing to synchronise.');

            return self::FAILURE;
        }

        $images = $bundle->images();
        $retired = $bundle->retired();
        $plan = [];
        $rows = [];
        $current = 0;
        $missing = 0;

        foreach ($bundle->places($author) as $key => $place) {
            $image = $images[$key] ?? null;
            if ($image === null && ! isset($retired[$key])) {
                continue;
            }

            if ($place === null) {
                $missing++;

                continue;
            }

            $media = $place->getMedia('images');
            if ($image !== null && $media->count() === 1 && $bundle->isCurrent($media->first(), $image)) {
                $current++;

                continue;
            }

            $label = "{$place->city->name}: {$place->name}";
            $files = $media->pluck('file_name')->implode(', ');
            if ($media->contains(fn (Media $item) => ! $bundle->isSeedManaged($item, $retired[$key] ?? null))) {
                $rows[] = [$key, $label, 'keep: not a bundled image', $files, ''];

                continue;
            }

            $action = match (true) {
                $image === null && $media->isEmpty() => null,
                $image === null => 'remove',
                $media->isEmpty() => 'add',
                default => 'replace',
            };
            if ($action === null) {
                continue;
            }

            $plan[] = [$place, $media, $image];
            $rows[] = [$key, $label, $action, $files, $image ? basename($image['file']) : ''];
        }

        if ($rows !== []) {
            $this->table(['Key', 'Place', 'Action', 'Current', 'New'], $rows);
        }
        $this->info(sprintf('%d to change, %d already up to date, %d bundled places not found.', count($plan), $current, $missing));

        if ($this->option('dry-run') || $plan === []) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Apply these changes?')) {
            $this->warn('Nothing was changed.');

            return self::SUCCESS;
        }

        foreach ($plan as [$place, $media, $image]) {
            // Deleting the media also removes its files and conversions from the disk.
            $media->each->delete();
            if ($image !== null) {
                $bundle->attach($place, $image);
            }
        }

        $this->info(sprintf('Updated %d places.', count($plan)));

        return self::SUCCESS;
    }
}
