<?php

namespace App\Repositories\Contracts\Events;

interface EventRepositoryInterface
{
    public function create(array $data);
    public function trendingEvents();
    public function show($slug);
    public function findBySlug(string $slug);
    public function findBySlugOrFail(string $slug);
    public function findById(int $id);
    public function findByIdOrFail(int $id);
    public function firstOrCreateView(int $eventId, string $ipAddress): void;
    public function count(): int;
    public function countByUserId(int $userId): int;
    public function countByCityIds($cityIds): int;
    public function whereInCityIds($cityIds);
    public function allActivePaginated(int $perPage);
    public function historicalActivePaginated(int $perPage);
    public function filteredActive(array $filters);
    public function randomActive(int $take = 8);
    public function gateEventsByCityIds($cityIds);
    public function findSingleDetailedBySlug(string $slug);
    public function findWithAdminRelationsById(int $id);
    public function wishlistEventsPaginated($eventIds, int $perPage = 5);
    public function daily($today);
    public function memoriesQuery();
    public function creatorEvents(int $userId);
    public function dashboardEvents(int $userId);
}
