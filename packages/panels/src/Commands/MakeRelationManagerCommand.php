<?php

namespace Filament\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Commands\Concerns\CanIndentStrings;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'make:filament-relation-manager', aliases: [
    'filament:make-relation-manager',
    'filament:relation-manager',
])]
class MakeRelationManagerCommand extends Command
{
    use CanIndentStrings;
    use CanManipulateFiles;

    protected $description = 'Create a new Filament relation manager class for a resource';

    protected $signature = 'make:filament-relation-manager {resource?} {relationship?} {recordTitleAttribute?} {--attach} {--associate} {--soft-deletes} {--view} {--panel=} {--F|force}';

    /**
     * @var array<string>
     */
    protected $aliases = [
        'filament:make-relation-manager',
        'filament:relation-manager',
    ];

    public function handle(): int
    {
        $resource = (string) str(
            $this->argument('resource') ?? text(
                label: 'What is the resource you would like to create this in?',
                placeholder: 'DepartmentResource',
                required: true,
            ),
        )
            ->studly()
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');

        if (! str($resource)->endsWith('Resource')) {
            $resource .= 'Resource';
        }

        $relationship = (string) str($this->argument('relationship') ?? text(
            label: 'What is the relationship?',
            placeholder: 'members',
            required: true,
        ))
            ->trim(' ');
        $managerClass = (string) str($relationship)
            ->studly()
            ->append('RelationManager');

        $recordTitleAttribute = (string) str($this->argument('recordTitleAttribute') ?? text(
            label: 'What is the title attribute?',
            placeholder: 'name',
            required: true,
        ))
            ->trim(' ');

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

        $path = (string) str($managerClass)
            ->prepend("{$resourcePath}/{$resource}/RelationManagers/")
            ->replace('\\', '/')
            ->append('.php');

        if (! $this->option('force') && $this->checkForCollision([
            $path,
        ])) {
            return static::INVALID;
        }

        $tableHeaderActions = [];

        $tableHeaderActions[] = 'Actions\CreateAction::make(),';

        if ($this->option('associate')) {
            $tableHeaderActions[] = 'Actions\AssociateAction::make(),';
        }

        if ($this->option('attach')) {
            $tableHeaderActions[] = 'Actions\AttachAction::make(),';
        }

        $tableHeaderActions = implode(PHP_EOL, $tableHeaderActions);

        $tableActions = [];

        if ($this->option('view')) {
            $tableActions[] = 'Actions\ViewAction::make(),';
        }

        $tableActions[] = 'Actions\EditAction::make(),';

        if ($this->option('associate')) {
            $tableActions[] = 'Actions\DissociateAction::make(),';
        }

        if ($this->option('attach')) {
            $tableActions[] = 'Actions\DetachAction::make(),';
        }

        $tableActions[] = 'Actions\DeleteAction::make(),';

        if ($this->option('soft-deletes')) {
            $tableActions[] = 'Actions\ForceDeleteAction::make(),';
            $tableActions[] = 'Actions\RestoreAction::make(),';
        }

        $tableActions = implode(PHP_EOL, $tableActions);

        $tableBulkActions = [];

        if ($this->option('associate')) {
            $tableBulkActions[] = 'Actions\DissociateBulkAction::make(),';
        }

        if ($this->option('attach')) {
            $tableBulkActions[] = 'Actions\DetachBulkAction::make(),';
        }

        $tableBulkActions[] = 'Actions\DeleteBulkAction::make(),';

        $modifyQueryUsing = '';

        if ($this->option('soft-deletes')) {
            $modifyQueryUsing .= '->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([';
            $modifyQueryUsing .= PHP_EOL . '    SoftDeletingScope::class,';
            $modifyQueryUsing .= PHP_EOL . ']))';

            $tableBulkActions[] = 'Actions\ForceDeleteBulkAction::make(),';
            $tableBulkActions[] = 'Actions\RestoreBulkAction::make(),';
        }

        $tableBulkActions = implode(PHP_EOL, $tableBulkActions);

        $this->copyStubToApp('RelationManager', $path, [
            'modifyQueryUsing' => filled($modifyQueryUsing) ? PHP_EOL . $this->indentString($modifyQueryUsing, 3) : $modifyQueryUsing,
            'namespace' => "{$resourceNamespace}\\{$resource}\\RelationManagers",
            'managerClass' => $managerClass,
            'recordTitleAttribute' => $recordTitleAttribute,
            'relationship' => $relationship,
            'tableActions' => $this->indentString($tableActions, 4),
            'tableBulkActions' => $this->indentString($tableBulkActions, 5),
            'tableFilters' => $this->indentString(
                $this->option('soft-deletes') ? 'Tables\Filters\TrashedFilter::make()' : '//',
                4,
            ),
            'tableHeaderActions' => $this->indentString($tableHeaderActions, 4),
        ]);

        $this->components->info("Filament relation manager [{$path}] created successfully.");

        $this->components->info("Make sure to register the relation in `{$resource}::getRelations()`.");

        return static::SUCCESS;
    }
}
