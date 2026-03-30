<?php

namespace App\Console\Commands;

use App\Services\SelfHostedWorkspaceAuditService;
use Illuminate\Console\Command;
use RuntimeException;

class AuditSelfHostedWorkspace extends Command
{
    protected $signature = 'self-hosted:audit-workspace
        {target : Directory workspace self-hosted}
        {--json : Output audit as JSON}';

    protected $description = 'Audit workspace self-hosted untuk menemukan dependency internal App yang masih hilang.';

    public function handle(SelfHostedWorkspaceAuditService $auditService): int
    {
        try {
            $report = $auditService->audit((string) $this->argument('target'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Self-Hosted Workspace Audit');
        $this->line('Workspace Directory      : '.$report['workspace_directory']);
        $this->line('PHP File Count           : '.$report['php_file_count']);
        $this->line('Missing Dependency Count : '.$report['missing_dependency_count']);
        $this->line('Portable Runtime Files   : '.$report['portable_runtime_php_file_count']);
        $this->line('Portable Runtime Missing : '.$report['portable_runtime_missing_dependency_count']);
        $this->line('Test Files               : '.$report['test_php_file_count']);
        $this->line('Test Missing             : '.$report['test_missing_dependency_count']);
        $this->line('Reference Files          : '.$report['reference_php_file_count']);
        $this->line('Reference Missing        : '.$report['reference_missing_dependency_count']);
        $this->newLine();

        if ($report['missing_dependency_count'] === 0) {
            $this->info('Tidak ada dependency App yang hilang.');

            return self::SUCCESS;
        }

        $this->renderDependencySection('Portable Runtime Gaps', $report['portable_runtime_missing_dependencies']);
        $this->renderDependencySection('Test Gaps', $report['test_missing_dependencies']);
        $this->renderDependencySection('Reference Gaps', $report['reference_missing_dependencies']);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{expected_path: string, referenced_by: list<string>}>  $dependencies
     */
    private function renderDependencySection(string $title, array $dependencies): void
    {
        if ($dependencies === []) {
            return;
        }

        $this->info($title);

        foreach ($dependencies as $className => $details) {
            $this->line($className.' => '.$details['expected_path']);

            foreach ($details['referenced_by'] as $referencedBy) {
                $this->line('  - referenced by '.$referencedBy);
            }
        }

        $this->newLine();
    }
}
