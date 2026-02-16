<div class="p-4">
    {{-- Category Filter --}}
    <div class="flex gap-1 mb-4">
        <button
            wire:click="selectCategory(null)"
            class="px-2 py-1 text-xs rounded-md transition-colors {{ !$selectedCategory ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)]' : 'border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)]' }}"
        >
            Alle
        </button>
        @foreach($categories as $key => $label)
            <button
                wire:click="selectCategory('{{ $key }}')"
                class="px-2 py-1 text-xs rounded-md transition-colors {{ $selectedCategory === $key ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)]' : 'border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)]' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Template Grid --}}
    <div class="grid grid-cols-2 gap-3">
        @foreach($layouts as $layout)
            <div
                class="rounded-lg border border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40 hover:shadow-sm transition-all cursor-pointer"
                x-on:click="$dispatch('select-template', { layoutKey: '{{ $layout['layout_key'] }}' })"
            >
                <div class="aspect-video rounded-t-lg overflow-hidden relative"
                    @if(isset($layout['background']) && $layout['background']['type'] === 'gradient')
                        @php
                            $dir = str_replace(['to-', 'br', 'bl'], ['to ', 'bottom right', 'bottom left'], $layout['background']['value']['direction'] ?? 'to-br');
                            $stops = implode(', ', $layout['background']['value']['stops'] ?? ['#667eea', '#764ba2']);
                        @endphp
                        style="background: linear-gradient({{ $dir }}, {{ $stops }});"
                    @else
                        style="background-color: #ffffff;"
                    @endif
                >
                    {{-- Mini preview of zones --}}
                    <div class="absolute inset-0 p-2">
                        @foreach(($layout['content']['elements'] ?? []) as $element)
                            <div
                                class="absolute border border-dashed border-gray-300/50 rounded"
                                style="
                                    left: {{ ($element['x'] / 1920) * 100 }}%;
                                    top: {{ ($element['y'] / 1080) * 100 }}%;
                                    width: {{ ($element['width'] / 1920) * 100 }}%;
                                    height: {{ ($element['height'] / 1080) * 100 }}%;
                                "
                            >
                                <span class="text-[6px] text-gray-400 p-0.5">{{ $element['zone'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="px-2 py-1.5">
                    <div class="text-xs font-medium text-[var(--ui-secondary)]">{{ $layout['name'] }}</div>
                    <div class="text-[10px] text-[var(--ui-muted)]">{{ $layout['description'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
