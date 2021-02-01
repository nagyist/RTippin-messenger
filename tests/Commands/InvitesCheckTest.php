<?php

namespace RTippin\Messenger\Tests\Commands;

use Illuminate\Support\Facades\Bus;
use RTippin\Messenger\Contracts\MessengerProvider;
use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Jobs\ArchiveInvalidInvites;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\FeatureTestCase;

class InvitesCheckTest extends FeatureTestCase
{
    private MessengerProvider $tippin;

    private Thread $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tippin = $this->userTippin();

        $this->group = $this->createGroupThread($this->tippin);
    }

    /** @test */
    public function invites_command_does_nothing_when_no_invalid_invites_found()
    {
        $this->group->invites()->create([
            'owner_id' => $this->tippin->getKey(),
            'owner_type' => get_class($this->tippin),
            'code' => 'TEST1234',
            'max_use' => 1,
            'uses' => 0,
            'expires_at' => null,
        ]);

        $this->artisan('messenger:invites:check-valid')
            ->expectsOutput('No invalid invites found.')
            ->assertExitCode(0);
    }

    /** @test */
    public function invites_command_does_nothing_when_invite_not_yet_expired()
    {
        $this->group->invites()->create([
            'owner_id' => $this->tippin->getKey(),
            'owner_type' => get_class($this->tippin),
            'code' => 'TEST1234',
            'max_use' => 1,
            'uses' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->artisan('messenger:invites:check-valid')
            ->expectsOutput('No invalid invites found.')
            ->assertExitCode(0);
    }

    /** @test */
    public function invites_command_does_nothing_when_invites_disabled_in_config()
    {
        Messenger::setThreadInvites(false);

        $this->artisan('messenger:invites:check-valid')
            ->expectsOutput('Thread invites are currently disabled.')
            ->assertExitCode(0);
    }

    /** @test */
    public function invites_command_runs_job_now()
    {
        $this->group->invites()->create([
            'owner_id' => $this->tippin->getKey(),
            'owner_type' => get_class($this->tippin),
            'code' => 'TEST1234',
            'max_use' => 1,
            'uses' => 1,
            'expires_at' => now(),
        ]);

        Bus::fake();

        $this->artisan('messenger:invites:check-valid', [
            '--now' => true,
        ])
            ->expectsOutput('1 invalid invites found. Archive invites completed!')
            ->assertExitCode(0);

        Bus::assertDispatched(ArchiveInvalidInvites::class);
    }

    /** @test */
    public function invites_command_dispatches_job_when_invite_has_max_use()
    {
        $this->group->invites()->create([
            'owner_id' => $this->tippin->getKey(),
            'owner_type' => get_class($this->tippin),
            'code' => 'TEST1234',
            'max_use' => 1,
            'uses' => 1,
            'expires_at' => null,
        ]);

        Bus::fake();

        $this->artisan('messenger:invites:check-valid')
            ->expectsOutput('1 invalid invites found. Archive invites dispatched!')
            ->assertExitCode(0);

        Bus::assertDispatched(ArchiveInvalidInvites::class);
    }

    /** @test */
    public function invites_command_dispatches_job_when_invite_has_expired()
    {
        $this->group->invites()->create([
            'owner_id' => $this->tippin->getKey(),
            'owner_type' => get_class($this->tippin),
            'code' => 'TEST1234',
            'max_use' => 1,
            'uses' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        Bus::fake();

        $this->travel(10)->minutes();

        $this->artisan('messenger:invites:check-valid')
            ->expectsOutput('1 invalid invites found. Archive invites dispatched!')
            ->assertExitCode(0);

        Bus::assertDispatched(ArchiveInvalidInvites::class);
    }

    /** @test */
    public function invites_command_finds_multiple_invalid_invites()
    {
        $this->group->invites()->create([
            'owner_id' => $this->tippin->getKey(),
            'owner_type' => get_class($this->tippin),
            'code' => 'TEST1234',
            'max_use' => 1,
            'uses' => 1,
            'expires_at' => null,
        ]);

        $this->group->invites()->create([
            'owner_id' => $this->tippin->getKey(),
            'owner_type' => get_class($this->tippin),
            'code' => 'TEST5678',
            'max_use' => 0,
            'uses' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        Bus::fake();

        $this->travel(10)->minutes();

        $this->artisan('messenger:invites:check-valid')
            ->expectsOutput('2 invalid invites found. Archive invites dispatched!')
            ->assertExitCode(0);

        Bus::assertDispatched(ArchiveInvalidInvites::class);
    }
}
