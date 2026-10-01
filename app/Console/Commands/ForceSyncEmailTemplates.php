<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ForceSyncEmailTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:sync-force';

    protected $description = 'Force synchronize all email templates in the database with the default definitions in EmailTemplateController';

    public function handle()
    {
        $controller = new \App\Http\Controllers\Api\EmailTemplateController();

        // Use reflection to access the private getDefaults method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getDefaults');
        $method->setAccessible(true);
        $defaults = $method->invoke($controller);

        $this->info("Starting force sync of " . count($defaults) . " templates...");

        foreach ($defaults as $type => $data) {
            $template = \App\Models\EmailTemplate::where('type', $type)->first();

            if ($template) {
                $template->update([
                    'subject' => $data['subject'],
                    'body' => $data['body'],
                    'placeholders' => $data['placeholders'],
                    'version' => $template->version + 1
                ]);
                $this->info("Updated template: {$type}");
            } else {
                \App\Models\EmailTemplate::create([
                    'type' => $type,
                    'subject' => $data['subject'],
                    'body' => $data['body'],
                    'placeholders' => $data['placeholders'],
                    'version' => 1
                ]);
                $this->info("Created template: {$type}");
            }
        }

        $this->info("Force sync complete!");
        return 0;
    }
}
