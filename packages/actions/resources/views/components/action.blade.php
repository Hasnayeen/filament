@props([
    'action',
    'dynamicComponent',
    'icon' => null,
])

@php
    $isDisabled = $action->isDisabled();
    $url = $action->getUrl();
    $shouldPostToUrl = $action->shouldPostToUrl();
@endphp

<x-dynamic-component
    :action="$shouldPostToUrl ? $url : null"
    :color="$action->getColor()"
    :component="$dynamicComponent"
    :disabled="$isDisabled"
    :form="$action->getFormToSubmit()"
    :form-id="$action->getFormId()"
    :href="($isDisabled || $shouldPostToUrl) ? null : $url"
    :icon="$icon ?? $action->getIcon()"
    :icon-size="$action->getIconSize()"
    :key-bindings="$action->getKeyBindings()"
    :label-sr-only="$action->isLabelHidden()"
    :method="$shouldPostToUrl ? 'post' : null"
    :tag="$url ? $shouldPostToUrl ? 'form' : 'a' : 'button'"
    :target="($url && $action->shouldOpenUrlInNewTab()) ? '_blank' : null"
    :tooltip="$action->getTooltip()"
    :type="$action->canSubmitForm() ? 'submit' : 'button'"
    :wire:click="$action->getLivewireClickHandler()"
    :wire:target="$action->getLivewireTarget()"
    :x-on:click="$action->getAlpineClickHandler()"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->merge($action->getExtraAttributes(), escape: false)
            ->class(['fi-ac-action'])
    "
>
    {{ $slot }}
</x-dynamic-component>
