<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$presentation->name . ' - Slide ' . ($currentIndex + 1)" icon="heroicon-o-pencil-square" />
    </x-slot>

    <x-ui-page-container class="max-w-full px-4">
        {{-- Navigation Bar --}}
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('slides.presentations.show', $presentation) }}" wire:navigate class="px-3 py-1.5 text-xs rounded-md border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)] transition-colors flex items-center gap-1">
                    @svg('heroicon-o-arrow-left', 'w-3.5 h-3.5')
                    <span>Übersicht</span>
                </a>
                <span class="text-sm text-[var(--ui-muted)]">Slide {{ $currentIndex + 1 }} von {{ $allSlides->count() }}</span>
            </div>
            <div class="flex items-center gap-2">
                @if($prevSlide)
                    <button wire:click="navigateToSlide({{ $prevSlide->id }})" class="p-1.5 rounded-md border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)] transition-colors">
                        @svg('heroicon-o-chevron-left', 'w-4 h-4')
                    </button>
                @endif
                @if($nextSlide)
                    <button wire:click="navigateToSlide({{ $nextSlide->id }})" class="p-1.5 rounded-md border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)] transition-colors">
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
                    class="relative overflow-hidden rounded-lg shadow-lg border border-[var(--ui-border)]"
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
                                class="absolute cursor-pointer group"
                                :class="selectedId === element.id ? 'ring-2 ring-blue-500' : 'hover:ring-1 hover:ring-blue-300'"
                                :style="`left: ${element.x}px; top: ${element.y}px; width: ${element.width}px; height: ${element.height}px; z-index: ${element.zIndex || 1};`"
                                x-on:click.stop="selectElement(element.id)"
                                x-on:dblclick.stop="startEditing(element.id)"
                            >
                                {{-- Text Element --}}
                                <template x-if="element.type === 'text'">
                                    <div
                                        :contenteditable="editingId === element.id ? 'true' : 'false'"
                                        class="w-full h-full outline-none overflow-hidden"
                                        :style="`font-family: ${element.style?.fontFamily || 'Inter'}; font-size: ${element.style?.fontSize || 24}px; font-weight: ${element.style?.fontWeight || '400'}; color: ${element.style?.color || '#333'}; text-align: ${element.style?.textAlign || 'left'}; line-height: ${element.style?.lineHeight || 1.4};`"
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
                                            <div class="w-full h-full bg-[var(--ui-muted-5)] flex items-center justify-center text-[var(--ui-muted)]">
                                                <div class="text-center">
                                                    @svg('heroicon-o-photo', 'w-12 h-12 mx-auto mb-2')
                                                    <span class="text-sm">Bild einfügen</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Selection Handles --}}
                                <template x-if="selectedId === element.id && editingId !== element.id">
                                    <div>
                                        <div class="absolute -top-1 -left-1 w-2.5 h-2.5 bg-blue-500 rounded-full cursor-nw-resize" x-on:mousedown.stop="startResize(element.id, 'nw', $event)"></div>
                                        <div class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-blue-500 rounded-full cursor-ne-resize" x-on:mousedown.stop="startResize(element.id, 'ne', $event)"></div>
                                        <div class="absolute -bottom-1 -left-1 w-2.5 h-2.5 bg-blue-500 rounded-full cursor-sw-resize" x-on:mousedown.stop="startResize(element.id, 'sw', $event)"></div>
                                        <div class="absolute -bottom-1 -right-1 w-2.5 h-2.5 bg-blue-500 rounded-full cursor-se-resize" x-on:mousedown.stop="startResize(element.id, 'se', $event)"></div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Speaker Notes --}}
        <div class="mt-4">
            <div class="border border-[var(--ui-border)] rounded-lg">
                <div class="px-3 py-2 border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                    <h3 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wide">Speaker Notes</h3>
                </div>
                <textarea
                    wire:model.blur="notes"
                    wire:change="saveNotes"
                    class="w-full p-3 text-sm bg-transparent border-none outline-none resize-none focus:ring-0"
                    rows="3"
                    placeholder="Notizen für den Referenten..."
                ></textarea>
            </div>
        </div>

        {{-- Slide Filmstrip --}}
        <div class="mt-4 flex gap-2 overflow-x-auto pb-2">
            @foreach($allSlides as $idx => $s)
                <button
                    wire:click="navigateToSlide({{ $s->id }})"
                    class="flex-shrink-0 rounded-md border-2 transition-all {{ $s->id === $slide->id ? 'border-[var(--ui-primary)]' : 'border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40' }}"
                >
                    <div class="w-24 aspect-video rounded bg-white overflow-hidden relative"
                        @php
                            $sBg = $s->background ?? ['type' => 'color', 'value' => '#ffffff'];
                        @endphp
                        @if($sBg['type'] === 'color')
                            style="background-color: {{ $sBg['value'] }};"
                        @endif
                    >
                        <div class="absolute top-0.5 left-0.5 text-[8px] font-bold text-[var(--ui-muted)]">{{ $idx + 1 }}</div>
                    </div>
                </button>
            @endforeach
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Slide" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-4">
                {{-- Layout --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Layout</h3>
                    <div class="grid grid-cols-3 gap-1">
                        @php
                            $layouts = \Platform\Slides\Models\SlidesSlideTemplate::systemLayouts();
                        @endphp
                        @foreach($layouts as $layout)
                            <button
                                wire:click="changeLayout('{{ $layout['layout_key'] }}')"
                                class="p-1.5 text-[9px] rounded border transition-colors {{ $slide->layout_key === $layout['layout_key'] ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5 text-[var(--ui-primary)]' : 'border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40 text-[var(--ui-muted)]' }}"
                                title="{{ $layout['name'] }}"
                            >
                                {{ $layout['name'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Hintergrund --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Hintergrund</h3>
                    <div class="space-y-2">
                        <select
                            wire:model.live="backgroundType"
                            wire:change="saveBackground"
                            class="w-full px-2 py-1 text-xs rounded-md border border-[var(--ui-border)] bg-[var(--ui-muted-5)]"
                        >
                            <option value="color">Farbe</option>
                            <option value="gradient">Farbverlauf</option>
                        </select>

                        @if($backgroundType === 'color')
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model.live="backgroundValue" wire:change="saveBackground" class="w-8 h-8 rounded border border-[var(--ui-border)] cursor-pointer" />
                                <input type="text" wire:model.blur="backgroundValue" wire:change="saveBackground" class="flex-1 px-2 py-1 text-xs rounded-md border border-[var(--ui-border)] bg-[var(--ui-muted-5)]" />
                            </div>
                        @elseif($backgroundType === 'gradient')
                            <div class="space-y-2">
                                <select wire:model.live="gradientDirection" wire:change="saveBackground" class="w-full px-2 py-1 text-xs rounded-md border border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                    <option value="to-r">Nach rechts</option>
                                    <option value="to-l">Nach links</option>
                                    <option value="to-b">Nach unten</option>
                                    <option value="to-t">Nach oben</option>
                                    <option value="to-br">Nach rechts unten</option>
                                    <option value="to-bl">Nach links unten</option>
                                </select>
                                <div class="flex gap-2">
                                    <input type="color" wire:model.live="gradientStop1" wire:change="saveBackground" class="w-8 h-8 rounded border border-[var(--ui-border)] cursor-pointer" />
                                    <input type="color" wire:model.live="gradientStop2" wire:change="saveBackground" class="w-8 h-8 rounded border border-[var(--ui-border)] cursor-pointer" />
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Transition --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Übergang</h3>
                    <select wire:model.live="transition" wire:change="saveTransition" class="w-full px-2 py-1 text-xs rounded-md border border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
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
                    <h3 class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Medien</h3>
                    <div>
                        <input
                            type="file"
                            wire:model="mediaUpload"
                            accept="image/*"
                            class="w-full text-xs file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-[var(--ui-primary)] file:text-[var(--ui-on-primary)]"
                        />
                        @if($mediaUpload)
                            <button wire:click="uploadMedia" class="mt-1 w-full px-2 py-1 text-xs rounded bg-[var(--ui-primary)] text-[var(--ui-on-primary)]">
                                Hochladen
                            </button>
                        @endif
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
            }, 100);
        },
    }));
});
</script>
@endpush
