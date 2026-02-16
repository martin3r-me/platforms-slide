<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Einstellungen" icon="heroicon-o-cog-6-tooth" />
    </x-slot>

    <x-ui-page-container class="max-w-3xl mx-auto">
        <div class="mb-4">
            <a href="{{ route('slides.presentations.show', $presentation) }}" wire:navigate class="text-sm text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                Zurück zur Präsentation
            </a>
        </div>

        <div class="space-y-8">
            {{-- Allgemein --}}
            <x-ui-panel title="Allgemein">
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Name</label>
                        <x-ui-input-text name="name" wire:model="name" placeholder="Präsentationsname" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Beschreibung</label>
                        <x-ui-input-textarea name="description" wire:model="description" placeholder="Optionale Beschreibung..." rows="3" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Breite (px)</label>
                            <x-ui-input-text name="slideWidth" type="number" wire:model="slideWidth" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Höhe (px)</label>
                            <x-ui-input-text name="slideHeight" type="number" wire:model="slideHeight" />
                        </div>
                    </div>
                </div>
            </x-ui-panel>

            {{-- Theme --}}
            <x-ui-panel title="Theme">
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Primärfarbe</label>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model="colorPrimary" class="w-8 h-8 rounded border border-[var(--ui-border)] cursor-pointer" />
                                <x-ui-input-text name="colorPrimary" wire:model="colorPrimary" class="flex-1" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Akzentfarbe</label>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model="colorAccent" class="w-8 h-8 rounded border border-[var(--ui-border)] cursor-pointer" />
                                <x-ui-input-text name="colorAccent" wire:model="colorAccent" class="flex-1" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Textfarbe</label>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model="colorText" class="w-8 h-8 rounded border border-[var(--ui-border)] cursor-pointer" />
                                <x-ui-input-text name="colorText" wire:model="colorText" class="flex-1" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Hintergrundfarbe</label>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model="colorBackground" class="w-8 h-8 rounded border border-[var(--ui-border)] cursor-pointer" />
                                <x-ui-input-text name="colorBackground" wire:model="colorBackground" class="flex-1" />
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Überschrift-Font</label>
                            <x-ui-input-select wire:model="fontHeading">
                                <option value="Open Sans">Open Sans</option>
                                <option value="Source Sans 3">Source Sans 3</option>
                                <option value="Nunito Sans">Nunito Sans</option>
                                <option value="Inter">Inter</option>
                                <option value="Arial">Arial</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Helvetica">Helvetica</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Verdana">Verdana</option>
                            </x-ui-input-select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Text-Font</label>
                            <x-ui-input-select wire:model="fontBody">
                                <option value="Open Sans">Open Sans</option>
                                <option value="Source Sans 3">Source Sans 3</option>
                                <option value="Nunito Sans">Nunito Sans</option>
                                <option value="Inter">Inter</option>
                                <option value="Arial">Arial</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Helvetica">Helvetica</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Verdana">Verdana</option>
                            </x-ui-input-select>
                        </div>
                    </div>
                </div>
            </x-ui-panel>

            {{-- Veröffentlichung --}}
            <x-ui-panel title="Veröffentlichung">
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">Öffentlich zugänglich</div>
                            <div class="text-xs text-[var(--ui-muted)]">Präsentation kann ohne Login angesehen werden</div>
                        </div>
                        <button
                            wire:click="togglePublish"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $isPublished ? 'bg-[var(--ui-primary)]' : 'bg-[var(--ui-muted-5)] border border-[var(--ui-border)]' }}"
                        >
                            <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform {{ $isPublished ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </div>

                    @if($isPublished && $publicToken)
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)] mb-1">Öffentlicher Link</div>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 text-xs text-[var(--ui-secondary)] break-all">{{ route('slides.public.view', $publicToken) }}</code>
                                <button
                                    x-data
                                    x-on:click="navigator.clipboard.writeText('{{ route('slides.public.view', $publicToken) }}')"
                                    class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                                    title="Kopieren"
                                >
                                    @svg('heroicon-o-clipboard', 'w-4 h-4')
                                </button>
                            </div>
                            <button wire:click="regenerateToken" class="mt-2 text-xs text-[var(--ui-primary)] hover:underline">
                                Neuen Link generieren
                            </button>
                        </div>
                    @endif
                </div>
            </x-ui-panel>

            {{-- Speichern --}}
            <div class="flex justify-end">
                <x-ui-button variant="primary" wire:click="save">
                    Speichern
                </x-ui-button>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
