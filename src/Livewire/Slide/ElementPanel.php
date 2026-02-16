<?php

namespace Platform\Slides\Livewire\Slide;

use Livewire\Component;

class ElementPanel extends Component
{
    public array $element = [];

    public function mount(array $element = [])
    {
        $this->element = $element;
    }

    public function render()
    {
        return view('slides::livewire.slide.element-panel');
    }
}
