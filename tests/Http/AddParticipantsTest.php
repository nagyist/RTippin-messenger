<?php

namespace RTippin\Messenger\Tests\Http;

use Illuminate\Support\Facades\Event;
use RTippin\Messenger\Broadcasting\NewThreadBroadcast;
use RTippin\Messenger\Events\ParticipantsAddedEvent;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\FeatureTestCase;

class AddParticipantsTest extends FeatureTestCase
{
    private Thread $group;

    protected function setUp(): void
    {
        parent::setUp();

        $tippin = $this->userTippin();

        $doe = $this->userDoe();

        $this->group = $this->createGroupThread($tippin, $doe);

        $this->createFriends($tippin, $doe);

        $this->createFriends($tippin, $this->companyDevelopers());
    }

    /** @test */
    public function forbidden_to_view_add_participants_on_private_thread()
    {
        $tippin = $this->userTippin();

        $private = $this->createPrivateThread($tippin, $this->userDoe());

        $this->actingAs($tippin);

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $private->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function non_participant_forbidden_to_view_add_participants()
    {
        $this->actingAs($this->companyDevelopers());

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $this->group->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function non_admin_without_permission_forbidden_to_view_add_participants()
    {
        $this->actingAs($this->userDoe());

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $this->group->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function admin_forbidden_to_view_add_participants_when_disabled_from_settings()
    {
        $this->group->update([
            'add_participants' => false,
        ]);

        $this->actingAs($this->userDoe());

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $this->group->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function non_admin_with_permission_can_view_add_participants()
    {
        $doe = $this->userDoe();

        $this->group->participants()
            ->where('owner_id', '=', $doe->getKey())
            ->where('owner_type', '=', get_class($doe))
            ->first()
            ->update([
                'add_participants' => true,
            ]);

        $this->actingAs($doe);

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $this->group->id,
        ]))
            ->assertSuccessful();
    }

    /** @test */
    public function admin_can_view_add_participants()
    {
        $this->actingAs($this->userTippin());

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $this->group->id,
        ]))
            ->assertSuccessful()
            ->assertJsonCount(1)
            ->assertJson([
                [
                    'party' => [
                        'name' => 'Developers',
                    ],
                    'party_id' => $this->companyDevelopers()->getKey(),
                ],
            ]);
    }

    /** @test */
    public function admin_can_add_many_participants()
    {
        $tippin = $this->userTippin();

        $laravel = $this->companyLaravel();

        $smith = $this->createJaneSmith();

        Event::fake([
            NewThreadBroadcast::class,
            ParticipantsAddedEvent::class,
        ]);

        $this->createFriends($tippin, $smith);

        $this->createFriends($tippin, $laravel);

        $this->actingAs($tippin);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $this->group->id,
        ]), [
            'providers' => [
                [
                    'id' => $smith->getKey(),
                    'alias' => 'user',
                ],
                [
                    'id' => $laravel->getKey(),
                    'alias' => 'company',
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(2);

        Event::assertDispatched(function (NewThreadBroadcast $event) use ($smith, $laravel) {
            $this->assertContains('private-user.'.$smith->getKey(), $event->broadcastOn());
            $this->assertContains('private-company.'.$laravel->getKey(), $event->broadcastOn());
            $this->assertContains('First Test Group', $event->broadcastWith()['thread']);

            return true;
        });

        Event::assertDispatched(function (ParticipantsAddedEvent $event) use ($tippin) {
            $this->assertSame($tippin->getKey(), $event->provider->getKey());
            $this->assertSame('First Test Group', $event->thread->subject);
            $this->assertCount(2, $event->participants);

            return true;
        });
    }

    /** @test */
    public function non_admin_with_permission_can_add_participants()
    {
        $doe = $this->userDoe();

        $laravel = $this->companyLaravel();

        $this->expectsEvents([
            NewThreadBroadcast::class,
            ParticipantsAddedEvent::class,
        ]);

        $this->group->participants()
            ->where('owner_id', '=', $doe->getKey())
            ->where('owner_type', '=', get_class($doe))
            ->first()
            ->update([
                'add_participants' => true,
            ]);

        $this->createFriends($doe, $laravel);

        $this->actingAs($doe);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $this->group->id,
        ]), [
            'providers' => [
                [
                    'id' => $laravel->getKey(),
                    'alias' => 'company',
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(1);
    }

    /** @test */
    public function non_friends_are_ignored_when_adding_participants()
    {
        $tippin = $this->userTippin();

        $smith = $this->createJaneSmith();

        $laravel = $this->companyLaravel();

        $this->expectsEvents([
            NewThreadBroadcast::class,
            ParticipantsAddedEvent::class,
        ]);

        $this->createFriends($tippin, $smith);

        $this->actingAs($tippin);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $this->group->id,
        ]), [
            'providers' => [
                [
                    'id' => $smith->getKey(),
                    'alias' => 'user',
                ],
                [
                    'id' => $laravel->getKey(),
                    'alias' => 'company',
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(1);
    }

    /** @test */
    public function no_participants_added_when_no_friends_found()
    {
        $tippin = $this->userTippin();

        $smith = $this->createJaneSmith();

        $this->doesntExpectEvents([
            NewThreadBroadcast::class,
            ParticipantsAddedEvent::class,
        ]);

        $this->actingAs($tippin);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $this->group->id,
        ]), [
            'providers' => [
                [
                    'id' => $smith->getKey(),
                    'alias' => 'user',
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(0);
    }

    /** @test */
    public function existing_participant_will_be_ignored_when_adding_participants()
    {
        $tippin = $this->userTippin();

        $doe = $this->userDoe();

        $this->doesntExpectEvents([
            NewThreadBroadcast::class,
            ParticipantsAddedEvent::class,
        ]);

        $this->actingAs($tippin);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $this->group->id,
        ]), [
            'providers' => [
                [
                    'id' => $doe->getKey(),
                    'alias' => 'user',
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(0);
    }

    /**
     * @test
     * @dataProvider providersValidation
     * @param $providers
     * @param $errors
     */
    public function add_participants_checks_providers($providers, $errors)
    {
        $this->actingAs($this->userTippin());

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $this->group->id,
        ]), [
            'providers' => $providers,
        ])
            ->assertJsonValidationErrors($errors);
    }

    public function providersValidation(): array
    {
        return [
            'Alias and ID cannot be null' => [
                [['alias' => null, 'id' => null]],
                ['providers.0.alias', 'providers.0.id'],
            ],
            'Alias and ID cannot be empty' => [
                [['alias' => '', 'id' => '']],
                ['providers.0.alias', 'providers.0.id'],
            ],
            'Alias must be a string' => [
                [['alias' => 123, 'id' => 1]],
                ['providers.0.alias'],
            ],
            'Providers array cannot be empty' => [
                [[]],
                ['providers.0.alias', 'providers.0.id'],
            ],
            'Validates all items in the array' => [
                [['alias' => 'user', 'id' => 1], ['alias' => null, 'id' => null]],
                ['providers.1.alias', 'providers.1.id'],
            ],
        ];
    }
}