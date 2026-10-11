<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\Format;
use App\UI\Http\Info\InfoSectionInterface;
use App\UI\Http\Info\SecretMasker;
use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsTaggedItem(priority: 40)]
final readonly class DoctrineSection implements InfoSectionInterface
{
    private const ASSOCIATION_TYPES = [
        ClassMetadata::ONE_TO_ONE   => 'OneToOne',
        ClassMetadata::MANY_TO_ONE  => 'ManyToOne',
        ClassMetadata::ONE_TO_MANY  => 'OneToMany',
        ClassMetadata::MANY_TO_MANY => 'ManyToMany',
    ];

    private const FETCH_MODES = [
        ClassMetadata::FETCH_LAZY       => 'LAZY',
        ClassMetadata::FETCH_EAGER      => 'EAGER',
        ClassMetadata::FETCH_EXTRA_LAZY => 'EXTRA_LAZY',
    ];

    public function __construct(
        private ManagerRegistry $doctrine,
        private Connection $connection,
        private SecretMasker $masker,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function id(): string
    {
        return 'doctrine';
    }

    public function title(): string
    {
        return 'Doctrine';
    }

    public function icon(): string
    {
        return '🗄️';
    }

    public function collect(): array
    {
        $connection = $this->connection;
        $manager    = $this->doctrine->getManager();
        $params     = $this->masker->maskArray($connection->getParams());
        \ksort($params);

        $blocks = [
            Block::kv([
                'ORM'           => InstalledVersions::getPrettyVersion('doctrine/orm'),
                'DBAL'          => InstalledVersions::getPrettyVersion('doctrine/dbal'),
                'Bundle'        => InstalledVersions::getPrettyVersion('doctrine/doctrine-bundle'),
                'Migrations'    => InstalledVersions::isInstalled('doctrine/migrations') ? InstalledVersions::getPrettyVersion('doctrine/migrations') : null,
                'Driver'        => $connection->getDriver()::class,
                'Platform'      => Format::shortClass($connection->getDatabasePlatform()::class),
                'Connections'   => \array_keys($this->doctrine->getConnectionNames()),
                'Managers'      => \array_keys($this->doctrine->getManagerNames()),
                'Connected now' => Block::flag($connection->isConnected(), 'yes', 'not yet'),
            ], 'Versions'),
            Block::kv($params, 'Connection parameters (default)'),
            Block::lazy($this->urlGenerator->generate('info_database'), 'Live connection & migrations check'),
        ];

        $entities = $manager instanceof EntityManagerInterface ? $manager->getMetadataFactory()->getAllMetadata() : [];
        $items    = [];
        foreach ($entities as $metadata) {
            $items[] = $this->describeEntity($metadata);
        }
        $blocks[] = Block::items($items, 'Mapped entities');

        $types = Type::getTypesMap();
        \ksort($types);
        $custom   = \array_filter($types, static fn (string $class): bool => \str_starts_with($class, 'App\\'));
        $blocks[] = Block::kv($custom, 'Custom DBAL types (value objects)');
        $blocks[] = Block::items([Block::item('All DBAL types', [Block::kv($types)], (string) \count($types))]);

        return [
            'summary' => \sprintf('%s · %d entities · %d custom types', Format::shortClass($connection->getDatabasePlatform()::class), \count($entities), \count($custom)),
            'blocks'  => $blocks,
        ];
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, mixed>
     */
    private function describeEntity(ClassMetadata $metadata): array
    {
        $fields = [];
        foreach ($metadata->fieldMappings as $field => $mapping) {
            $fields[] = [
                \in_array($field, $metadata->identifier, true) ? Block::badge($field, 'info') : $field,
                $mapping->columnName,
                $mapping->type,
                Block::flag((bool) $mapping->nullable, 'yes', 'no'),
                $mapping->length,
                Block::flag((bool) $mapping->unique, 'yes', 'no'),
            ];
        }

        $associations = [];
        foreach ($metadata->associationMappings as $field => $mapping) {
            $joinColumns = \property_exists($mapping, 'joinColumns') ? \array_map(
                static fn (object $column): string => \sprintf('%s → %s%s', $column->name, $column->referencedColumnName, $column->onDelete ? ' (ON DELETE ' . $column->onDelete . ')' : ''),
                $mapping->joinColumns,
            ) : [];

            $associations[] = [
                $field,
                self::ASSOCIATION_TYPES[$mapping->type()] ?? (string) $mapping->type(),
                $mapping->targetEntity,
                self::FETCH_MODES[$mapping->fetch ?? ClassMetadata::FETCH_LAZY],
                $joinColumns,
            ];
        }

        $blocks = [Block::table(['Field', 'Column', 'Type', 'Nullable', 'Length', 'Unique'], $fields, 'Fields')];
        if ([] !== $associations) {
            $blocks[] = Block::table(['Field', 'Type', 'Target', 'Fetch', 'Join columns'], $associations, 'Associations');
        }

        return Block::item(
            $metadata->getName(),
            $blocks,
            $metadata->getTableName(),
            \sprintf('%d fields · %d associations', \count($fields), \count($associations)),
        );
    }
}
