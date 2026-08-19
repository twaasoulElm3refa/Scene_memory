<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\api\home\EventUserCreateController;
use App\Http\Requests\AdminEventsRequest;

class EventAdminCreateController extends EventUserCreateController
{
    public function createAdmin(AdminEventsRequest $request)
    {
        return $this->createEvent(
            request: $request,
            isHistorical: false,
            requiresModeration: false,
            isTrending: $request->boolean('is_trending')
        );
    }

    public function historicAdmin(AdminEventsRequest $request)
    {
        return $this->createEvent(
            request: $request,
            isHistorical: true,
            requiresModeration: false,
            isTrending: $request->boolean('is_trending')
        );
    }
}
