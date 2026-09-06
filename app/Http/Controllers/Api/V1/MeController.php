<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Authenticated session bootstrap for the Vue app shell.
     */
    public function __invoke(Request $request): UserResource
    {
        abort_unless($request->user(), 401);

        return new UserResource($request->user());
    }
}
