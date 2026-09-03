<?php

namespace App\Services;

use App\Repositories\Contracts\Auth\ProfileRepositoryInterface;

class ProfileService
{

    public function __construct(
        protected ProfileRepositoryInterface $repository
    )
    {}


    public function activity(
        int $userId,
        int $page=1
    ){

        return $this->repository
            ->getProfileActivity(
                $userId,
                $page
            );

    }

}
