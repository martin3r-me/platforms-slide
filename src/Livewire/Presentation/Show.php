<?php

namespace Platform\Slides\Livewire\Presentation;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Slides\Models\SlidesPresentation;
use Platform\Slides\Models\SlidesSlide;
use Platform\Slides\Models\SlidesSlideTemplate;
use Livewire\Attributes\On;

class Show extends Component
{
    public SlidesPresentation $presentation;

    public function mount(SlidesPresentation $slidesPresentation)
    {
        $this->presentation = $slidesPresentation;
        $this->authorize('view', $this->presentation);
    }

    #[On('updatePresentation')]
    public function updatePresentation()
    {
        $this->presentation->refresh();
    }

    public function addSlide($layoutKey = 'content-text')
    {
        $this->authorize('update', $this->presentation);

        $maxOrder = $this->presentation->slides()->max('sort_order') ?? -1;

        $layouts = SlidesSlideTemplate::systemLayouts();
        $layout = collect($layouts)->firstWhere('layout_key', $layoutKey);

        $slide = $this->presentation->slides()->create([
            'sort_order' => $maxOrder + 1,
            'layout_key' => $layoutKey,
            'content' => $layout['content'] ?? ['version' => 1, 'mode' => 'layout', 'elements' => []],
            'background' => $layout['background'] ?? ['type' => 'color', 'value' => '#ffffff'],
        ]);

        $this->presentation->refresh();
        return $this->redirect(route('slides.presentations.slides.edit', [$this->presentation, $slide]), navigate: true);
    }

    public function duplicateSlide($slideId)
    {
        $this->authorize('update', $this->presentation);

        $slide = SlidesSlide::findOrFail($slideId);

        if ($slide->presentation_id !== $this->presentation->id) {
            return;
        }

        // Shift all slides after this one
        $this->presentation->slides()
            ->where('sort_order', '>', $slide->sort_order)
            ->increment('sort_order');

        $this->presentation->slides()->create([
            'sort_order' => $slide->sort_order + 1,
            'layout_key' => $slide->layout_key,
            'content' => $slide->content,
            'background' => $slide->background,
            'transition' => $slide->transition,
            'notes' => $slide->notes,
            'duration_seconds' => $slide->duration_seconds,
        ]);

        $this->presentation->refresh();
    }

    public function deleteSlide($slideId)
    {
        $this->authorize('update', $this->presentation);

        $slide = SlidesSlide::findOrFail($slideId);

        if ($slide->presentation_id !== $this->presentation->id) {
            return;
        }

        $slide->delete();

        // Re-order remaining slides
        $this->presentation->slides()
            ->orderBy('sort_order')
            ->get()
            ->each(function ($s, $index) {
                $s->update(['sort_order' => $index]);
            });

        $this->presentation->refresh();
    }

    public function toggleHideSlide($slideId)
    {
        $this->authorize('update', $this->presentation);

        $slide = SlidesSlide::findOrFail($slideId);

        if ($slide->presentation_id !== $this->presentation->id) {
            return;
        }

        $slide->update(['is_hidden' => !$slide->is_hidden]);
        $this->presentation->refresh();
    }

    public function reorderSlides($orderedIds)
    {
        $this->authorize('update', $this->presentation);

        foreach ($orderedIds as $index => $slideId) {
            SlidesSlide::where('id', $slideId)
                ->where('presentation_id', $this->presentation->id)
                ->update(['sort_order' => $index]);
        }

        $this->presentation->refresh();
    }

    public function updateName($name)
    {
        $this->authorize('update', $this->presentation);

        $name = trim($name);
        if (empty($name)) {
            return;
        }

        $this->presentation->update(['name' => $name]);
        $this->presentation->refresh();
    }

    public function deletePresentation()
    {
        $this->authorize('delete', $this->presentation);

        $folderId = $this->presentation->folder_id;
        $this->presentation->delete();

        $this->dispatch('updateSidebar');

        if ($folderId) {
            return $this->redirect(route('slides.folders.show', $folderId), navigate: true);
        }

        return $this->redirect(route('slides.dashboard'), navigate: true);
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => get_class($this->presentation),
            'modelId' => $this->presentation->id,
            'subject' => $this->presentation->name,
            'description' => $this->presentation->description ?? '',
            'url' => route('slides.presentations.show', $this->presentation),
            'source' => 'slides.presentation.view',
            'recipients' => [],
            'meta' => [
                'created_at' => $this->presentation->created_at,
            ],
        ]);
    }

    public function render()
    {
        $slides = $this->presentation->slides()->orderBy('sort_order')->get();

        return view('slides::livewire.presentation.show', [
            'slides' => $slides,
        ])->layout('platform::layouts.app');
    }
}
