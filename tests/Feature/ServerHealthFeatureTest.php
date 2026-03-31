<?php

use App\Models\User;
use App\Services\FeatureGateService;
use App\Services\ServerHealthService;
use App\Services\WaMultiSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

function createServerHealthSuperAdmin(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);
}

it('shows pm2 wa multi session status on server health for saas', function () {
    config()->set('license.self_hosted_enabled', false);

    Process::fake([
        '/bin/systemctl is-active rafen-queue' => Process::result('active', '', 0),
        '/bin/systemctl is-active rafen-schedule.timer' => Process::result('active', '', 0),
        '/bin/systemctl is-active freeradius' => Process::result('active', '', 0),
        '/bin/systemctl is-active genieacs-cwmp' => Process::result('inactive', '', 3),
        '/bin/systemctl is-active genieacs-nbi' => Process::result('active', '', 0),
    ]);

    $manager = Mockery::mock(WaMultiSessionManager::class);
    $manager->shouldReceive('status')->once()->andReturn([
        'running' => true,
        'name' => 'wa-multi-session',
        'host' => '127.0.0.1',
        'port' => 3100,
        'url' => 'http://127.0.0.1:3100',
        'pm2_pid' => 4242,
        'pm2_status' => 'online',
    ]);
    $this->app->instance(WaMultiSessionManager::class, $manager);

    $response = $this->actingAs(createServerHealthSuperAdmin())
        ->get(route('super-admin.server-health'));

    $response->assertSuccessful()
        ->assertSee('PM2 / wa-multi-session')
        ->assertSee('PID: 4242')
        ->assertSee('http://127.0.0.1:3100');
});

it('filters self hosted server health services using licensed modules', function () {
    config()->set('license.self_hosted_enabled', true);

    Process::fake([
        '/bin/systemctl is-active rafen-queue' => Process::result('active', '', 0),
        '/bin/systemctl is-active rafen-schedule.timer' => Process::result('active', '', 0),
        '/bin/systemctl is-active freeradius' => Process::result('active', '', 0),
    ]);

    $featureGate = Mockery::mock(FeatureGateService::class);
    $featureGate->shouldReceive('isEnabled')->with('radius')->once()->andReturn(true);
    $featureGate->shouldReceive('isEnabled')->with('genieacs')->twice()->andReturn(false);
    $featureGate->shouldReceive('isEnabled')->with('wa')->once()->andReturn(false);
    $this->app->instance(FeatureGateService::class, $featureGate);

    $manager = Mockery::mock(WaMultiSessionManager::class);
    $manager->shouldNotReceive('status');
    $this->app->instance(WaMultiSessionManager::class, $manager);

    $services = app(ServerHealthService::class)->services();

    expect(collect($services)->pluck('key')->all())
        ->toBe([
            'rafen-queue',
            'rafen-schedule.timer',
            'freeradius',
        ]);
});

it('starts inactive systemd service permanently when controlled from server health', function () {
    config()->set('license.self_hosted_enabled', false);

    $statusChecks = 0;

    Process::fake(function ($process) use (&$statusChecks) {
        return match ($process->command) {
            '/bin/systemctl is-active rafen-queue' => (++$statusChecks === 1)
                ? Process::result('inactive', '', 3)
                : Process::result('active', '', 0),
            'sudo /bin/systemctl enable --now rafen-queue' => Process::result('', '', 0),
            default => Process::result('', 'Unexpected command in server health test.', 1),
        };
    });

    $manager = Mockery::mock(WaMultiSessionManager::class);
    $manager->shouldNotReceive('status');
    $this->app->instance(WaMultiSessionManager::class, $manager);

    $result = app(ServerHealthService::class)->control('rafen-queue');

    expect($result['success'])->toBeTrue()
        ->and($result['action'])->toBe('start_permanent')
        ->and($result['status'])->toBe('active')
        ->and(data_get($result, 'service.primary_action'))->toBe('restart');

    Process::assertRan(fn ($process) => $process->command === 'sudo /bin/systemctl enable --now rafen-queue');
});

it('suggests rerunning installer when sudoers for server health is missing', function () {
    config()->set('license.self_hosted_enabled', false);

    Process::fake([
        '/bin/systemctl is-active rafen-queue' => Process::result('inactive', '', 3),
        'sudo /bin/systemctl enable --now rafen-queue' => Process::result(
            '',
            'sudo: a terminal is required to read the password; either use the -S option to read from standard input or configure an askpass helper',
            1
        ),
    ]);

    $manager = Mockery::mock(WaMultiSessionManager::class);
    $manager->shouldNotReceive('status');
    $this->app->instance(WaMultiSessionManager::class, $manager);

    $result = app(ServerHealthService::class)->control('rafen-queue');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('sudoers Server Health terpasang');
});
