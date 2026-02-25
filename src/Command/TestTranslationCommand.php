<?php
// src/Command/TestTranslationCommand.php
namespace App\Command;

use App\Service\TranslationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-translation',
    description: 'Test the translation service'
)]
class TestTranslationCommand extends Command
{
    private TranslationService $translationService;

    public function __construct(TranslationService $translationService)
    {
        parent::__construct();
        $this->translationService = $translationService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Translation Service Test');
        
        // Test data
        $testCases = [
            ['text' => 'Hello, how are you?', 'target' => 'fr', 'expected' => 'French'],
            ['text' => 'Docker works locally', 'target' => 'es', 'expected' => 'Spanish'],
            ['text' => 'Good morning', 'target' => 'de', 'expected' => 'German'],
        ];

        $io->section('Running Tests');
        
        $success = 0;
        $failed = 0;

        foreach ($testCases as $index => $test) {
            $io->write(sprintf(
                'Test %d: "%s" → %s ... ',
                $index + 1,
                substr($test['text'], 0, 30),
                strtoupper($test['target'])
            ));

            try {
                $translated = $this->translationService->translateForItem(
                    $test['text'],
                    $test['target'],
                    'post',
                    1,
                    'en'
                );

                if (empty($translated)) {
                    $io->writeln('<e>FAILED</e> (empty result)');
                    $failed++;
                } else {
                    $io->writeln('<info>SUCCESS</info>');
                    $io->writeln('   → ' . $translated);
                    $success++;
                }
            } catch (\Exception $e) {
                $io->writeln('<e>FAILED</e>');
                $io->writeln('   Error: ' . $e->getMessage());
                $failed++;
            }

            // Small delay to avoid rate limiting
            if ($index < count($testCases) - 1) {
                sleep(2);
            }
        }

        // Summary
        $io->newLine();
        $io->section('Test Results');
        $io->writeln(sprintf('✓ Success: <info>%d</info>', $success));
        $io->writeln(sprintf('✗ Failed: <e>%d</e>', $failed));

        if ($failed === 0) {
            $io->success('All translation tests passed!');
            return Command::SUCCESS;
        } else {
            $io->error('Some translation tests failed. Check logs for details.');
            return Command::FAILURE;
        }
    }
}