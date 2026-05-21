<?php

namespace App\Repositories\Contracts\Auth;

interface AdminAuthRepositoryInterface
{
    public function login($request);
}
