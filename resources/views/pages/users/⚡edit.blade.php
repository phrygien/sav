<?php

use App\Services\CosmiaApi;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $userId = '';

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|email|max:255')]
    public string $email = '';

    #[Validate('required|in:super_admin,simple_user')]
    public string $role = 'simple_user';

    /** [['user_project_id' => 1, 'project_id' => 3, 'name' => 'DIGIPARF'], ...] */
    public array $assignments = [];

    /** Tous les projets existants */
    public array $projects = [];

    public string $newProjectId = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(string $user, CosmiaApi $api): void
    {
        Gate::authorize('manage-access');

        $this->userId = $user;

        try {
            $found = collect($api->get('user'))->first(fn ($u) => (string) $u['id'] === $user);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        abort_if(! $found, 404);

        $this->name  = $found['name'];
        $this->email = $found['email'];
        $this->role  = $found['role'];

        try {
            $response = $api->get('project');
            $projects = $response['data'] ?? $response;
            $this->projects = array_is_list($projects) ? $projects : [];
        } catch (\RuntimeException) {
            // La liste des projets est seulement utile pour l'ajout
        }

        $this->loadAssignments($api);
    }

    private function loadAssignments(CosmiaApi $api): void
    {
        try {
            $response = $api->get('user/userproject/'.$this->userId);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->assignments = collect($response['result'] ?? [])
            ->map(fn ($item) => [
                'user_project_id' => data_get($item, 'user_project_id'),
                'project_id'      => data_get($item, 'project_id'),
                'name'            => data_get($item, 'project_name')
                    ?? 'Projet #'.data_get($item, 'project_id'),
            ])
            ->values()
            ->all();
    }

    public function roleOptions(): array
    {
        return [
            'simple_user' => __('Utilisateur'),
            'super_admin' => __('Super admin'),
        ];
    }

    /** Projets pas encore affectés à cet utilisateur */
    public function availableProjects(): array
    {
        $assignedIds = collect($this->assignments)->pluck('project_id')->map(fn ($id) => (string) $id);

        return collect($this->projects)
            ->reject(fn ($p) => $assignedIds->contains((string) $p['id']))
            ->values()
            ->all();
    }

    public function save(CosmiaApi $api): void
    {
        Gate::authorize('manage-access');

        $this->validate();
        $this->reset('error', 'success');

        try {
            $api->put('user/'.$this->userId, [
                'name'  => trim($this->name),
                'email' => trim($this->email),
                'role'  => $this->role,
            ]);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session()->flash('success', __('Utilisateur modifié avec succès.'));

        $this->redirectRoute('users.list', navigate: true);
    }

    public function removeProject(int|string $userProjectId, CosmiaApi $api): void
    {
        Gate::authorize('manage-access');

        $this->reset('error', 'success');

        // Sécurité : on n'accepte que les affectations de CET utilisateur
        $assignment = collect($this->assignments)
            ->first(fn ($a) => (string) $a['user_project_id'] === (string) $userProjectId);

        if (! $assignment) {
            $this->error = __('Affectation introuvable.');

            return;
        }

        try {
            $api->delete('user/userproject/'.$assignment['user_project_id']);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->loadAssignments($api);
        $this->success = __('Utilisateur retiré du projet « :name ».', ['name' => $assignment['name']]);
    }

    public function addProject(CosmiaApi $api): void
    {
        Gate::authorize('manage-access');

        $this->reset('error', 'success');

        $allowed = collect($this->availableProjects())->pluck('id')->map(fn ($id) => (string) $id);

        if (! $allowed->contains($this->newProjectId)) {
            $this->error = __('Sélectionnez un projet valide.');

            return;
        }

        try {
            $api->post('user/userproject', [
                'user_id'    => $this->userId,
                'project_id' => $this->newProjectId,
            ]);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->newProjectId = '';
        $this->loadAssignments($api);
        $this->success = __('Projet ajouté.');
    }
};
?>

<div class="mx-auto w-full max-w-2xl space-y-6">
    <div>
        <flux:button
            variant="ghost"
            size="sm"
            icon="arrow-left"
            :href="route('users.list')"
            wire:navigate
            class="-ml-2 mb-2"
        >
            {{ __('Retour aux utilisateurs') }}
        </flux:button>

        <flux:heading size="xl" level="1">{{ __("Modifier l'utilisateur") }}</flux:heading>
        <flux:subheading>{{ __('Modifiez les informations et gérez les projets affectés.') }}</flux:subheading>
    </div>

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error" />
    @endif

    @if ($success)
        <flux:callout variant="success" icon="check-circle" :heading="$success" />
    @endif

    {{-- Informations --}}
    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="name" :label="__('Nom complet')" type="text" required autofocus />

            <flux:input wire:model="email" :label="__('Adresse e-mail')" type="email" required />

            <flux:select wire:model="role" :label="__('Rôle')">
                @foreach ($this->roleOptions() as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center justify-end gap-3">
                <flux:button variant="ghost" :href="route('users.list')" wire:navigate>
                    {{ __('Annuler') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check">
                    <span wire:loading.remove wire:target="save">{{ __('Enregistrer') }}</span>
                    <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:card>

    {{-- Projets --}}
    <flux:card class="space-y-5">
        <div>
            <flux:heading size="lg">{{ __('Projets affectés') }}</flux:heading>
            <flux:subheading>
                {{ trans_choice(':count projet|:count projets', count($assignments)) }}
            </flux:subheading>
        </div>

        @if (empty($assignments))
            <flux:text class="italic">{{ __("Cet utilisateur n'est affecté à aucun projet.") }}</flux:text>
        @else
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($assignments as $assignment)
                    <li
                        wire:key="assignment-{{ $assignment['user_project_id'] }}"
                        class="flex items-center justify-between gap-3 py-3"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <flux:icon.folder class="size-5 shrink-0 text-blue-500" />
                            <flux:text class="truncate font-medium">{{ $assignment['name'] }}</flux:text>
                        </div>

                        <flux:button
                            size="sm"
                            variant="danger"
                            icon="x-mark"
                            wire:click="removeProject({{ $assignment['user_project_id'] }})"
                            wire:confirm="{{ __('Retirer cet utilisateur du projet :name ?', ['name' => $assignment['name']]) }}"
                        >
                            {{ __('Désaffecter') }}
                        </flux:button>
                    </li>
                @endforeach
            </ul>
        @endif

        @php $available = $this->availableProjects(); @endphp

        @if (! empty($available))
            <flux:separator />

            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <flux:select wire:model="newProjectId" :label="__('Ajouter à un projet')">
                        <flux:select.option value="">{{ __('Choisir un projet…') }}</flux:select.option>

                        @foreach ($available as $project)
                            <flux:select.option :value="(string) $project['id']">
                                {{ $project['name'] ?? ('Projet #'.$project['id']) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:button wire:click="addProject" icon="plus">
                    {{ __('Ajouter') }}
                </flux:button>
            </div>
        @endif
    </flux:card>
</div>
