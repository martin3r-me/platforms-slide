{{-- Auto-Fit / Shrink-to-Fit for text elements --}}
<script>
(function() {
    if (window.__slideAutoFit) return;

    /**
     * Shrink-to-fit: reduces font-size until text fits within its container.
     *
     * @param {HTMLElement} el       - The text container element
     * @param {number}      basePx   - The original / desired font-size in px
     * @param {number}      minPx    - The minimum font-size in px (default 18)
     */
    window.__slideAutoFit = function(el, basePx, minPx) {
        if (!el || !el.parentElement) return;

        minPx = minPx || 18;
        basePx = basePx || 24;

        // Start with the original font-size
        var size = basePx;
        el.style.fontSize = size + 'px';

        // Allow the browser to lay out the content, then check overflow
        // We compare scrollHeight to clientHeight to detect vertical overflow
        var step = 2;
        var maxIterations = Math.ceil((basePx - minPx) / step) + 1;
        var iterations = 0;

        while (el.scrollHeight > el.clientHeight + 1 && size > minPx && iterations < maxIterations) {
            size = Math.max(minPx, size - step);
            el.style.fontSize = size + 'px';
            iterations++;
        }
    };
})();
</script>
