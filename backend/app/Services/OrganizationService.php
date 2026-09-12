<?php

namespace App\Services;

use App\Jobs\RefreshOrganizationData;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    /**
     * @var list<string>
     */
    private const LIST_COLUMNS = [
        'id', 'name', 'url', 'rating', 'rating_updated_at', 'rating_count', 'review_count',
        'parsing_status', 'parsing_progress', 'parsing_error', 'data_updated_at',
    ];

    /**
     * @return Collection<int, Organization>
     */
    public function listFor(User $user): Collection
    {
        return $user->organizations()->latest('id')->get(self::LIST_COLUMNS);
    }

    /**
     * @param  array{name: string, url: string}  $data
     */
    public function create(User $user, array $data): Organization
    {
        return DB::transaction(function () use ($user, $data): Organization {
            $lockedUser = $user->newQuery()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->organizations()->where('url', $data['url'])->exists()) {
                throw ValidationException::withMessages([
                    'url' => 'Организация с такой ссылкой уже добавлена.',
                ]);
            }

            return $lockedUser->organizations()->create($data);
        });
    }

    public function queueRefresh(User $user, int $organizationId): Organization
    {
        return DB::transaction(function () use ($user, $organizationId): Organization {
            $organization = $user->organizations()->lockForUpdate()->findOrFail($organizationId);

            if (! in_array($organization->parsing_status, ['queued', 'running'], true)) {
                $organization->update([
                    'parsing_status' => 'queued',
                    'parsing_progress' => 0,
                    'parsing_error' => null,
                ]);

                RefreshOrganizationData::dispatch($organization->id)->afterCommit();
            }

            return $organization;
        });
    }

    /** @return LengthAwarePaginator<int, Review> */
    public function reviewsFor(User $user, int $organizationId): LengthAwarePaginator
    {
        return $this->findFor($user, $organizationId)
            ->reviews()
            ->latest('reviewed_at')
            ->latest('id')
            ->paginate(50, ['id', 'author', 'reviewed_at', 'text', 'rating']);
    }

    public function delete(User $user, int $organizationId): void
    {
        $this->findFor($user, $organizationId)->delete();
    }

    private function findFor(User $user, int $organizationId): Organization
    {
        return $user->organizations()->findOrFail($organizationId);
    }
}
