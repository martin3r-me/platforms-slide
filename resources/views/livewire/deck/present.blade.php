<div wire:poll.{{ $poll }}s="refreshIfChanged"
     x-data @keydown.window.arrow-right.prevent="$wire.next()" @keydown.window.arrow-left.prevent="$wire.previous()"
     @keydown.window.space.prevent="$wire.next()"
     style="position:fixed; inset:0; display:flex; flex-direction:column; background:#111418;">
    <div style="flex:1; display:flex; align-items:center; justify-content:center; padding:16px;">
        @if($doc)
            <div style="width:min(100%, calc((100vh - 72px) * {{ $size->width() }} / {{ $size->height() }}));" wire:key="present-{{ $slide->id }}-{{ $deck->revision }}">
                @include('slides::livewire.partials.slide-frame', ['doc' => $doc, 'size' => $size, 'label' => 'Folie ' . ($index + 1)])
            </div>
        @else
            <p style="color:#9aa3ad;">Dieses Deck hat noch keine sichtbaren Folien.</p>
        @endif
    </div>
    <div style="height:56px; display:flex; align-items:center; justify-content:space-between; padding:0 20px; color:#9aa3ad; font-size:14px;">
        <a href="{{ route('slides.decks.show', $deck) }}" style="color:#9aa3ad; text-decoration:none;">← {{ $deck->title }}</a>
        <div style="display:flex; align-items:center; gap:12px;">
            <button type="button" wire:click="previous" style="background:none; border:1px solid #3a4250; color:#e8e6e1; border-radius:6px; padding:4px 12px; cursor:pointer;">‹</button>
            <span>{{ $count ? $index + 1 : 0 }} / {{ $count }}</span>
            <button type="button" wire:click="next" style="background:none; border:1px solid #3a4250; color:#e8e6e1; border-radius:6px; padding:4px 12px; cursor:pointer;">›</button>
        </div>
    </div>
</div>
