<?php

namespace RTippin\Messenger\Tests\Http;

use RTippin\Messenger\Models\Call;
use RTippin\Messenger\Models\CallParticipant;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\HttpTestCase;

class CallsTest extends HttpTestCase
{
    /** @test */
    public function non_participant_forbidden_to_view_calls()
    {
        $this->logCurrentRequest('api.messenger.threads.calls.index');
        $thread = Thread::factory()->create();
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.calls.index', [
            'thread' => $thread->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function non_participant_forbidden_to_view_call()
    {
        $this->logCurrentRequest('api.messenger.threads.calls.show');
        $thread = Thread::factory()->create();
        $call = Call::factory()->for($thread)->owner($this->tippin)->create();
        $this->actingAs($this->doe);

        $this->getJson(route('api.messenger.threads.calls.show', [
            'thread' => $thread->id,
            'call' => $call->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function user_can_view_calls()
    {
        $this->logCurrentRequest('api.messenger.threads.calls.index');
        $thread = $this->createGroupThread($this->tippin);
        Call::factory()->for($thread)->owner($this->tippin)->ended()->create();
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.calls.index', [
            'thread' => $thread->id,
        ]))
            ->assertSuccessful()
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function user_can_view_call()
    {
        $this->logCurrentRequest('api.messenger.threads.calls.show');
        $thread = $this->createGroupThread($this->tippin);
        $call = Call::factory()->for($thread)->owner($this->tippin)->create();
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.calls.show', [
            'thread' => $thread->id,
            'call' => $call->id,
        ]))
            ->assertSuccessful()
            ->assertJson([
                'id' => $call->id,
            ]);
    }

    /** @test */
    public function user_can_view_call_participants()
    {
        $this->logCurrentRequest('api.messenger.threads.calls.participants.index');
        $thread = $this->createGroupThread($this->tippin);
        $call = $this->createCall($thread, $this->tippin);
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.calls.participants.index', [
            'thread' => $thread->id,
            'call' => $call->id,
        ]))
            ->assertSuccessful()
            ->assertJsonCount(1);
    }

    /** @test */
    public function non_participant_forbidden_to_view_call_participants()
    {
        $this->logCurrentRequest('api.messenger.threads.calls.participants.index');
        $thread = Thread::factory()->create();
        $call = Call::factory()->for($thread)->owner($this->tippin)->create();
        $this->actingAs($this->doe);

        $this->getJson(route('api.messenger.threads.calls.participants.index', [
            'thread' => $thread->id,
            'call' => $call->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function user_can_view_call_participant()
    {
        $this->logCurrentRequest('api.messenger.threads.calls.participants.show');
        $thread = $this->createGroupThread($this->tippin);
        $call = Call::factory()->for($thread)->owner($this->tippin)->create();
        $participant = CallParticipant::factory()->for($call)->owner($this->tippin)->create();
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.calls.participants.show', [
            'thread' => $thread->id,
            'call' => $call->id,
            'participant' => $participant->id,
        ]))
            ->assertSuccessful()
            ->assertJson([
                'id' => $participant->id,
            ]);
    }

    /** @test */
    public function non_participant_forbidden_to_view_call_participant()
    {
        $this->logCurrentRequest('api.messenger.threads.calls.participants.show');
        $thread = $this->createGroupThread($this->tippin);
        $call = Call::factory()->for($thread)->owner($this->tippin)->create();
        $participant = CallParticipant::factory()->for($call)->owner($this->tippin)->create();
        $this->actingAs($this->doe);

        $this->getJson(route('api.messenger.threads.calls.participants.show', [
            'thread' => $thread->id,
            'call' => $call->id,
            'participant' => $participant->id,
        ]))
            ->assertForbidden();
    }
}
