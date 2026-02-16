<div
    x-data="presenterView({
        slides: @js($slides->map(fn($s) => [
            'id' => $s->id,
            'content' => $s->content,
            'background' => $s->background ?? ['type' => 'color', 'value' => '#ffffff'],
            'notes' => $s->notes,
        ])->values()),
        slideWidth: {{ $presentation->slide_width }},
        slideHeight: {{ $presentation->slide_height }},
        presentationId: {{ $presentation->id }},
    })"
    x-on:keydown.right.window="next()"
    x-on:keydown.left.window="prev()"
    x-on:keydown.space.window.prevent="next()"
    class="min-h-screen bg-gray-900 text-white p-4"
>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-semibold">{{ $presentation->name }} - Referentenansicht</h1>
        <div class="flex items-center gap-4">
            <div class="text-sm text-gray-400">
                Slide <span x-text="currentIndex + 1"></span> / <span x-text="slides.length"></span>
            </div>
            <div class="text-sm text-gray-400" x-text="formatTime(elapsed)"></div>
            <a href="{{ route('slides.presentations.show', $presentation) }}" class="text-sm text-gray-400 hover:text-white">
                Beenden
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4" style="height: calc(100vh - 80px);">
        {{-- Current Slide --}}
        <div class="flex flex-col gap-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Aktueller Slide</div>
            <div class="flex-1 bg-black rounded-lg overflow-hidden flex items-center justify-center">
                <div
                    class="relative origin-top-left"
                    :style="`width: ${slideWidth}px; height: ${slideHeight}px; transform: scale(${currentScale});` + getBackgroundStyle(currentSlide)"
                >
                    <template x-for="element in currentSlide?.content?.elements || []" :key="element.id">
                        <div
                            class="absolute"
                            :style="`left: ${element.x}px; top: ${element.y}px; width: ${element.width}px; height: ${element.height}px; z-index: ${element.zIndex || 1};`"
                        >
                            <template x-if="element.type === 'text'">
                                <div
                                    class="w-full h-full overflow-hidden"
                                    :style="`font-family: '${element.style?.fontFamily || 'Open Sans'}', 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; font-weight: ${element.style?.fontWeight || '400'}; color: ${element.style?.color || '#333'}; text-align: ${element.style?.textAlign || 'left'}; line-height: ${element.style?.lineHeight || 1.4};`"
                                    x-html="element.content?.html || ''"
                                    x-effect="$nextTick(() => window.__slideAutoFit($el, element.style?.fontSize || 24, 18))"
                                ></div>
                            </template>
                            <template x-if="element.type === 'image' && element.content?.src">
                                <img :src="element.content.src" class="w-full h-full" :style="`object-fit: ${element.style?.objectFit || 'cover'}; border-radius: ${element.style?.borderRadius || 0}px;`" />
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Notes --}}
            <div class="bg-gray-800 rounded-lg p-4 max-h-48 overflow-y-auto">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Speaker Notes</div>
                <div class="text-sm text-gray-200 whitespace-pre-wrap" x-text="currentSlide?.notes || 'Keine Notizen für diesen Slide.'"></div>
            </div>
        </div>

        {{-- Next Slide --}}
        <div class="flex flex-col gap-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Nächster Slide</div>
            <div class="flex-1 bg-black rounded-lg overflow-hidden flex items-center justify-center">
                <template x-if="nextSlide">
                    <div
                        class="relative origin-top-left"
                        :style="`width: ${slideWidth}px; height: ${slideHeight}px; transform: scale(${nextScale});` + getBackgroundStyle(nextSlide)"
                    >
                        <template x-for="element in nextSlide?.content?.elements || []" :key="element.id">
                            <div
                                class="absolute"
                                :style="`left: ${element.x}px; top: ${element.y}px; width: ${element.width}px; height: ${element.height}px; z-index: ${element.zIndex || 1};`"
                            >
                                <template x-if="element.type === 'text'">
                                    <div
                                        class="w-full h-full overflow-hidden"
                                        :style="`font-family: '${element.style?.fontFamily || 'Open Sans'}', 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; font-weight: ${element.style?.fontWeight || '400'}; color: ${element.style?.color || '#333'}; text-align: ${element.style?.textAlign || 'left'}; line-height: ${element.style?.lineHeight || 1.4};`"
                                        x-html="element.content?.html || ''"
                                        x-effect="$nextTick(() => window.__slideAutoFit($el, element.style?.fontSize || 24, 18))"
                                    ></div>
                                </template>
                                <template x-if="element.type === 'image' && element.content?.src">
                                    <img :src="element.content.src" class="w-full h-full" :style="`object-fit: ${element.style?.objectFit || 'cover'}; border-radius: ${element.style?.borderRadius || 0}px;`" />
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!nextSlide">
                    <div class="text-gray-600 text-sm">Letzter Slide</div>
                </template>
            </div>

            {{-- Controls --}}
            <div class="flex items-center justify-center gap-4 bg-gray-800 rounded-lg p-4">
                <button x-on:click="prev()" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 transition-colors flex items-center gap-2" :disabled="currentIndex === 0">
                    @svg('heroicon-o-chevron-left', 'w-4 h-4')
                    Zurück
                </button>
                <button x-on:click="next()" class="px-4 py-2 bg-blue-600 rounded hover:bg-blue-500 transition-colors flex items-center gap-2" :disabled="currentIndex === slides.length - 1">
                    Weiter
                    @svg('heroicon-o-chevron-right', 'w-4 h-4')
                </button>
            </div>

            {{-- Progress --}}
            <div class="h-1 bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-blue-500 rounded-full transition-all" :style="`width: ${((currentIndex + 1) / slides.length) * 100}%`"></div>
            </div>
        </div>
    </div>
</div>

@include('slides::livewire.partials.auto-fit-text')

@once
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Source+Sans+3:ital,wght@0,200..900;1,200..900&family=Nunito+Sans:ital,opsz,wght@0,6..12,200..1000;1,6..12,200..1000&display=swap" rel="stylesheet">
@endonce

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('presenterView', (config) => ({
        slides: config.slides || [],
        slideWidth: config.slideWidth,
        slideHeight: config.slideHeight,
        currentIndex: 0,
        currentScale: 0.4,
        nextScale: 0.35,
        elapsed: 0,
        timerInterval: null,
        channel: null,

        init() {
            this.timerInterval = setInterval(() => this.elapsed++, 1000);

            // BroadcastChannel for syncing with fullscreen
            try {
                this.channel = new BroadcastChannel('slides-present-' + config.presentationId);
                this.channel.onmessage = (e) => {
                    if (e.data.type === 'state') {
                        this.currentIndex = e.data.index;
                        this.elapsed = e.data.elapsed;
                    }
                };
            } catch (e) {}

            this.calculateScales();
            window.addEventListener('resize', () => this.calculateScales());
        },

        destroy() {
            clearInterval(this.timerInterval);
            this.channel?.close();
        },

        calculateScales() {
            const containerHeight = (window.innerHeight - 200) * 0.6;
            const containerWidth = (window.innerWidth - 40) / 2;
            this.currentScale = Math.min(containerWidth / this.slideWidth, containerHeight / this.slideHeight, 0.5);
            this.nextScale = this.currentScale * 0.85;
        },

        get currentSlide() {
            return this.slides[this.currentIndex] || null;
        },

        get nextSlide() {
            return this.slides[this.currentIndex + 1] || null;
        },

        getBackgroundStyle(slide) {
            const bg = slide?.background;
            if (!bg) return 'background-color: #ffffff;';
            if (bg.type === 'color') return `background-color: ${bg.value};`;
            if (bg.type === 'gradient') {
                const dir = (bg.value.direction || 'to-br').replace('to-', 'to ').replace('br', 'bottom right').replace('bl', 'bottom left');
                return `background: linear-gradient(${dir}, ${(bg.value.stops || []).join(', ')});`;
            }
            return 'background-color: #ffffff;';
        },

        next() {
            if (this.currentIndex < this.slides.length - 1) {
                this.currentIndex++;
                this.channel?.postMessage({ type: 'goto', index: this.currentIndex });
            }
        },

        prev() {
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this.channel?.postMessage({ type: 'goto', index: this.currentIndex });
            }
        },

        formatTime(seconds) {
            const m = Math.floor(seconds / 60).toString().padStart(2, '0');
            const s = (seconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        },
    }));
});
</script>
