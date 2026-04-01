<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class SelfHostedWorkspaceScaffoldService
{
    /**
     * @return array<string, mixed>
     */
    public function scaffold(string $targetDirectory, bool $force = false): array
    {
        $targetDirectory = rtrim($targetDirectory, DIRECTORY_SEPARATOR);

        if ($targetDirectory === '') {
            throw new RuntimeException('Target workspace self-hosted tidak ditemukan.');
        }

        File::ensureDirectoryExists($targetDirectory);

        $files = $this->scaffoldFiles();
        $writtenFiles = 0;

        foreach ($files as $relativePath => $contents) {
            $destination = $targetDirectory.'/'.$relativePath;

            if (File::exists($destination) && ! $force) {
                throw new RuntimeException("File scaffold target sudah ada: {$relativePath}. Gunakan --force untuk menimpa.");
            }

            File::ensureDirectoryExists(dirname($destination));
            File::put($destination, $contents);
            $writtenFiles++;
        }

        File::put(
            $targetDirectory.'/_self_hosted_scaffold.json',
            json_encode([
                'generated_at' => now()->toIso8601String(),
                'written_file_count' => $writtenFiles,
                'files' => array_keys($files),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return [
            'target_directory' => $targetDirectory,
            'written_file_count' => $writtenFiles,
            'files' => array_keys($files),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function scaffoldFiles(): array
    {
        return [
            '.env.example' => <<<'ENV'
APP_NAME="Rafen Self-Hosted"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rafen_selfhosted
DB_USERNAME=rafen
DB_PASSWORD=

SESSION_DRIVER=file
QUEUE_CONNECTION=database
CACHE_STORE=file

LICENSE_SELF_HOSTED_ENABLED=true
LICENSE_ENFORCE=true
LICENSE_PUBLIC_KEY=
LICENSE_PUBLIC_KEY_EDITABLE=false
LICENSE_FILE_PATH=storage/app/license/rafen.lic
LICENSE_MACHINE_ID_PATH=/etc/machine-id
LICENSE_DEFAULT_GRACE_DAYS=21
ENV,
            'bootstrap/cache/.gitignore' => <<<'TEXT'
*
!.gitignore
TEXT,
            'database/.gitignore' => <<<'TEXT'
*
!.gitignore
TEXT,
            'storage/app/.gitignore' => <<<'TEXT'
*
!.gitignore
TEXT,
            'storage/app/license/.gitignore' => <<<'TEXT'
*
!.gitignore
TEXT,
            'storage/framework/cache/data/.gitignore' => <<<'TEXT'
*
!.gitignore
TEXT,
            'storage/framework/sessions/.gitignore' => <<<'TEXT'
*
!.gitignore
TEXT,
            'storage/framework/views/.gitignore' => <<<'TEXT'
*
!.gitignore
TEXT,
            'storage/logs/.gitignore' => <<<'TEXT'
*
!.gitignore
TEXT,
            'tests/Unit/.gitignore' => <<<'TEXT'
*
!.gitignore
TEXT,
            'app/Console/Commands/CreateInitialSuperAdmin.php' => <<<'PHP'
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateInitialSuperAdmin extends Command
{
    protected $signature = 'user:create-super-admin
        {name : Nama super admin}
        {email : Email super admin}
        {--password= : Password super admin}';

    protected $description = 'Buat akun super admin awal untuk instance self-hosted.';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: Str::password(16));

        $user = User::query()->updateOrCreate(
            ['email' => (string) $this->argument('email')],
            [
                'name' => (string) $this->argument('name'),
                'password' => $password,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->info('Super admin berhasil disiapkan.');
        $this->line('ID       : '.$user->id);
        $this->line('Email    : '.$user->email);
        $this->line('Password : '.$password);

        return self::SUCCESS;
    }
}
PHP,
            'app/Http/Controllers/Auth/LoginController.php' => <<<'PHP'
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show(Request $request): Response
    {
        $request->session()->regenerateToken();

        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Kredensial tidak valid.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('super-admin.settings.license'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Berhasil logout.');
    }
}
PHP,
            'app/Http/Requests/Auth/LoginRequest.php' => <<<'PHP'
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ];
    }
}
PHP,
            'app/Models/User.php' => <<<'PHP'
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }
}
PHP,
            'bootstrap/app.php' => <<<'PHP'
<?php

use App\Http\Middleware\EnsureSystemFeatureEnabled;
use App\Http\Middleware\EnsureValidSystemLicense;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'super.admin' => SuperAdminMiddleware::class,
            'system.license' => EnsureValidSystemLicense::class,
            'system.feature' => EnsureSystemFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
PHP,
            'bootstrap/providers.php' => <<<'PHP'
<?php

use App\Providers\SelfHostedLicenseServiceProvider;

return [
    SelfHostedLicenseServiceProvider::class,
];
PHP,
            'database/factories/UserFactory.php' => <<<'PHP'
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_super_admin' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => [
            'is_super_admin' => true,
        ]);
    }
}
PHP,
            'database/seeders/DatabaseSeeder.php' => <<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
PHP,
            'database/migrations/0001_01_01_000000_create_users_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_super_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
PHP,
            'resources/views/auth/login.blade.php' => <<<'BLADE'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - Rafen Self-Hosted</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <strong>Rafen</strong> Self-Hosted
        </div>
        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">Masuk ke Rafen Self-Hosted</p>

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                <form action="{{ route('login.attempt') }}" method="POST">
                    @csrf
                    <div class="input-group mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" required value="{{ old('email') }}">
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                        </div>
                    </div>

                    <div class="input-group mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-lock"></span></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-8">
                            <div class="icheck-primary">
                                <input type="checkbox" id="remember" name="remember" @checked(old('remember'))>
                                <label for="remember">Remember Me</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary btn-block">Masuk</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
BLADE,
            'resources/views/layouts/admin.blade.php' => <<<'BLADE'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Rafen Self-Hosted'))</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition layout-top-nav">
    <div class="wrapper">
        <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
            <div class="container">
                <a href="{{ route('super-admin.settings.license') }}" class="navbar-brand">
                    <span class="brand-text font-weight-bold">Rafen Self-Hosted</span>
                </a>
                <div class="navbar-nav ml-auto align-items-center">
                    <a href="{{ route('super-admin.settings.license') }}" class="nav-link">Lisensi Sistem</a>
                    @auth
                        <form action="{{ route('logout') }}" method="POST" class="mb-0 ml-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Logout</button>
                        </form>
                    @endauth
                </div>
            </div>
        </nav>

        <div class="content-wrapper">
            <div class="content pt-3">
                @includeWhen(($isSelfHostedLicenseEnabled ?? false) && ($systemLicenseSnapshot['license']->validation_error ?? false), 'self-hosted-license.partials.admin-alert')
                @yield('content')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    @stack('scripts')
</body>
</html>
BLADE,
            'routes/console.php' => <<<'PHP'
<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
PHP,
            'routes/web.php' => <<<'PHP'
<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/super-admin/settings/license');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

require __DIR__.'/self_hosted_license.php';
PHP,
            'tests/Feature/SelfHostedBootstrapTest.php' => <<<'PHP'
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('shows the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Masuk ke Rafen Self-Hosted');
});

it('allows a super admin to sign in and open the license page', function () {
    $user = User::factory()->superAdmin()->create([
        'password' => Hash::make('secret-123'),
    ]);

    $this->post(route('login.attempt'), [
        'email' => $user->email,
        'password' => 'secret-123',
    ])->assertRedirect(route('super-admin.settings.license'));

    $this->actingAs($user)
        ->get(route('super-admin.settings.license'))
        ->assertSuccessful();
});

it('blocks a non super admin from the license page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('super-admin.settings.license'))
        ->assertForbidden();
});
PHP,
            'tests/Pest.php' => <<<'PHP'
<?php

pest()->extend(Tests\TestCase::class)
    ->in('Feature');
PHP,
            'tests/TestCase.php' => <<<'PHP'
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    //
}
PHP,
        ];
    }
}
