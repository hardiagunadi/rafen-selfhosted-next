<?php

namespace App\Console\Commands;

use App\Services\SelfHostedCutoverPlanService;
use Illuminate\Console\Command;

class ShowSelfHostedCutoverPlan extends Command
{
    protected $signature = 'self-hosted:cutover-plan {--json : Output the cutover plan as JSON}';

    protected $description = 'Tampilkan runbook cutover cluster self-hosted dari repo SaaS ke repo terpisah.';

    public function handle(SelfHostedCutoverPlanService $cutoverPlanService): int
    {
        $plan = $cutoverPlanService->build();

        if ($this->option('json')) {
            $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Self-Hosted Cutover Plan');
        $this->line('Source Repo      : '.$plan['source_repo']);
        $this->line('Destination Repo : '.$plan['destination_repo']);
        $this->line('Feature Flag     : '.$plan['feature_flag']);
        $this->newLine();

        foreach ([
            'preflight_checks' => 'Preflight Checks',
            'stage_and_import_steps' => 'Stage And Import Steps',
            'saas_cleanup_candidates' => 'SaaS Cleanup Candidates',
            'manual_patch_targets' => 'Manual Patch Targets',
            'post_cutover_tasks' => 'Post Cutover Tasks',
            'verification_commands' => 'SaaS Verification Commands',
            'self_hosted_repo_verification' => 'Self-Hosted Repo Verification',
        ] as $key => $label) {
            $this->line($label.':');

            foreach ($plan[$key] as $item) {
                $this->line('  - '.$item);
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
