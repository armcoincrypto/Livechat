<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiTemplate;
use iEXPackages\SupportChat\Data\SupportAiOperatorTemplates;
use Illuminate\Console\Command;

final class SupportChatTemplateImportCommand extends Command
{
    protected $signature = 'support-chat:template-import
                            {--dry-run : Preview import without writing to DB}';

    protected $description = 'Import operator reply templates from AI-SUPPORT-OPERATOR-REPLY-MINING-1.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $templates = SupportAiOperatorTemplates::all();

        $this->line('Support AI Template Import — source: docs/audits/ai-support-operator-reply-mining-1.md');
        $this->line('Templates in bundle: '.count($templates));
        $this->line('Dry-run: '.($dryRun ? 'yes' : 'no'));
        $this->line('');

        $created = 0;
        $updated = 0;

        foreach ($templates as $row) {
            $code = (string) $row['template_code'];
            $existing = SupportAiTemplate::query()->where('template_code', $code)->first();

            if ($dryRun) {
                $action = $existing === null ? 'CREATE' : 'UPDATE';
                $this->line(sprintf(
                    '[%s] %s — type=%s freq=%d active=%s',
                    $action,
                    $code,
                    $row['template_type'] ?? 'policy',
                    (int) ($row['frequency'] ?? 0),
                    ($row['active'] ?? false) ? 'yes' : 'no'
                ));

                continue;
            }

            $payload = [
                'intent' => $row['intent'],
                'category' => $row['category'],
                'title' => $row['title'],
                'template_text' => $row['template_text'],
                'template_type' => $row['template_type'],
                'language' => $row['language'],
                'frequency' => (int) ($row['frequency'] ?? 0),
                'confidence' => $row['confidence'] ?? 'medium',
                'active' => (bool) ($row['active'] ?? true),
                'requires_validation' => (bool) ($row['requires_validation'] ?? false),
                'source_conversation_ids' => $row['source_conversation_ids'] ?? [],
                'metadata' => $row['metadata'] ?? [],
            ];

            if ($existing === null) {
                SupportAiTemplate::query()->create(array_merge(['template_code' => $code], $payload));
                $created++;
            } else {
                $existing->update($payload);
                $updated++;
            }
        }

        if ($dryRun) {
            $this->line('');
            $this->info('Dry-run complete — no DB changes.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info("Import complete: {$created} created, {$updated} updated.");

        return self::SUCCESS;
    }
}
