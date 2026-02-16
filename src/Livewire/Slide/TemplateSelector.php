<?php

namespace Platform\Slides\Livewire\Slide;

use Livewire\Component;
use Platform\Slides\Models\SlidesSlideTemplate;

class TemplateSelector extends Component
{
    public ?string $selectedCategory = null;

    public function selectCategory(?string $category)
    {
        $this->selectedCategory = $category;
    }

    public function render()
    {
        $layouts = SlidesSlideTemplate::systemLayouts();
        $categories = [
            'title' => 'Titel',
            'content' => 'Inhalt',
            'media' => 'Medien',
            'closing' => 'Abschluss',
        ];

        $filteredLayouts = $this->selectedCategory
            ? collect($layouts)->where('category', $this->selectedCategory)->values()
            : collect($layouts);

        return view('slides::livewire.slide.template-selector', [
            'layouts' => $filteredLayouts,
            'categories' => $categories,
        ]);
    }
}
