<?php

namespace Filament\Schema\Components\Concerns;

use Closure;
use Filament\Actions\Action;
use Filament\Schema\Components\Contracts\HasAffixActions;
use Filament\Schema\Components\Contracts\HasExtraItemActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait HasActions
{
    /**
     * @var array<Action> | null
     */
    protected ?array $cachedActions = null;

    /**
     * @var array<string, Action | Closure>
     */
    protected array $actions = [];

    protected Model | string | null $actionFormModel = null;

    protected ?Action $action = null;

    /**
     * @param  array<Action | Closure>  $actions
     */
    public function registerActions(array $actions): static
    {
        $this->actions = [
            ...$this->actions,
            ...$actions,
        ];

        return $this;
    }

    public function action(?Action $action): static
    {
        $this->action = $action;

        if ($action) {
            $this->registerActions([$action]);
        }

        return $this;
    }

    /**
     * @param  string | array<string> | null  $name
     */
    public function getAction(string | array | null $name = null): ?Action
    {
        $actions = $this->cacheActions();

        if (blank($name)) {
            return $this->action;
        }

        if (is_string($name) && str($name)->contains('.')) {
            $name = explode('.', $name);
        }

        if (is_array($name)) {
            $firstName = array_shift($name);
            $modalActionNames = $name;

            $name = $firstName;
        }

        $action = $actions[$name] ?? null;

        if (! $action) {
            return null;
        }

        foreach ($modalActionNames ?? [] as $modalActionName) {
            $action = $action->getMountableModalAction($modalActionName);

            if (! $action) {
                return null;
            }

            $name = $modalActionName;
        }

        if (! $action instanceof Action) {
            return null;
        }

        return $action;
    }

    /**
     * @return array<string, Action>
     */
    public function getActions(): array
    {
        return $this->cachedActions ??= $this->cacheActions();
    }

    /**
     * @return array<Action>
     */
    public function cacheActions(): array
    {
        $this->cachedActions = $this->getDecorationActions();

        if ($this instanceof HasAffixActions) {
            $this->cachedActions = [
                ...$this->cachedActions,
                ...$this->getPrefixActions(),
                ...$this->getSuffixActions(),
            ];
        }

        if ($this instanceof HasExtraItemActions) {
            $this->cachedActions = [
                ...$this->cachedActions,
                ...$this->getExtraItemActions(),
            ];
        }

        foreach ($this->actions as $registeredAction) {
            foreach (Arr::wrap($this->evaluate($registeredAction)) as $action) {
                $this->cachedActions[$action->getName()] = $this->prepareAction($action);
            }
        }

        return $this->cachedActions;
    }

    public function prepareAction(Action $action): Action
    {
        return $action->schemaComponent($this);
    }

    public function actionFormModel(Model | string | null $model): static
    {
        $this->actionFormModel = $model;

        return $this;
    }

    public function getActionFormModel(): Model | string | null
    {
        return $this->actionFormModel ?? $this->getRecord() ?? $this->getModel();
    }

    public function hasAction(string $name): bool
    {
        return array_key_exists($name, $this->getActions());
    }
}
