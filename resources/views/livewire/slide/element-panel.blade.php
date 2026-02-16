<div class="p-4 space-y-3">
    @if(!empty($element))
        <h3 class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)]">Element-Eigenschaften</h3>

        <div class="space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] text-[var(--ui-muted)]">X</label>
                    <input type="number" wire:model.live="element.x" class="w-full px-2 py-1 text-xs rounded border border-[var(--ui-border)] bg-[var(--ui-muted-5)]" />
                </div>
                <div>
                    <label class="text-[10px] text-[var(--ui-muted)]">Y</label>
                    <input type="number" wire:model.live="element.y" class="w-full px-2 py-1 text-xs rounded border border-[var(--ui-border)] bg-[var(--ui-muted-5)]" />
                </div>
                <div>
                    <label class="text-[10px] text-[var(--ui-muted)]">Breite</label>
                    <input type="number" wire:model.live="element.width" class="w-full px-2 py-1 text-xs rounded border border-[var(--ui-border)] bg-[var(--ui-muted-5)]" />
                </div>
                <div>
                    <label class="text-[10px] text-[var(--ui-muted)]">Höhe</label>
                    <input type="number" wire:model.live="element.height" class="w-full px-2 py-1 text-xs rounded border border-[var(--ui-border)] bg-[var(--ui-muted-5)]" />
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-8">
            <p class="text-xs text-[var(--ui-muted)]">Kein Element ausgewählt</p>
        </div>
    @endif
</div>
