<?php

namespace Filament\Commands;

use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Commands\Concerns\CanIndentStrings;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;

#[AsCommand(name: 'make:filament-page', aliases: [
    'filament:make-page',
    'filament:page',
])]
class MakePageCommand extends Command
{
    use CanIndentStrings;
    use CanManipulateFiles;

    protected $description = 'Create a new Filament page class and view';

    protected $signature = 'make:filament-page {name?} {--R|resource=} {--T|type=} {--panel=} {--F|force}';

    /**
     * @var array<string>
     */
    protected $aliases = [
        'filament:make-page',
        'filament:page',
    ];

    public function handle(): int
    {
        $page = (string) str(
            $this->argument('name') ??
            text(
                label: 'What is the page name?',
                placeholder: 'EditSettings',
                required: true,
            ),
        )
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');
        $pageClass = (string) str($page)->afterLast('\\');
        $pageNamespace = str($page)->contains('\\') ?
            (string) str($page)->beforeLast('\\') :
            '';

        $resource = null;
        $resourceClass = null;
        $resourcePage = null;

        $panel = $this->option('panel');

        if ($panel) {
            $panel = Filament::getPanel($panel, isStrict: false);
        }

        if (! $panel) {
            $panels = Filament::getPanels();

            /** @var Panel $panel */
            $panel = (count($panels) > 1) ? $panels[select(
                label: 'Which panel would you like to create this in?',
                options: array_map(
                    fn (Panel $panel): string => $panel->getId(),
                    $panels,
                ),
                default: Filament::getDefaultPanel()->getId()
            )] : Arr::first($panels);
        }

        $resourceInput = $this->option('resource') ?? suggest(
            label: 'Which resource would you like to create this in?',
            options: collect($panel->getResources())
                ->filter(fn (string $namespace): bool => str($namespace)->contains('\\Resources\\'))
                ->map(
                    fn (string $namespace): string => (string) str($namespace)
                        ->afterLast('\\Resources\\')
                        ->beforeLast('Resource')
                )
                ->all(),
            placeholder: '[Optional] UserResource',
        );

        if (filled($resourceInput)) {
            $resource = (string) str($resourceInput)
                ->studly()
                ->trim('/')
                ->trim('\\')
                ->trim(' ')
                ->replace('/', '\\');

            if (! str($resource)->endsWith('Resource')) {
                $resource .= 'Resource';
            }

            $resourceClass = (string) str($resource)
                ->afterLast('\\');

            $resourcePage = $this->option('type') ?? select(
                label: 'Which type of page would you like to create?',
                options: [
                    'custom' => 'Custom',
                    'ListRecords' => 'List',
                    'CreateRecord' => 'Create',
                    'EditRecord' => 'Edit',
                    'ViewRecord' => 'View',
                    'ManageRelatedRecords' => 'Relationship',
                    'ManageRecords' => 'Manage',
                ],
                default: 'custom'
            );

            if ($resourcePage === 'ManageRelatedRecords') {
                $relationship = (string) str(text(
                    label: 'What is the relationship?',
                    placeholder: 'members',
                    required: true,
                ))
                    ->trim(' ');

                $recordTitleAttribute = (string) str(text(
                    label: 'What is the title attribute?',
                    placeholder: 'name',
                    required: true,
                ))
                    ->trim(' ');

                $tableHeaderActions = [];

                $tableHeaderActions[] = 'Actions\CreateAction::make(),';

                if ($hasAssociateAction = confirm('Is this a one-to-many relationship where the related records can be associated?')) {
                    $tableHeaderActions[] = 'Actions\AssociateAction::make(),';
                } elseif ($hasAttachAction = confirm('Is this a many-to-many relationship where the related records can be attached?')) {
                    $tableHeaderActions[] = 'Actions\AttachAction::make(),';
                }

                $tableHeaderActions = implode(PHP_EOL, $tableHeaderActions);

                $tableActions = [];

                if (confirm('Would you like an action to open each record in a read-only View modal?')) {
                    $tableActions[] = 'Actions\ViewAction::make(),';
                }

                $tableActions[] = 'Actions\EditAction::make(),';

                if ($hasAssociateAction) {
                    $tableActions[] = 'Actions\DissociateAction::make(),';
                }

                if ($hasAttachAction ?? false) {
                    $tableActions[] = 'Actions\DetachAction::make(),';
                }

                $tableActions[] = 'Actions\DeleteAction::make(),';

                if ($hasSoftDeletes = confirm('Can the related records be soft deleted?')) {
                    $tableActions[] = 'Actions\ForceDeleteAction::make(),';
                    $tableActions[] = 'Actions\RestoreAction::make(),';
                }

                $tableActions = implode(PHP_EOL, $tableActions);

                $tableBulkActions = [];

                if ($hasAssociateAction) {
                    $tableBulkActions[] = 'Actions\DissociateBulkAction::make(),';
                }

                if ($hasAttachAction ?? false) {
                    $tableBulkActions[] = 'Actions\DetachBulkAction::make(),';
                }

                $tableBulkActions[] = 'Actions\DeleteBulkAction::make(),';

                $modifyQueryUsing = '';

                if ($hasSoftDeletes) {
                    $modifyQueryUsing .= '->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([';
                    $modifyQueryUsing .= PHP_EOL . '    SoftDeletingScope::class,';
                    $modifyQueryUsing .= PHP_EOL . ']))';

                    $tableBulkActions[] = 'Actions\RestoreBulkAction::make(),';
                    $tableBulkActions[] = 'Actions\ForceDeleteBulkAction::make(),';
                }

                $tableBulkActions = implode(PHP_EOL, $tableBulkActions);
            }
        }

        if (empty($resource)) {
            $pageDirectories = $panel->getPageDirectories();
            $pageNamespaces = $panel->getPageNamespaces();

            $namespace = (count($pageNamespaces) > 1) ?
                select(
                    label: 'Which namespace would you like to create this in?',
                    options: $pageNamespaces
                ) :
                (Arr::first($pageNamespaces) ?? 'App\\Filament\\Pages');
            $path = (count($pageDirectories) > 1) ?
                $pageDirectories[array_search($namespace, $pageNamespaces)] :
                (Arr::first($pageDirectories) ?? app_path('Filament/Pages/'));
        } else {
            $resourceDirectories = $panel->getResourceDirectories();
            $resourceNamespaces = $panel->getResourceNamespaces();

            $resourceNamespace = (count($resourceNamespaces) > 1) ?
                select(
                    label: 'Which namespace would you like to create this in?',
                    options: $resourceNamespaces
                ) :
                (Arr::first($resourceNamespaces) ?? 'App\\Filament\\Resources');
            $resourcePath = (count($resourceDirectories) > 1) ?
                $resourceDirectories[array_search($resourceNamespace, $resourceNamespaces)] :
                (Arr::first($resourceDirectories) ?? app_path('Filament/Resources/'));
        }

        $view = str($page)
            ->prepend(
                (string) str(empty($resource) ? "{$namespace}\\" : "{$resourceNamespace}\\{$resource}\\pages\\")
                    ->replaceFirst('App\\', '')
            )
            ->replace('\\', '/')
            ->explode('/')
            ->map(fn ($segment) => Str::lower(Str::kebab($segment)))
            ->implode('.');

        $path = (string) str($page)
            ->prepend('/')
            ->prepend(empty($resource) ? $path : $resourcePath . "\\{$resource}\\Pages\\")
            ->replace('\\', '/')
            ->replace('//', '/')
            ->append('.php');

        $viewPath = resource_path(
            (string) str($view)
                ->replace('.', '/')
                ->prepend('views/')
                ->append('.blade.php'),
        );

        $files = [
            $path,
            ...($resourcePage === 'custom' ? [$viewPath] : []),
        ];

        if (! $this->option('force') && $this->checkForCollision($files)) {
            return static::INVALID;
        }

        $potentialCluster = empty($resource) ? ((string) str($namespace)->beforeLast('\Pages')) : null;
        $clusterAssignment = null;
        $clusterImport = null;

        if (
            filled($potentialCluster) &&
            class_exists($potentialCluster) &&
            is_subclass_of($potentialCluster, Cluster::class)
        ) {
            $clusterAssignment = $this->indentString(PHP_EOL . PHP_EOL . 'protected static ?string $cluster = ' . class_basename($potentialCluster) . '::class;');
            $clusterImport = "use {$potentialCluster};" . PHP_EOL;
        }

        if (empty($resource)) {
            $this->copyStubToApp('Page', $path, [
                'class' => $pageClass,
                'clusterAssignment' => $clusterAssignment,
                'clusterImport' => $clusterImport,
                'namespace' => $namespace . ($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'view' => $view,
            ]);
        } elseif ($resourcePage === 'ManageRelatedRecords') {
            $this->copyStubToApp('ResourceManageRelatedRecordsPage', $path, [
                'baseResourcePage' => "Filament\\Resources\\Pages\\{$resourcePage}",
                'baseResourcePageClass' => $resourcePage,
                'modifyQueryUsing' => filled($modifyQueryUsing ?? null) ? PHP_EOL . $this->indentString($modifyQueryUsing, 3) : $modifyQueryUsing ?? '',
                'namespace' => "{$resourceNamespace}\\{$resource}\\Pages" . ($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'recordTitleAttribute' => $recordTitleAttribute ?? null,
                'relationship' => $relationship ?? null,
                'resource' => "{$resourceNamespace}\\{$resource}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $pageClass,
                'tableActions' => $this->indentString($tableActions ?? '', 4),
                'tableBulkActions' => $this->indentString($tableBulkActions ?? '', 5),
                'tableFilters' => $this->indentString(
                    ($hasSoftDeletes ?? false) ? 'Tables\Filters\TrashedFilter::make()' : '//',
                    4,
                ),
                'tableHeaderActions' => $this->indentString($tableHeaderActions ?? '', 4),
                'title' => Str::headline($relationship ?? ''),
                'view' => $view,
            ]);
        } else {
            $this->copyStubToApp($resourcePage === 'custom' ? 'CustomResourcePage' : 'ResourcePage', $path, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\' . ($resourcePage === 'custom' ? 'Page' : $resourcePage),
                'baseResourcePageClass' => $resourcePage === 'custom' ? 'Page' : $resourcePage,
                'namespace' => "{$resourceNamespace}\\{$resource}\\Pages" . ($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'resource' => "{$resourceNamespace}\\{$resource}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $pageClass,
                'view' => $view,
            ]);
        }

        if (empty($resource) || $resourcePage === 'custom') {
            $this->copyStubToApp('PageView', $viewPath);
        }

        $this->components->info("Filament page [{$path}] created successfully.");

        if ($resource !== null) {
            $this->components->info("Make sure to register the page in `{$resourceClass}::getPages()`.");
        }

        return static::SUCCESS;
    }
}
