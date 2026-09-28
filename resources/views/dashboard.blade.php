<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full max-w-7xl mx-auto flex-1 flex-col gap-4 rounded-xl">
        <livewire:pages::dashboard.projet />
        <livewire:pages::dashboard.tickets-chart />

        <livewire:pages::dashboard.partition-chart />

            <div class="grid gap-4 md:grid-cols-2">
                <livewire:pages::dashboard.donut-chart />

                <livewire:pages::dashboard.user-activity-chart />
            </div>

            <livewire:pages::dashboard.user-activity-table />

    </div>
</x-layouts::app>
