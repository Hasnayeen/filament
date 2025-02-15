<?php

namespace Filament\Tables\Columns;

use Filament\Forms\Components\Concerns\CanDisableOptions;
use Filament\Forms\Components\Concerns\CanSelectPlaceholder;
use Filament\Forms\Components\Concerns\HasEnum;
use Filament\Forms\Components\Concerns\HasExtraInputAttributes;
use Filament\Forms\Components\Concerns\HasOptions;
use Filament\Tables\Columns\Contracts\Editable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SelectColumn extends Column implements Editable
{
    use CanDisableOptions;
    use CanSelectPlaceholder;
    use Concerns\CanBeValidated {
        getRules as baseGetRules;
    }
    use Concerns\CanUpdateState;
    use HasEnum;
    use HasExtraInputAttributes;
    use HasOptions;

    /**
     * @var view-string
     */
    protected string $view = 'filament-tables::columns.select-column';

    protected function setUp(): void
    {
        parent::setUp();

        $this->disabledClick();

        $this->placeholder(__('filament-forms::components.select.placeholder'));
    }

    /**
     * @return array<array-key>
     */
    public function getRules(): array
    {
        return [
            ...$this->baseGetRules(),
            ...(filled($enum = $this->getEnum()) ?
                [new Enum($enum)] :
                Rule::in(array_keys($this->getEnabledOptions()))),
        ];
    }
}
