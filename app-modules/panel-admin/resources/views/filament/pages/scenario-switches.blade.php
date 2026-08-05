<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($this->getSwitchRows() as $row)
            @php
                $switch = $row['switch'];
                $enabled = $row['enabled'];
            @endphp

            <x-filament::section>
                <x-slot name="heading">
                    {{ $switch->getLabel() }}
                </x-slot>

                <x-slot name="afterHeader">
                    <x-filament::badge :color="$enabled ? 'danger' : 'gray'">
                        {{ $enabled ? __('panel-admin::venue.scenario_switches.state_on') : __('panel-admin::venue.scenario_switches.state_off') }}
                    </x-filament::badge>
                </x-slot>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $switch->getDescription() }}
                </p>

                <div class="mt-4">
                    {{ ($this->toggleAction)(['switch' => $switch->value, 'enable' => !$enabled]) }}
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
