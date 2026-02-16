<div
    x-data="presentationMode({
        slides: @js($slides->map(fn($s) => [
            'id' => $s->id,
            'content' => $s->content,
            'background' => $s->background ?? ['type' => 'color', 'value' => '#ffffff'],
            'transition' => $s->transition,
        ])->values()),
        slideWidth: {{ $presentation->slide_width }},
        slideHeight: {{ $presentation->slide_height }},
    })"
    x-on:keydown.right.window="next()"
    x-on:keydown.left.window="prev()"
    x-on:keydown.space.window.prevent="next()"
    x-on:keydown.escape.window="exitFullscreen()"
    x-on:keydown.f.window="toggleFullscreen()"
    class="min-h-screen bg-black flex items-center justify-center"
    x-on:mousemove="showControls()"
>
    {{-- Slide Container --}}
    <div
        x-ref="slideContainer"
        class="relative overflow-hidden"
        :style="`width: ${displayWidth}px; height: ${displayHeight}px;`"
    >
        <div
            class="absolute inset-0 origin-top-left"
            :style="`width: ${slideWidth}px; height: ${slideHeight}px; transform: scale(${scale});` + currentBackgroundStyle"
        >
            <template x-for="element in currentSlide?.content?.elements || []" :key="element.id">
                <div
                    class="absolute"
                    :style="`left: ${element.x}px; top: ${element.y}px; width: ${element.width}px; height: ${element.height}px; z-index: ${element.zIndex || 1};`"
                >
                    <template x-if="element.type === 'text'">
                        <div
                            class="w-full h-full overflow-hidden"
                            :style="`font-family: '${element.style?.fontFamily || 'Open Sans'}', 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; font-weight: ${element.style?.fontWeight || '400'}; color: ${element.style?.color || '#333'}; text-align: ${element.style?.textAlign || 'left'}; line-height: ${element.style?.lineHeight || 1.4}; font-style: ${element.style?.fontStyle || 'normal'};` + (element.style?.letterSpacing ? `letter-spacing: ${element.style.letterSpacing}px;` : '')"
                            x-html="element.content?.html || ''"
                            x-effect="$nextTick(() => window.__slideAutoFit($el, element.style?.fontSize || 24, 18))"
                        ></div>
                    </template>
                    <template x-if="element.type === 'image' && element.content?.src">
                        <img
                            :src="element.content.src"
                            class="w-full h-full"
                            :style="`object-fit: ${element.style?.objectFit || 'cover'}; border-radius: ${element.style?.borderRadius || 0}px; opacity: ${element.style?.opacity || 1};`"
                        />
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- Controls --}}
    <div
        x-show="controlsVisible"
        x-transition.opacity
        class="fixed bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-4"
    >
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <div class="text-white/80 text-sm">
                {{ $presentation->name }}
            </div>
            <div class="text-white/60 text-sm">
                <span x-text="currentIndex + 1"></span> / <span x-text="slides.length"></span>
            </div>
            <div class="flex items-center gap-3">
                <button x-on:click.stop="prev()" class="text-white/80 hover:text-white p-1">
                    @svg('heroicon-o-chevron-left', 'w-5 h-5')
                </button>
                <button x-on:click.stop="next()" class="text-white/80 hover:text-white p-1">
                    @svg('heroicon-o-chevron-right', 'w-5 h-5')
                </button>
                <button x-on:click.stop="toggleFullscreen()" class="text-white/80 hover:text-white p-1">
                    @svg('heroicon-o-arrows-pointing-out', 'w-5 h-5')
                </button>
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
    Alpine.data('presentationMode', (config) => ({
        slides: config.slides || [],
        slideWidth: config.slideWidth,
        slideHeight: config.slideHeight,
        currentIndex: 0,
        scale: 1,
        displayWidth: 0,
        displayHeight: 0,
        controlsVisible: true,
        controlsTimeout: null,

        init() {
            this.calculateScale();
            window.addEventListener('resize', () => this.calculateScale());
            this.showControls();
        },

        calculateScale() {
            const w = window.innerWidth;
            const h = window.innerHeight;
            this.scale = Math.min(w / this.slideWidth, h / this.slideHeight);
            this.displayWidth = this.slideWidth * this.scale;
            this.displayHeight = this.slideHeight * this.scale;
        },

        get currentSlide() {
            return this.slides[this.currentIndex] || null;
        },

        get currentBackgroundStyle() {
            const bg = this.currentSlide?.background;
            if (!bg) return 'background-color: #ffffff;';
            if (bg.type === 'color') return `background-color: ${bg.value};`;
            if (bg.type === 'gradient') {
                const dir = (bg.value.direction || 'to-br').replace('to-', 'to ').replace('br', 'bottom right').replace('bl', 'bottom left');
                return `background: linear-gradient(${dir}, ${(bg.value.stops || []).join(', ')});`;
            }
            return 'background-color: #ffffff;';
        },

        next() { if (this.currentIndex < this.slides.length - 1) this.currentIndex++; },
        prev() { if (this.currentIndex > 0) this.currentIndex--; },

        toggleFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                document.documentElement.requestFullscreen().catch(() => {});
            }
        },

        exitFullscreen() {
            if (document.fullscreenElement) document.exitFullscreen();
        },

        showControls() {
            this.controlsVisible = true;
            clearTimeout(this.controlsTimeout);
            this.controlsTimeout = setTimeout(() => this.controlsVisible = false, 3000);
        },
    }));
});
</script>
