<?php

namespace App\Services\Central\Audit;

use App\Models\Central\Audit\Activity;
use App\Models\Central\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;

class CentralActivityService
{
    private const string CSV_DELIMITER = ';';

    /** @param array<string, mixed> $filters
     *  @return LengthAwarePaginator<int, Activity>
     */
    public function search(User $actor, array $filters): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Activity::class);
        $this->audit($actor, 'central_activity_viewed', 'Consultation du journal central.');

        $page = $this->query($filters)->orderByDesc('activity_log.id')->paginate(20)->withQueryString();
        $this->attachSubjectUuids($page->getCollection());

        return $page;
    }

    /** @return array{activity: Activity, actorUuid: ?string, subjectUuid: ?string} */
    public function details(User $actor, Activity $activity): array
    {
        Gate::forUser($actor)->authorize('view', $activity);
        $this->audit($actor, 'central_activity_viewed', 'Consultation du journal central.');
        $actorUuid = $activity->causer_type === 'central_user' && $activity->causer_id !== null
            ? DB::table('users')->where('id', $activity->causer_id)->value('uuid') : null;
        $class = $this->centralMorphs()[$activity->subject_type] ?? null;
        $subjectUuid = $class !== null && $activity->subject_id !== null
            ? DB::table((new $class)->getTable())->where('id', $activity->subject_id)->value('uuid') : null;

        return ['activity' => $activity, 'actorUuid' => $actorUuid, 'subjectUuid' => $subjectUuid];
    }

    /** @param array<string, mixed> $filters */
    public function export(User $actor, array $filters): StreamedResponse
    {
        Gate::forUser($actor)->authorize('export', Activity::class);
        $query = $this->query($filters);
        $lastId = (int) ((clone $query)->max('activity_log.id') ?? 0);
        $this->audit($actor, 'central_activity_export_requested', 'Export du journal central demandé.');

        return response()->streamDownload(function () use ($query, $lastId): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new RuntimeException('Unable to open the export stream.');
            }
            try {
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, ['uuid', 'date_utc', 'journal', 'evenement', 'origine', 'acteur_uuid', 'sujet_uuid', 'correlation_uuid'], self::CSV_DELIMITER, '"', '');
                $query->where('activity_log.id', '<=', $lastId)->chunkById(500, function (Collection $entries) use ($stream): void {
                    $this->attachSubjectUuids($entries);
                    foreach ($entries as $entry) {
                        fputcsv($stream, array_map($this->csvCell(...), [
                            $entry->uuid, $entry->created_at?->utc()->format('Y-m-d H:i:s'), $entry->log_name,
                            $entry->event, $entry->origin->name, $entry->getAttribute('actor_uuid'),
                            $entry->getAttribute('subject_uuid'), $entry->correlation_id,
                        ]), self::CSV_DELIMITER, '"', '');
                    }
                }, 'activity_log.id', 'id');
            } finally {
                fclose($stream);
            }
        }, 'activites-centrales.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @param array<string, mixed> $filters
     *  @return Builder<Activity>
     */
    private function query(array $filters): Builder
    {
        $query = Activity::query()->leftJoin('users as activity_actor', function ($join): void {
            $join->on('activity_actor.id', '=', 'activity_log.causer_id')->where('activity_log.causer_type', '=', 'central_user');
        })->select('activity_log.*', 'activity_actor.uuid as actor_uuid');
        foreach (['log_name', 'event', 'correlation_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where('activity_log.'.$field, $filters[$field]);
            }
        }
        if (! empty($filters['from'])) {
            $query->where('activity_log.created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('activity_log.created_at', '<', \Carbon\CarbonImmutable::parse($filters['to'], 'UTC')->addDay()->startOfDay());
        }
        if (! empty($filters['actor_uuid'])) {
            $query->where('activity_actor.uuid', $filters['actor_uuid']);
        }
        if (! empty($filters['subject_uuid'])) {
            $this->filterSubject($query, $filters['subject_uuid']);
        }

        return $query;
    }

    /** @param Builder<Activity> $query */
    private function filterSubject(Builder $query, string $uuid): void
    {
        $map = $this->centralMorphs();
        $query->where(function (Builder $query) use ($map, $uuid): void {
            foreach ($map as $alias => $class) {
                $model = new $class;
                $table = $model->getTable();
                $query->orWhere(fn (Builder $branch) => $branch->where('activity_log.subject_type', $alias)
                    ->whereIn('activity_log.subject_id', $model->getConnection()->table($table)->select('id')->where('uuid', $uuid)));
            }
        });
    }

    /** @return array<string, class-string<Model>> */
    private function centralMorphs(): array
    {
        return array_filter(Relation::morphMap(), fn ($class) => is_subclass_of($class, Model::class)
            && (str_starts_with($class, 'App\\Models\\Central\\') || $class === \App\Models\User::class));
    }

    /** @param Collection<int, Activity> $activities */
    private function attachSubjectUuids(Collection $activities): void
    {
        $map = $this->centralMorphs();
        foreach ($activities->groupBy('subject_type') as $type => $entries) {
            $class = $map[$type] ?? null;
            if ($class === null) {
                continue;
            }
            $uuids = DB::table((new $class)->getTable())->whereIn('id', $entries->pluck('subject_id')->filter()->unique())->pluck('uuid', 'id');
            foreach ($entries as $entry) {
                $entry->setAttribute('subject_uuid', $uuids[$entry->subject_id] ?? null);
            }
        }
    }

    private function audit(User $actor, string $event, string $description): void
    {
        activity('privacy')->causedBy($actor)->event($event)->withProperties(['scope' => 'central_activity'])->log($description);
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\s\x00-\x1f]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
