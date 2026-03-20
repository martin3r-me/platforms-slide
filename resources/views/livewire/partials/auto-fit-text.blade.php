{{-- Auto-Fit / Shrink-to-Fit for text elements --}}
<script>
(function() {
    if (window.__slideAutoFit) return;

    /**
     * Shrink-to-fit: reduces font-size via binary search until text fits within its container.
     *
     * @param {HTMLElement} el       - The text container element
     * @param {number}      basePx   - The original / desired font-size in px
     * @param {number}      minPx    - The minimum font-size in px (default 22)
     */
    window.__slideAutoFit = function(el, basePx, minPx) {
        if (!el || !el.parentElement) return;

        minPx = minPx || 22;
        basePx = basePx || 24;

        // Try the original font-size first
        el.style.fontSize = basePx + 'px';

        // If it fits at base size, we're done
        if (el.scrollHeight <= el.clientHeight + 1) return;

        // Binary search for the largest size that fits
        var lo = minPx;
        var hi = basePx;
        var maxIterations = 10;
        var iterations = 0;

        while (hi - lo > 1 && iterations < maxIterations) {
            var mid = Math.floor((lo + hi) / 2);
            el.style.fontSize = mid + 'px';

            if (el.scrollHeight > el.clientHeight + 1) {
                hi = mid;
            } else {
                lo = mid;
            }
            iterations++;
        }

        // Use the last known fitting size
        el.style.fontSize = lo + 'px';
    };
})();
</script>
