<?php

namespace RTippin\Messenger\Http\Controllers\Actions;

use RTippin\Messenger\Actions\Calls\EndCall as EndCallAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use RTippin\Messenger\Models\Call;
use RTippin\Messenger\Models\Thread;
use Throwable;

class EndCall
{
    use AuthorizesRequests;

    /**
     * Store or restore a call participant / join call.
     *
     * @param EndCallAction $endCall
     * @param Thread $thread
     * @param Call $call
     * @return JsonResponse
     * @throws AuthorizationException|Throwable
     */
    public function __invoke(EndCallAction $endCall,
                          Thread $thread,
                          Call $call)
    {
        $this->authorize('end', [
            $call,
            $thread
        ]);

        return $endCall->execute($call)
            ->getMessageResponse();
    }
}