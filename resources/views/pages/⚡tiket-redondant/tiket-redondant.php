<?php

use App\Services\CosmiaApi;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] class extends Component
{
    use WithPagination;

    public string $ticketStatus = 'all';
    public int $dateRange = 1;
    public int $perPage = 10;
    public ?string $error = null;

    public function updatedTicketStatus(): void
    {
        $this->resetPage();
    }

    public function updatedDateRange(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        $this->error = null;

        try {
            $response = app(CosmiaApi::class)->get('ticket/list/getRedudentTicket', [
                'ticket_status' => $this->ticketStatus,
                'date_range'    => $this->dateRange,
                'per_page'      => $this->perPage,
                'page'          => $this->getPage(),
            ]);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return new LengthAwarePaginator([], 0, $this->perPage);
        }

        return new LengthAwarePaginator(
            items: $response['data'] ?? [],
            total: $response['total_item'] ?? 0,
            perPage: $response['per_page'] ?? $this->perPage,
            currentPage: $response['current_page'] ?? 1,
            options: ['path' => request()->url()],
        );
    }
};
