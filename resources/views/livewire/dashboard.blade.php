<?php

use App\Models\ListeningParty;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public $startTime;

    public function createListeningParty()
    {

    }

    public function with()
    {
        return [
            'listening_parties' => ListeningParty::all(),
        ];
    }
}; ?>

<div class="flex items-center justify-center min-h-screen bg-slate-50">
    <div class="w-full max-w-lg px-4">
        <form wire:submit="createListeningParty" class="space-y-6">
            <x-input wire:model="name" placeholder="Listening Party Name"/>
            <x-datetime-picker wire:model="startTime" placeholder="Listening Party Time"/>
            <x-button primary>Create Listening Party</x-button>
        </form>
    </div>
</div>
