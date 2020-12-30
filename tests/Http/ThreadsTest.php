<?php

namespace RTippin\Messenger\Tests\Http;

use RTippin\Messenger\Definitions;
use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\FeatureTestCase;

class ThreadsTest extends FeatureTestCase
{
    private Thread $private;

    private Thread $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupInitialThreads();
    }

    private function setupInitialThreads(): void
    {
        $tippin = $this->userTippin();

        $doe = $this->userDoe();

        $this->group = Thread::create([
            'type' => 2,
            'subject' => 'Test Group',
            'image' => '1.png',
            'add_participants' => true,
            'invitations' => true,
        ]);

        $this->group->participants()
            ->create(array_merge(Definitions::DefaultAdminParticipant, [
                'owner_id' => $tippin->getKey(),
                'owner_type' => get_class($tippin),
            ]));

        $this->group->participants()
            ->create(array_merge(Definitions::DefaultParticipant, [
                'owner_id' => $doe->getKey(),
                'owner_type' => get_class($doe),
            ]));

        $this->private = $this->makePrivateThread(
            $tippin,
            $doe
        );
    }

    /** @test */
    public function guest_is_unauthorized()
    {
        $this->getJson(route('api.messenger.threads.index'))
            ->assertUnauthorized();
    }

    /** @test */
    public function new_user_has_no_threads()
    {
        $this->actingAs($this->generateJaneSmith());

        $this->getJson(route('api.messenger.threads.index'))
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonFragment([
                'meta' => [
                    'final_page' => true,
                    'index' => true,
                    'next_page_id' => null,
                    'next_page_route' => null,
                    'page_id' => null,
                    'per_page' => Messenger::getThreadsIndexCount(),
                    'results' => 0,
                    'total' => 0,
                ],
            ]);
    }

    /** @test */
    public function new_company_has_no_threads()
    {
        $this->actingAs($this->generateSomeCompany());

        $this->getJson(route('api.messenger.threads.index'))
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonFragment([
                'meta' => [
                    'final_page' => true,
                    'index' => true,
                    'next_page_id' => null,
                    'next_page_route' => null,
                    'page_id' => null,
                    'per_page' => Messenger::getThreadsIndexCount(),
                    'results' => 0,
                    'total' => 0,
                ],
            ]);
    }

    /** @test */
    public function user_belongs_to_two_threads()
    {
        $this->actingAs($this->userTippin());

        $this->getJson(route('api.messenger.threads.index'))
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJson([
                'data' => [
                    [
                        'type_verbose' => 'GROUP',
                        'name' => 'Test Group',
                    ],
                    [
                        'type_verbose' => 'PRIVATE',
                        'name' => 'John Doe',
                    ],
                ],
            ]);
    }

    /** @test */
    public function invalid_thread_id_not_found()
    {
        $this->actingAs($this->userTippin());

        $this->getJson(route('api.messenger.threads.show', [
            'thread' => '123456-789',
        ]))
            ->assertNotFound();
    }

    /** @test */
    public function user_forbidden_to_view_thread_they_do_not_belong_to()
    {
        $group = Thread::create([
            'type' => 2,
            'subject' => 'Empty Group',
            'image' => '2.png',
            'add_participants' => true,
            'invitations' => true,
        ]);

        $this->actingAs($this->userTippin());

        $this->getJson(route('api.messenger.threads.show', [
            'thread' => $group->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function view_individual_private_thread()
    {
        $this->actingAs($this->userTippin());

        $this->getJson(route('api.messenger.threads.show', [
            'thread' => $this->private->id,
        ]))
            ->assertStatus(200)
            ->assertJson([
                'id' => $this->private->id,
                'type' => 1,
                'type_verbose' => 'PRIVATE',
                'group' => false,
                'unread' => true,
                'name' => 'John Doe',
                'options' => [
                    'add_participants' => false,
                    'admin' => false,
                    'invitations' => false,
                ],
                'resources' => [
                    'recipient' => [
                        'provider_id' => $this->userDoe()->getKey(),
                        'name' => 'John Doe',
                    ],
                ],
            ]);
    }

    /** @test */
    public function view_individual_group_thread()
    {
        $this->actingAs($this->userTippin());

        $this->getJson(route('api.messenger.threads.show', [
            'thread' => $this->group->id,
        ]))
            ->assertStatus(200)
            ->assertJson([
                'id' => $this->group->id,
                'type' => 2,
                'type_verbose' => 'GROUP',
                'group' => true,
                'unread' => true,
                'name' => 'Test Group',
                'options' => [
                    'add_participants' => true,
                    'admin' => true,
                    'invitations' => true,
                ],
            ]);
    }
}
