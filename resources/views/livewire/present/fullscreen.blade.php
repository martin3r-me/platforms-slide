<div
    x-data="presentationMode({
        slides: @js($slides->map(fn($s) => [
            'id' => $s->id,
            'content' => $s->content,
            'background' => $s->background ?? ['type' => 'color', 'value' => '#ffffff'],
            'transition' => $s->transition,
            'notes' => $s->notes,
        ])->values()),
        slideWidth: {{ $presentation->slide_width }},
        slideHeight: {{ $presentation->slide_height }},
    })"
    x-on:keydown.right.window="next()"
    x-on:keydown.left.window="prev()"
    x-on:keydown.space.window.prevent="next()"
    x-on:keydown.escape.window="exitFullscreen()"
    x-on:keydown.f.window="toggleFullscreen()"
    class="fixed inset-0 bg-black z-50 flex items-center justify-center cursor-none"
    x-on:mousemove="showControls()"
    x-on:click="next()"
>
    {{-- Slide Container --}}
    <div
        x-ref="slideContainer"
        class="relative overflow-hidden"
        :style="`width: ${displayWidth}px; height: ${displayHeight}px;`"
    >
        {{-- Current Slide --}}
        <div
            class="absolute inset-0 origin-top-left"
            :class="transitionClass"
            :key="'slide-' + currentIndex"
            :style="`width: ${slideWidth}px; height: ${slideHeight}px; transform: scale(${scale});` + currentBackgroundStyle"
        >
            <template x-for="element in currentSlide?.content?.elements || []" :key="element.id">
                <div
                    class="absolute"
                    :class="element.style?.animation ? 'el-anim-' + element.style.animation : ''"
                    :style="`left: ${element.x}px; top: ${element.y}px; width: ${element.width}px; height: ${element.height}px; z-index: ${element.zIndex || 1};` + (element.style?.animationDelay ? `animation-delay: ${element.style.animationDelay}s;` : '')"
                >
                    <template x-if="element.type === 'text'">
                        <div
                            class="w-full h-full overflow-hidden slide-text-render"
                            :style="`font-family: '${element.style?.fontFamily || 'Open Sans'}', 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; font-weight: ${element.style?.fontWeight || '400'}; color: ${element.style?.color || '#333'}; text-align: ${element.style?.textAlign || 'left'}; line-height: ${element.style?.lineHeight || 1.4}; font-style: ${element.style?.fontStyle || 'normal'};` + (element.style?.letterSpacing ? `letter-spacing: ${element.style.letterSpacing}px;` : '') + (element.style?.textShadow ? `text-shadow: ${element.style.textShadow};` : '') + (element.style?.textTransform ? `text-transform: ${element.style.textTransform};` : '') + (element.style?.backgroundColor ? `background-color: ${element.style.backgroundColor};` : '') + (element.style?.padding ? `padding: ${element.style.padding}px;` : '') + (element.style?.borderRadius ? `border-radius: ${element.style.borderRadius}px;` : '')"
                            x-html="element.content?.html || ''"
                            x-effect="$nextTick(() => window.__slideAutoFit($el, element.style?.fontSize || 24))"
                        ></div>
                    </template>
                    <template x-if="element.type === 'image' && element.content?.src">
                        <img
                            :src="element.content.src"
                            :alt="element.content?.alt || ''"
                            class="w-full h-full"
                            :style="`object-fit: ${element.style?.objectFit || 'cover'}; border-radius: ${element.style?.borderRadius || 0}px; opacity: ${element.style?.opacity || 1};`"
                        />
                    </template>
                </div>
            </template>

            {{-- Persistent Elements (Logo, Slide Numbers, Footer) --}}
            @include('slides::livewire.partials.persistent-elements', ['presentationSettings' => $presentation->settings])
        </div>
    </div>

    {{-- Controls (shown on hover) --}}
    <div
        x-show="controlsVisible"
        x-transition.opacity
        class="fixed bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-4 cursor-default"
        x-on:click.stop
    >
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            {{-- Slide Counter --}}
            <div class="text-white/80 text-sm">
                <span x-text="currentIndex + 1"></span> / <span x-text="slides.length"></span>
            </div>

            {{-- Progress Bar --}}
            <div class="flex-1 mx-8 h-1 bg-white/20 rounded-full overflow-hidden">
                <div class="h-full bg-white/80 rounded-full transition-all duration-300" :style="`width: ${((currentIndex + 1) / slides.length) * 100}%`"></div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-3">
                <button x-on:click.stop="prev()" class="text-white/80 hover:text-white transition-colors p-1">
                    @svg('heroicon-o-chevron-left', 'w-5 h-5')
                </button>
                <button x-on:click.stop="next()" class="text-white/80 hover:text-white transition-colors p-1">
                    @svg('heroicon-o-chevron-right', 'w-5 h-5')
                </button>
                <button x-on:click.stop="toggleFullscreen()" class="text-white/80 hover:text-white transition-colors p-1">
                    @svg('heroicon-o-arrows-pointing-out', 'w-5 h-5')
                </button>
                <a href="{{ route('slides.presentations.show', $presentation) }}" class="text-white/80 hover:text-white transition-colors p-1" title="Beenden">
                    @svg('heroicon-o-x-mark', 'w-5 h-5')
                </a>
            </div>
        </div>

        {{-- Timer --}}
        <div class="max-w-4xl mx-auto mt-2 text-center">
            <span class="text-white/50 text-xs" x-text="formatTime(elapsed)"></span>
        </div>
    </div>
</div>

@include('slides::livewire.partials.auto-fit-text')

@include('slides::livewire.partials.font-loader')

@include('slides::livewire.partials.animation-styles')

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
        elapsed: 0,
        timerInterval: null,
        channel: null,

        init() {
            this.calculateScale();
            window.addEventListener('resize', () => this.calculateScale());

            // Start timer
            this.timerInterval = setInterval(() => this.elapsed++, 1000);

            // Auto-hide controls
            this.showControls();

            // Request fullscreen
            this.$nextTick(() => this.toggleFullscreen());

            // BroadcastChannel for presenter sync
            try {
                this.channel = new BroadcastChannel('slides-present-' + '{{ $presentation->id }}');
                this.channel.onmessage = (e) => {
                    if (e.data.type === 'goto') {
                        this.currentIndex = Math.max(0, Math.min(e.data.index, this.slides.length - 1));
                    }
                };
            } catch (e) {}

            this.broadcastState();
        },

        destroy() {
            clearInterval(this.timerInterval);
            this.channel?.close();
        },

        calculateScale() {
            const w = window.innerWidth;
            const h = window.innerHeight;
            const scaleX = w / this.slideWidth;
            const scaleY = h / this.slideHeight;
            this.scale = Math.min(scaleX, scaleY);
            this.displayWidth = this.slideWidth * this.scale;
            this.displayHeight = this.slideHeight * this.scale;
        },

        get currentSlide() {
            return this.slides[this.currentIndex] || null;
        },

        get transitionClass() {
            const transition = this.currentSlide?.transition;
            if (!transition) return '';
            return 'slide-transition-' + transition;
        },

        get currentBackgroundStyle() {
            const bg = this.currentSlide?.background;
            if (!bg) return 'background-color: #ffffff;';
            if (bg.type === 'color') return `background-color: ${bg.value};`;
            if (bg.type === 'gradient') {
                const dir = (bg.value.direction || 'to-br').replace('to-', 'to ').replace('br', 'bottom right').replace('bl', 'bottom left').replace('tr', 'top right').replace('tl', 'top left');
                return `background: linear-gradient(${dir}, ${(bg.value.stops || []).join(', ')});`;
            }
            return 'background-color: #ffffff;';
        },

        next() {
            if (this.currentIndex < this.slides.length - 1) {
                this.currentIndex++;
                this.broadcastState();
            }
        },

        prev() {
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this.broadcastState();
            }
        },

        broadcastState() {
            this.channel?.postMessage({ type: 'state', index: this.currentIndex, elapsed: this.elapsed });
        },

        toggleFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                document.documentElement.requestFullscreen().catch(() => {});
            }
        },

        exitFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            }
        },

        showControls() {
            this.controlsVisible = true;
            clearTimeout(this.controlsTimeout);
            this.controlsTimeout = setTimeout(() => this.controlsVisible = false, 3000);
        },

        formatTime(seconds) {
            const m = Math.floor(seconds / 60).toString().padStart(2, '0');
            const s = (seconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        },
    }));
});
</script>
