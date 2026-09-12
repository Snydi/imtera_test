<?php

namespace App\Jobs;

use App\Exceptions\YandexMapsParsingException;
use App\Models\Organization;
use App\Services\YandexMapsOrganizationParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefreshOrganizationData implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $organizationId) {}

    public function handle(YandexMapsOrganizationParser $parser): void
    {
        $organization = Organization::find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $organization->update([
            'parsing_status' => 'running',
            'parsing_progress' => 1,
            'parsing_error' => null,
        ]);

        $result = $parser->parse($organization->url, function (int $progress) use ($organization): void {
            $organization->newQuery()->whereKey($organization->id)->update([
                'parsing_progress' => $progress,
            ]);
        });

        DB::transaction(function () use ($organization, $result): void {
            $timestamp = now();
            $externalIds = [];
            $rows = [];

            foreach ($result['reviews'] as $review) {
                $externalIds[] = $review['external_id'];
                $rows[] = [
                    ...$review,
                    'organization_id' => $organization->id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            if ($rows !== []) {
                DB::table('reviews')->upsert(
                    $rows,
                    ['organization_id', 'external_id'],
                    ['author', 'reviewed_at', 'text', 'rating', 'updated_at'],
                );
            }

            $reviewsToDelete = $organization->reviews();

            if ($externalIds !== []) {
                $reviewsToDelete->whereNotIn('external_id', $externalIds);
            }

            $reviewsToDelete->delete();

            $organization->update([
                'rating' => $result['rating'],
                'rating_updated_at' => $timestamp,
                'rating_count' => $result['rating_count'],
                'review_count' => $result['review_count'],
                'parsing_status' => 'succeeded',
                'parsing_progress' => 100,
                'parsing_error' => null,
                'data_updated_at' => $timestamp,
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception instanceof YandexMapsParsingException
            ? $exception->getMessage()
            : 'Не удалось обновить данные организации. Попробуйте позже.';

        Organization::whereKey($this->organizationId)->update([
            'parsing_status' => 'failed',
            'parsing_progress' => 0,
            'parsing_error' => $message,
        ]);
    }
}
