<?php

namespace App\Command;

use App\Entity\Post;
use App\Entity\Reaction;
use App\Entity\Reply;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:community:doctrine-check', description: 'Validate Doctrine mapping for community post entities only')]
class CommunityDoctrineCheckCommand extends Command
{
    private const COMMUNITY_ENTITIES = [
        Post::class,
        Reply::class,
        Reaction::class,
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('with-db', null, InputOption::VALUE_NONE, 'Also check metadata/database schema sync (requires DB connection).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Community Doctrine Check');

        $metadataFactory = $this->entityManager->getMetadataFactory();
        $errors = [];

        foreach (self::COMMUNITY_ENTITIES as $entityClass) {
            if ($metadataFactory->isTransient($entityClass)) {
                $errors[] = sprintf('%s is transient (not managed by Doctrine).', $entityClass);
                continue;
            }

            $metadata = $metadataFactory->getMetadataFor($entityClass);

            foreach ($metadata->getAssociationNames() as $field) {
                $targetEntity = $metadata->getAssociationTargetClass($field);
                if ($targetEntity === '') {
                    $errors[] = sprintf('%s::%s has an invalid targetEntity mapping.', $entityClass, $field);
                    continue;
                }

                if (!class_exists($targetEntity)) {
                    $errors[] = sprintf('%s::%s references missing class %s.', $entityClass, $field, $targetEntity);
                    continue;
                }

                if ($metadataFactory->isTransient($targetEntity)) {
                    $errors[] = sprintf('%s::%s targets %s which is not a managed Doctrine entity.', $entityClass, $field, $targetEntity);
                }
            }
        }

        $validator = new SchemaValidator($this->entityManager);
        $mappingErrors = $validator->validateMapping();
        foreach (self::COMMUNITY_ENTITIES as $entityClass) {
            foreach ($mappingErrors[$entityClass] ?? [] as $message) {
                $errors[] = sprintf('%s: %s', $entityClass, $message);
            }
        }

        if ($input->getOption('with-db')) {
            try {
                if (!$validator->schemaInSyncWithMetadata()) {
                    $errors[] = 'Database schema is not in sync with entity metadata.';
                }
            } catch (\Throwable $exception) {
                $errors[] = 'DB sync check failed: ' . $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $io->error('Community Doctrine check failed.');
            $io->listing($errors);

            return Command::FAILURE;
        }

        $io->success('Community entities mapping is valid: Post, Reply, Reaction.');

        return Command::SUCCESS;
    }
}
