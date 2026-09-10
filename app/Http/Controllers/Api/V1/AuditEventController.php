<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditEventResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditEventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditEvent::class);

        $search = $request->string('q')->trim()->limit(100)->toString();

        $auditEvents = AuditEvent::query()
            ->forCurrentTenant()
            ->with('user:id,name,email')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('event_type', 'like', "%{$search}%")
                        ->orWhere('resource_type', 'like', "%{$search}%")
                        ->orWhere('resource_id', 'like', "%{$search}%")
                        ->orWhere('ip', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return AuditEventResource::collection($auditEvents);
    }

    public function show(AuditEvent $auditEvent): AuditEventResource
    {
        $this->authorize('view', $auditEvent);

        $auditEvent->load('user:id,name,email');

        return new AuditEventResource($auditEvent);
    }
}
