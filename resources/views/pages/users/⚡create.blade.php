<?php

use App\Services\CosmiaApi;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|email|max:255')]
    public string $email = '';

    #[Validate('required|in:super_admin,simple_user')]
    public string $role = 'simple_user';

    // Projets cochés (tableau d'ids en string). Vide = aucun projet.
    #[Validate('array')]
    public array $project_ids = [];

    public array $projects = [];

    public ?string $projectsError = null;

    public ?string $error = null;

    // Mémorise l'utilisateur déjà créé si une association échoue,
    // pour ne pas le recréer lors d'une nouvelle tentative.
    public ?string $createdUserId = null;

    public function mount(CosmiaApi $api): void
    {
        Gate::authorize('create-user');

        try {
            $response = $api->get('project');
            $projects = $response['data'] ?? $response;

            $this->projects = array_is_list($projects) ? $projects : [];
        } catch (\RuntimeException $e) {
            $this->projectsError = $e->getMessage();
        }
    }

    public function roleOptions(): array
    {
        return [
            'simple_user' => __('Utilisateur'),
            'super_admin' => __('Super admin'),
        ];
    }

    public function projectName(string $id): string
    {
        $project = collect($this->projects)->first(fn ($p) => (string) $p['id'] === $id);

        return $project['name'] ?? ('Projet #'.$id);
    }

    public function save(CosmiaApi $api): void
    {
        Gate::authorize('create-user');

        $this->validate();
        $this->error = null;

        // 1. Création de l'utilisateur (sauf si déjà créé lors d'une tentative précédente)
        if (! $this->createdUserId) {
            try {
                $response = $api->post('user', [
                    'name'  => trim($this->name),
                    'email' => trim($this->email),
                    'role'  => $this->role,
                ]);

                $this->createdUserId = (string) $this->extractUserId($response, $api);
            } catch (\RuntimeException $e) {
                $this->error = $e->getMessage();

                return;
            }
        }

        // 2. Association aux projets cochés (optionnelle)
        if (! empty($this->project_ids)) {
            if (! $this->createdUserId) {
                $this->error = __("Utilisateur créé, mais son identifiant est introuvable : l'association aux projets n'a pas pu être faite.");

                return;
            }

            $failures = [];

            foreach ($this->project_ids as $projectId) {
                try {
                    $api->post('user/userproject', [
                        'user_id'    => $this->createdUserId,
                        'project_id' => (string) $projectId,
                    ]);

                    // Succès : on le retire de la liste pour qu'un "Réessayer"
                    // ne traite que les projets restants.
                    $this->project_ids = array_values(
                        array_diff($this->project_ids, [(string) $projectId])
                    );
                } catch (\RuntimeException $e) {
                    $failures[] = $this->projectName((string) $projectId).' ('.$e->getMessage().')';
                }
            }

            if (! empty($failures)) {
                $this->error = __("L'utilisateur a été créé, mais l'association a échoué pour : :list", [
                    'list' => implode(', ', $failures),
                ]);

                return;
            }
        }

        session()->flash('success', __('Utilisateur ajouté avec succès.'));

        $this->redirectRoute('users.list', navigate: true);
    }

    /**
     * Récupère l'id du nouvel utilisateur depuis la réponse de l'API.
     * Si la réponse ne le contient pas, on le retrouve via l'email dans la liste.
     */
    private function extractUserId(array $response, CosmiaApi $api): mixed
    {
        $id = data_get($response, 'id')
            ?? data_get($response, 'user.id')
            ?? data_get($response, 'data.id');

        if ($id) {
            return $id;
        }

        try {
            $users = $api->get('user');
        } catch (\RuntimeException) {
            return null;
        }

        $found = collect($users)->firstWhere('email', trim($this->email));

        return data_get($found, 'id');
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

        <flux:heading size="xl" level="1">{{ __('Ajouter un utilisateur') }}</flux:heading>
        <flux:subheading>{{ __('Renseignez les informations et, si besoin, associez-le à un ou plusieurs projets.') }}</flux:subheading>
    </div>

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error" />
    @endif

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:input
                wire:model="name"
                :label="__('Nom complet')"
                type="text"
                required
                autofocus
                :disabled="(bool) $createdUserId"
                placeholder="Ex : Fabien Liserre"
            />

            <flux:input
                wire:model="email"
                :label="__('Adresse e-mail')"
                type="email"
                required
                :disabled="(bool) $createdUserId"
                placeholder="email@exemple.com"
            />

            <flux:select
                wire:model="role"
                :label="__('Rôle')"
                :disabled="(bool) $createdUserId"
            >
                @foreach ($this->roleOptions() as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:separator />

            @if ($projectsError)
                <flux:callout
                    variant="warning"
                    icon="exclamation-triangle"
                    :heading="__('Liste des projets indisponible')"
                    :text="$projectsError"
                />
            @elseif (empty($projects))
                <flux:text>{{ __('Aucun projet disponible pour le moment.') }}</flux:text>
            @else
                <flux:checkbox.group
                    wire:model="project_ids"
                    :label="__('Associer à des projets')"
                    :description="__('Optionnel : cochez un ou plusieurs projets. Vous pourrez aussi le faire plus tard.')"
                >
                    @if (count($projects) > 1)
                        <flux:checkbox.all :label="__('Tout sélectionner')" />
                    @endif

                    @foreach ($projects as $project)
                        <flux:checkbox
                            wire:key="project-{{ $project['id'] }}"
                            :value="(string) $project['id']"
                            :label="$project['name'] ?? ('Projet #'.$project['id'])"
                        />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <div class="flex items-center justify-end gap-3">
                <flux:button variant="ghost" :href="route('users.list')" wire:navigate>
                    {{ __('Annuler') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check">
                    <span wire:loading.remove wire:target="save">
                        {{ $createdUserId ? __("Réessayer l'association") : __('Créer l’utilisateur') }}
                    </span>
                    <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
