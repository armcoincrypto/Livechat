<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiKnowledgeRule;
use iEXPackages\SupportChat\Data\SupportAiExtractedKnowledgeRules;
use Illuminate\Console\Command;

final class SupportChatKnowledgeImportExtractedCommand extends Command
{
    protected $signature = 'support-chat:knowledge-import-extracted
                            {--dry-run : Preview import without writing to DB}';

    protected $description = 'Import operator-derived knowledge rules from AI-SUPPORT-KNOWLEDGE-BASE-1A extraction.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rules = SupportAiExtractedKnowledgeRules::all();

        $this->line('Support AI Knowledge Import — source: docs/audits/ai-support-knowledge-base-1a-extraction.md');
        $this->line('Rules in bundle: '.count($rules));
        $this->line('Dry-run: '.($dryRun ? 'yes' : 'no'));
        $this->line('');

        $created = 0;
        $updated = 0;

        foreach ($rules as $row) {
            $code = (string) $row['rule_code'];
            $existing = SupportAiKnowledgeRule::query()->where('rule_code', $code)->first();

            if ($dryRun) {
                $action = $existing === null ? 'CREATE' : 'UPDATE';
                $this->line(sprintf(
                    '[%s] %s — active=%s validated=%s confidence=%s',
                    $action,
                    $code,
                    ($row['active'] ?? false) ? 'yes' : 'no',
                    ($row['requires_validation'] ?? true) ? 'needs_review' : 'ok',
                    $row['confidence'] ?? 'medium'
                ));

                continue;
            }

            $payload = [
                'category' => $row['category'],
                'title' => $row['title'],
                'intent' => $row['intent'],
                'language' => $row['language'],
                'rule_text' => $row['rule_text'],
                'answer_template' => $row['answer_template'],
                'safe_phrasing' => $row['safe_phrasing'],
                'question_patterns' => $row['question_patterns'],
                'source_conversation_ids' => $row['source_conversation_ids'],
                'source_message_count' => (int) ($row['source_message_count'] ?? 0),
                'confidence' => $row['confidence'],
                'requires_validation' => (bool) ($row['requires_validation'] ?? true),
                'active' => (bool) ($row['active'] ?? false),
                'risk_level' => $row['risk_level'] ?? 'medium',
                'tags' => $row['tags'] ?? [],
                'metadata' => $row['metadata'] ?? [],
            ];

            if ($existing === null) {
                SupportAiKnowledgeRule::query()->create(array_merge(['rule_code' => $code], $payload));
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
