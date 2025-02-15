<?php

namespace Filament\Tables\Table\Concerns;

use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Enums\RecordCheckboxPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;

trait HasBulkActions
{
    /**
     * @var array<BulkAction | ActionGroup>
     */
    protected array $bulkActions = [];

    /**
     * @var array<string, BulkAction>
     */
    protected array $flatBulkActions = [];

    protected ?Closure $checkIfRecordIsSelectableUsing = null;

    protected bool | Closure | null $selectsCurrentPageOnly = false;

    protected RecordCheckboxPosition | Closure | null $recordCheckboxPosition = null;

    protected bool | Closure | null $isSelectable = null;

    /**
     * @param  array<BulkAction | ActionGroup> | ActionGroup  $actions
     */
    public function bulkActions(array | ActionGroup $actions): static
    {
        $this->bulkActions = [];
        $this->pushBulkActions($actions);

        return $this;
    }

    /**
     * @param  array<BulkAction | ActionGroup> | ActionGroup  $actions
     */
    public function pushBulkActions(array | ActionGroup $actions): static
    {
        foreach (Arr::wrap($actions) as $action) {
            $action->table($this);

            if ($action instanceof ActionGroup) {
                /** @var array<string, BulkAction> $flatActions */
                $flatActions = $action->getFlatActions();

                $this->mergeCachedFlatBulkActions($flatActions);
            } elseif ($action instanceof BulkAction) {
                $this->cacheBulkAction($action);
            } else {
                throw new InvalidArgumentException('Table bulk actions must be an instance of ' . BulkAction::class . ' or ' . ActionGroup::class . '.');
            }

            $this->bulkActions[] = $action;
        }

        return $this;
    }

    /**
     * @param  array<BulkAction | ActionGroup>  $actions
     */
    public function groupedBulkActions(array $actions): static
    {
        $this->bulkActions([BulkActionGroup::make($actions)]);

        return $this;
    }

    protected function cacheBulkAction(BulkAction $action): void
    {
        $this->flatBulkActions[$action->getName()] = $action;
    }

    /**
     * @param  array<string, BulkAction>  $actions
     */
    protected function mergeCachedFlatBulkActions(array $actions): void
    {
        $this->flatBulkActions = [
            ...$this->flatBulkActions,
            ...$actions,
        ];
    }

    public function checkIfRecordIsSelectableUsing(?Closure $callback): static
    {
        $this->checkIfRecordIsSelectableUsing = $callback;

        return $this;
    }

    public function selectCurrentPageOnly(bool | Closure $condition = true): static
    {
        $this->selectsCurrentPageOnly = $condition;

        return $this;
    }

    /**
     * @return array<BulkAction | ActionGroup>
     */
    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }

    /**
     * @return array<string, BulkAction>
     */
    public function getFlatBulkActions(): array
    {
        return $this->flatBulkActions;
    }

    public function getBulkAction(string $name): ?BulkAction
    {
        return $this->getFlatBulkActions()[$name] ?? null;
    }

    /**
     * @param  Model | array<string, mixed>  $record
     */
    public function isRecordSelectable(Model | array $record): bool
    {
        return (bool) ($this->evaluate(
            $this->checkIfRecordIsSelectableUsing,
            namedInjections: [
                'record' => $record,
            ],
            typedInjections: ($record instanceof Model) ? [
                Model::class => $record,
                $record::class => $record,
            ] : [],
        ) ?? true);
    }

    public function getAllSelectableRecordsCount(): int
    {
        return $this->getLivewire()->getAllSelectableTableRecordsCount();
    }

    public function selectable(bool | Closure | null $condition = true): static
    {
        $this->isSelectable = $condition;

        return $this;
    }

    public function isSelectionEnabled(): bool
    {
        if (is_bool($isSelectable = $this->evaluate($this->isSelectable))) {
            return $isSelectable;
        }

        foreach ($this->getFlatBulkActions() as $bulkAction) {
            if ($bulkAction->isVisible()) {
                return true;
            }
        }

        return false;
    }

    public function selectsCurrentPageOnly(): bool
    {
        return $this->evaluate($this->selectsCurrentPageOnly) || (! $this->hasQuery());
    }

    public function checksIfRecordIsSelectable(): bool
    {
        return $this->checkIfRecordIsSelectableUsing !== null;
    }

    public function recordCheckboxPosition(RecordCheckboxPosition | Closure | null $position = null): static
    {
        $this->recordCheckboxPosition = $position;

        return $this;
    }

    public function getRecordCheckboxPosition(): RecordCheckboxPosition
    {
        return $this->evaluate($this->recordCheckboxPosition) ?? RecordCheckboxPosition::BeforeCells;
    }
}
