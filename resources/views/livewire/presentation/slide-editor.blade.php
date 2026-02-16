<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$presentation->name . ' - Slide ' . ($currentIndex + 1)" icon="heroicon-o-pencil-square" />
    </x-slot>

    <x-ui-page-container class="max-w-full px-4">
        {{-- Navigation Bar --}}
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('slides.presentations.show', $presentation) }}" wire:navigate class="px-3 py-1.5 text-xs rounded-lg border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)] transition-colors flex items-center gap-1.5">
                    @svg('heroicon-o-arrow-left', 'w-3.5 h-3.5')
                    <span>Übersicht</span>
                </a>
                <div class="flex items-center gap-1.5 text-sm">
                    <span class="font-medium text-[var(--ui-secondary)]">Slide {{ $currentIndex + 1 }}</span>
                    <span class="text-[var(--ui-muted)]">von {{ $allSlides->count() }}</span>
                </div>
                @if($slide->layout_key)
                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-[var(--ui-primary)]/10 text-[var(--ui-primary)]">
                        {{ $slide->layout_key }}
                    </span>
                @endif
            </div>
            <div class="flex items-center gap-1.5">
                {{-- Save indicator --}}
                <div x-data="{ saved: false }" x-on:content-saved.window="saved = true; setTimeout(() => saved = false, 2000)">
                    <span x-show="saved" x-transition.opacity class="text-[10px] text-[var(--ui-success)] flex items-center gap-1">
                        @svg('heroicon-s-check-circle', 'w-3.5 h-3.5')
                        Gespeichert
                    </span>
                </div>
                @if($prevSlide)
                    <button wire:click="navigateToSlide({{ $prevSlide->id }})" class="p-2 rounded-lg border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)] transition-colors" title="Vorheriger Slide">
                        @svg('heroicon-o-chevron-left', 'w-4 h-4')
                    </button>
                @endif
                @if($nextSlide)
                    <button wire:click="navigateToSlide({{ $nextSlide->id }})" class="p-2 rounded-lg border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)] transition-colors" title="Nächster Slide">
                        @svg('heroicon-o-chevron-right', 'w-4 h-4')
                    </button>
                @endif
            </div>
        </div>

        {{-- Editor Canvas --}}
        <div
            wire:ignore
            x-data="slideEditor({
                elements: @js($slide->content['elements'] ?? []),
                slideWidth: {{ $presentation->slide_width }},
                slideHeight: {{ $presentation->slide_height }},
                background: @js($slide->background ?? ['type' => 'color', 'value' => '#ffffff']),
            })"
            class="relative"
        >
            {{-- Canvas Container --}}
            <div class="flex justify-center">
                <div
                    x-ref="canvasWrapper"
                    class="relative overflow-hidden rounded-xl shadow-xl border border-[var(--ui-border)]/60"
                    :style="`width: ${canvasDisplayWidth}px; height: ${canvasDisplayHeight}px;`"
                >
                    {{-- Scaled Canvas --}}
                    <div
                        x-ref="canvas"
                        class="absolute top-0 left-0 origin-top-left"
                        :style="`width: ${slideWidth}px; height: ${slideHeight}px; transform: scale(${scale});` + backgroundStyle"
                        x-on:click="deselectAll($event)"
                    >
                        {{-- Elements --}}
                        <template x-for="(element, index) in elements" :key="element.id">
                            <div
                                :data-element-id="element.id"
                                class="absolute cursor-pointer"
                                :class="{
                                    'ring-2 ring-blue-500 ring-offset-1': selectedId === element.id,
                                    'hover:ring-1 hover:ring-blue-300/50': selectedId !== element.id
                                }"
                                :style="`left: ${element.x}px; top: ${element.y}px; width: ${element.width}px; height: ${element.height}px; z-index: ${element.zIndex || 1};`"
                                x-on:click.stop="selectElement(element.id)"
                                x-on:dblclick.stop="startEditing(element.id)"
                            >
                                {{-- Zone label --}}
                                <div
                                    x-show="selectedId === element.id && element.zone"
                                    class="absolute -top-6 left-0 px-2 py-0.5 text-[10px] font-mono bg-blue-500 text-white rounded-t-md whitespace-nowrap"
                                    x-text="element.zone"
                                ></div>

                                {{-- Text Element --}}
                                <template x-if="element.type === 'text'">
                                    <div
                                        :contenteditable="editingId === element.id ? 'true' : 'false'"
                                        class="w-full h-full outline-none overflow-hidden transition-shadow"
                                        :class="editingId === element.id ? 'ring-1 ring-blue-400/30 bg-white/5' : ''"
                                        :style="`font-family: ${element.style?.fontFamily || 'Inter'}; font-size: ${element.style?.fontSize || 24}px; font-weight: ${element.style?.fontWeight || '400'}; color: ${element.style?.color || '#333'}; text-align: ${element.style?.textAlign || 'left'}; line-height: ${element.style?.lineHeight || 1.4}; font-style: ${element.style?.fontStyle || 'normal'};`"
                                        x-html="element.content?.html || ''"
                                        x-on:blur="updateElementContent(element.id, $event.target.innerHTML)"
                                        x-on:input="debounceSave()"
                                    ></div>
                                </template>

                                {{-- Image Element --}}
                                <template x-if="element.type === 'image'">
                                    <div class="w-full h-full overflow-hidden" :style="`border-radius: ${element.style?.borderRadius || 0}px;`">
                                        <template x-if="element.content?.src">
                                            <img
                                                :src="element.content.src"
                                                :alt="element.content?.alt || ''"
                                                class="w-full h-full"
                                                :style="`object-fit: ${element.style?.objectFit || 'cover'}; opacity: ${element.style?.opacity || 1};`"
                                            />
                                        </template>
                                        <template x-if="!element.content?.src">
                                            <div class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-400 border-2 border-dashed border-gray-300 rounded">
                                                <div class="text-center">
                                                    @svg('heroicon-o-photo', 'w-12 h-12 mx-auto mb-2')
                                                    <span class="text-sm font-medium">Bild einfügen</span>
                                                    <span class="text-xs block mt-1">Wähle dieses Element und lade ein Bild hoch</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Selection Handles --}}
                                <template x-if="selectedId === element.id && editingId !== element.id">
                                    <div>
                                        <div class="absolute -top-1.5 -left-1.5 w-3 h-3 bg-blue-500 border-2 border-white rounded-full cursor-nw-resize shadow-sm" x-on:mousedown.stop="startResize(element.id, 'nw', $event)"></div>
                                        <div class="absolute -top-1.5 -right-1.5 w-3 h-3 bg-blue-500 border-2 border-white rounded-full cursor-ne-resize shadow-sm" x-on:mousedown.stop="startResize(element.id, 'ne', $event)"></div>
                                        <div class="absolute -bottom-1.5 -left-1.5 w-3 h-3 bg-blue-500 border-2 border-white rounded-full cursor-sw-resize shadow-sm" x-on:mousedown.stop="startResize(element.id, 'sw', $event)"></div>
                                        <div class="absolute -bottom-1.5 -right-1.5 w-3 h-3 bg-blue-500 border-2 border-white rounded-full cursor-se-resize shadow-sm" x-on:mousedown.stop="startResize(element.id, 'se', $event)"></div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Speaker Notes --}}
        <div class="mt-5">
            <div class="border border-[var(--ui-border)]/60 rounded-xl overflow-hidden">
                <div class="px-4 py-2 border-b border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]/50 flex items-center gap-2">
                    @svg('heroicon-o-chat-bubble-bottom-center-text', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                    <h3 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Speaker Notes</h3>
                </div>
                <textarea
                    wire:model.blur="notes"
                    wire:change="saveNotes"
                    class="w-full p-4 text-sm bg-transparent border-none outline-none resize-none focus:ring-0"
                    rows="3"
                    placeholder="Notizen für den Referenten..."
                ></textarea>
            </div>
        </div>

        {{-- Slide Filmstrip --}}
        <div class="mt-5 flex gap-2.5 overflow-x-auto pb-3 scrollbar-thin">
            @foreach($allSlides as $idx => $s)
                <button
                    wire:click="navigateToSlide({{ $s->id }})"
                    class="flex-shrink-0 rounded-lg border-2 transition-all duration-200 hover:shadow-md {{ $s->id === $slide->id ? 'border-[var(--ui-primary)] shadow-md ring-1 ring-[var(--ui-primary)]/20' : 'border-[var(--ui-border)]/30 hover:border-[var(--ui-primary)]/40' }}"
                >
                    <div class="w-28 aspect-video rounded-md bg-white overflow-hidden relative"
                        @php
                            $sBg = $s->background ?? ['type' => 'color', 'value' => '#ffffff'];
                        @endphp
                        @if(($sBg['type'] ?? 'color') === 'color')
                            style="background-color: {{ $sBg['value'] ?? '#ffffff' }};"
                        @elseif(($sBg['type'] ?? '') === 'gradient')
                            @php
                                $fDir = str_replace(['to-', 'br', 'bl', 'tr', 'tl', 'r', 'l', 'b', 't'], ['to ', 'bottom right', 'bottom left', 'top right', 'top left', 'right', 'left', 'bottom', 'top'], $sBg['value']['direction'] ?? 'to-br');
                                $fStops = implode(', ', $sBg['value']['stops'] ?? ['#667eea', '#764ba2']);
                            @endphp
                            style="background: linear-gradient({{ $fDir }}, {{ $fStops }});"
                        @endif
                    >
                        {{-- Mini content preview --}}
                        @if($s->content && isset($s->content['elements']))
                            <div class="absolute inset-0 overflow-hidden" style="transform: scale(0.058); transform-origin: top left; width: 1920px; height: 1080px;">
                                @foreach($s->content['elements'] as $element)
                                    @if($element['type'] === 'text')
                                        <div style="position: absolute; left: {{ $element['x'] }}px; top: {{ $element['y'] }}px; width: {{ $element['width'] }}px; height: {{ $element['height'] }}px; font-size: {{ $element['style']['fontSize'] ?? 24 }}px; font-weight: {{ $element['style']['fontWeight'] ?? '400' }}; color: {{ $element['style']['color'] ?? '#333' }}; text-align: {{ $element['style']['textAlign'] ?? 'left' }}; overflow: hidden;">
                                            {!! $element['content']['html'] ?? '' !!}
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        <div class="absolute top-0.5 left-1 text-[8px] font-bold {{ $s->id === $slide->id ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]' }}">{{ $idx + 1 }}</div>
                    </div>
                </button>
            @endforeach
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Slide" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-5">
                {{-- Platzhalter/Zones --}}
                @php
                    $placeholders = $slide->getPlaceholders();
                @endphp
                @if(count($placeholders) > 0)
                    <div>
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5 flex items-center gap-1.5">
                            @svg('heroicon-o-cursor-arrow-ripple', 'w-3.5 h-3.5')
                            Platzhalter
                        </h3>
                        <div class="space-y-1.5">
                            @foreach($placeholders as $ph)
                                <div class="flex items-center justify-between py-1.5 px-2.5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/30 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        @if($ph['type'] === 'text')
                                            @svg('heroicon-o-bars-3-bottom-left', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                                        @else
                                            @svg('heroicon-o-photo', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                                        @endif
                                        <span class="text-xs font-mono font-medium text-[var(--ui-secondary)]">{{ $ph['zone'] }}</span>
                                    </div>
                                    <span class="text-[10px] text-[var(--ui-muted)] max-w-[120px] truncate">
                                        {{ Str::limit($ph['current_value'], 25) ?: '(leer)' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-[10px] text-[var(--ui-muted)] mt-2">
                            Platzhalter können per LLM-Tool befüllt werden: <code class="bg-[var(--ui-muted-5)] px-1 rounded">slides.slide.content.PUT</code>
                        </p>
                    </div>
                @endif

                {{-- Layout --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5 flex items-center gap-1.5">
                        @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5')
                        Layout
                    </h3>
                    <div class="grid grid-cols-3 gap-1.5">
                        @php
                            $layouts = \Platform\Slides\Models\SlidesSlideTemplate::systemLayouts();
                        @endphp
                        @foreach($layouts as $layout)
                            <button
                                wire:click="changeLayout('{{ $layout['layout_key'] }}')"
                                class="p-1.5 text-[9px] rounded-lg border transition-all duration-150 {{ $slide->layout_key === $layout['layout_key'] ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold shadow-sm' : 'border-[var(--ui-border)]/30 hover:border-[var(--ui-primary)]/40 hover:bg-[var(--ui-primary)]/5 text-[var(--ui-muted)]' }}"
                                title="{{ $layout['description'] }}"
                            >
                                {{ $layout['name'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Hintergrund --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5 flex items-center gap-1.5">
                        @svg('heroicon-o-paint-brush', 'w-3.5 h-3.5')
                        Hintergrund
                    </h3>
                    <div class="space-y-2">
                        <select
                            wire:model.live="backgroundType"
                            wire:change="saveBackground"
                            class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]"
                        >
                            <option value="color">Farbe</option>
                            <option value="gradient">Farbverlauf</option>
                        </select>

                        @if($backgroundType === 'color')
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model.live="backgroundValue" wire:change="saveBackground" class="w-9 h-9 rounded-lg border border-[var(--ui-border)] cursor-pointer" />
                                <input type="text" wire:model.blur="backgroundValue" wire:change="saveBackground" class="flex-1 px-2.5 py-1.5 text-xs rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] font-mono" />
                            </div>
                        @elseif($backgroundType === 'gradient')
                            <div class="space-y-2">
                                <select wire:model.live="gradientDirection" wire:change="saveBackground" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                    <option value="to-r">Nach rechts</option>
                                    <option value="to-l">Nach links</option>
                                    <option value="to-b">Nach unten</option>
                                    <option value="to-t">Nach oben</option>
                                    <option value="to-br">Nach rechts unten</option>
                                    <option value="to-bl">Nach links unten</option>
                                </select>
                                <div class="flex gap-2">
                                    <input type="color" wire:model.live="gradientStop1" wire:change="saveBackground" class="w-9 h-9 rounded-lg border border-[var(--ui-border)] cursor-pointer" />
                                    <input type="color" wire:model.live="gradientStop2" wire:change="saveBackground" class="w-9 h-9 rounded-lg border border-[var(--ui-border)] cursor-pointer" />
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Transition --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5 flex items-center gap-1.5">
                        @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                        Übergang
                    </h3>
                    <select wire:model.live="transition" wire:change="saveTransition" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <option value="">Kein Übergang</option>
                        <option value="fade">Einblenden</option>
                        <option value="slide-left">Nach links schieben</option>
                        <option value="slide-right">Nach rechts schieben</option>
                        <option value="slide-up">Nach oben schieben</option>
                        <option value="zoom">Zoom</option>
                    </select>
                </div>

                {{-- Bild hochladen --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5 flex items-center gap-1.5">
                        @svg('heroicon-o-photo', 'w-3.5 h-3.5')
                        Medien
                    </h3>
                    <div class="space-y-2">
                        <input
                            type="file"
                            wire:model="mediaUpload"
                            accept="image/*"
                            class="w-full text-xs file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-[var(--ui-primary)] file:text-[var(--ui-on-primary)] file:cursor-pointer"
                        />
                        @if($mediaUpload)
                            <button wire:click="uploadMedia" class="w-full px-3 py-1.5 text-xs rounded-lg bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90 transition-opacity flex items-center justify-center gap-1.5">
                                @svg('heroicon-o-arrow-up-tray', 'w-3.5 h-3.5')
                                Hochladen
                            </button>
                        @endif
                        <p class="text-[10px] text-[var(--ui-muted)]">Wähle zuerst ein Bild-Element im Canvas, dann lade ein Bild hoch.</p>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('slideEditor', (config) => ({
        elements: config.elements || [],
        slideWidth: config.slideWidth,
        slideHeight: config.slideHeight,
        background: config.background,
        scale: 1,
        canvasDisplayWidth: 0,
        canvasDisplayHeight: 0,
        selectedId: null,
        editingId: null,
        resizing: null,
        saveTimeout: null,

        init() {
            this.calculateScale();
            window.addEventListener('resize', () => this.calculateScale());

            // Listen for media uploads
            Livewire.on('mediaUploaded', (data) => {
                if (this.selectedId) {
                    const el = this.elements.find(e => e.id === this.selectedId);
                    if (el && el.type === 'image') {
                        el.content = { ...el.content, src: data[0].src, mediaId: data[0].id };
                        this.autoSave();
                    }
                }
            });

            // Mouse move/up for resizing
            document.addEventListener('mousemove', (e) => this.onMouseMove(e));
            document.addEventListener('mouseup', () => this.onMouseUp());

            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.stopEditing();
                    this.selectedId = null;
                }
            });
        },

        calculateScale() {
            const wrapper = this.$refs.canvasWrapper?.parentElement;
            if (!wrapper) return;
            const maxWidth = wrapper.clientWidth - 32;
            const maxHeight = window.innerHeight * 0.6;
            const scaleX = maxWidth / this.slideWidth;
            const scaleY = maxHeight / this.slideHeight;
            this.scale = Math.min(scaleX, scaleY, 1);
            this.canvasDisplayWidth = this.slideWidth * this.scale;
            this.canvasDisplayHeight = this.slideHeight * this.scale;
        },

        get backgroundStyle() {
            const bg = this.background;
            if (!bg) return 'background-color: #ffffff;';
            if (bg.type === 'color') return `background-color: ${bg.value};`;
            if (bg.type === 'gradient') {
                const dir = (bg.value.direction || 'to-br')
                    .replace('to-', 'to ')
                    .replace('br', 'bottom right')
                    .replace('bl', 'bottom left')
                    .replace('tr', 'top right')
                    .replace('tl', 'top left')
                    .replace(/\br\b/, 'right')
                    .replace(/\bl\b/, 'left')
                    .replace(/\bb\b/, 'bottom')
                    .replace(/\bt\b/, 'top');
                return `background: linear-gradient(${dir}, ${(bg.value.stops || []).join(', ')});`;
            }
            return 'background-color: #ffffff;';
        },

        selectElement(id) {
            if (this.editingId && this.editingId !== id) {
                this.stopEditing();
            }
            this.selectedId = id;
        },

        deselectAll(event) {
            if (event.target === this.$refs.canvas) {
                this.stopEditing();
                this.selectedId = null;
            }
        },

        startEditing(id) {
            const el = this.elements.find(e => e.id === id);
            if (el && el.type === 'text' && !el.locked) {
                this.editingId = id;
                this.selectedId = id;
            }
        },

        stopEditing() {
            if (this.editingId) {
                this.editingId = null;
                this.autoSave();
            }
        },

        updateElementContent(id, html) {
            const el = this.elements.find(e => e.id === id);
            if (el) {
                el.content = {
                    ...el.content,
                    html: html,
                    plainText: html.replace(/<[^>]*>/g, ''),
                };
            }
        },

        startResize(id, handle, event) {
            const el = this.elements.find(e => e.id === id);
            if (!el || el.locked) return;
            this.resizing = { id, handle, startX: event.clientX, startY: event.clientY, origX: el.x, origY: el.y, origW: el.width, origH: el.height };
        },

        onMouseMove(event) {
            if (!this.resizing) return;
            const r = this.resizing;
            const dx = (event.clientX - r.startX) / this.scale;
            const dy = (event.clientY - r.startY) / this.scale;
            const el = this.elements.find(e => e.id === r.id);
            if (!el) return;

            if (r.handle.includes('e')) { el.width = Math.max(50, r.origW + dx); }
            if (r.handle.includes('w')) { el.x = r.origX + dx; el.width = Math.max(50, r.origW - dx); }
            if (r.handle.includes('s')) { el.height = Math.max(30, r.origH + dy); }
            if (r.handle.includes('n')) { el.y = r.origY + dy; el.height = Math.max(30, r.origH - dy); }
        },

        onMouseUp() {
            if (this.resizing) {
                this.resizing = null;
                this.autoSave();
            }
        },

        debounceSave() {
            clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => this.autoSave(), 1500);
        },

        autoSave() {
            clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => {
                const elementsData = JSON.parse(JSON.stringify(this.elements));
                this.$wire.saveContent(elementsData);
                window.dispatchEvent(new CustomEvent('content-saved'));
            }, 100);
        },
    }));
});
</script>
@endpush
