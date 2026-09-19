<?php

use App\Models\Team;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;
    }
};

?>

<div class="space-y-6">
    <flux:card>
        <div class="space-y-3">
            <flux:heading size="lg">Playground</flux:heading>
            <flux:subheading>Working in the context of {{ $team->name }}.</flux:subheading>
        </div>
    </flux:card>

    <flux:card>
        <flux:button variant="primary">Hi everyone</flux:button>
    </flux:card>
</div>
