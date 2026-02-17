<?php

namespace Platform\Slides\Livewire\Presentation;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Platform\Slides\Models\SlidesPresentation;
use Platform\Slides\Models\SlidesSlide;
use Platform\Slides\Models\SlidesSlideTemplate;
use Platform\Slides\Models\SlidesMedia;
use Livewire\Attributes\On;

class SlideEditor extends Component
{
    use WithFileUploads;

    public SlidesPresentation $presentation;
    public SlidesSlide $slide;

    public ?string $notes = '';
    public string $backgroundType = 'color';
    public string $backgroundValue = '#ffffff';
    public string $gradientDirection = 'to-br';
    public string $gradientStop1 = '#667eea';
    public string $gradientStop2 = '#764ba2';
    public ?string $transition = null;

    public $mediaUpload;

    public function mount(SlidesPresentation $slidesPresentation, SlidesSlide $slidesSlide)
    {
        $this->presentation = $slidesPresentation;
        $this->slide = $slidesSlide;
        $this->authorize('update', $this->presentation);

        if ($this->slide->presentation_id !== $this->presentation->id) {
            abort(404);
        }

        $this->notes = $this->slide->notes ?? '';
        $this->transition = $this->slide->transition;

        $bg = $this->slide->background ?? ['type' => 'color', 'value' => '#ffffff'];
        $this->backgroundType = $bg['type'] ?? 'color';

        if ($this->backgroundType === 'color') {
            $this->backgroundValue = $bg['value'] ?? '#ffffff';
        } elseif ($this->backgroundType === 'gradient') {
            $this->gradientDirection = $bg['value']['direction'] ?? 'to-br';
            $this->gradientStop1 = $bg['value']['stops'][0] ?? '#667eea';
            $this->gradientStop2 = $bg['value']['stops'][1] ?? '#764ba2';
        }
    }

    /**
     * Save slide content from Alpine.js canvas.
     */
    public function saveContent(array $elements)
    {
        $this->authorize('update', $this->presentation);

        $content = $this->slide->content ?? ['version' => 1, 'mode' => 'layout', 'elements' => []];
        $content['elements'] = $elements;

        $this->slide->update(['content' => $content]);
    }

    public function saveNotes()
    {
        $this->authorize('update', $this->presentation);
        $this->slide->update(['notes' => $this->notes ?: null]);
    }

    public function saveBackground()
    {
        $this->authorize('update', $this->presentation);

        $background = match ($this->backgroundType) {
            'color' => ['type' => 'color', 'value' => $this->backgroundValue],
            'gradient' => ['type' => 'gradient', 'value' => [
                'direction' => $this->gradientDirection,
                'stops' => [$this->gradientStop1, $this->gradientStop2],
            ]],
            default => ['type' => 'color', 'value' => '#ffffff'],
        };

        $this->slide->update(['background' => $background]);
    }

    public function saveTransition()
    {
        $this->authorize('update', $this->presentation);
        $this->slide->update(['transition' => $this->transition ?: null]);
    }

    public function changeLayout(string $layoutKey)
    {
        $this->authorize('update', $this->presentation);

        $layouts = SlidesSlideTemplate::systemLayouts();
        $layout = collect($layouts)->firstWhere('layout_key', $layoutKey);

        if (!$layout) {
            return;
        }

        // Apply theme fontSizes to the template content
        $content = $layout['content'];
        $themeFontSizes = $this->presentation->theme['fontSizes'] ?? [];
        $content = SlidesSlideTemplate::applyThemeFontSizes($content, $themeFontSizes);

        $this->slide->update([
            'layout_key' => $layoutKey,
            'content' => $content,
        ]);

        if (isset($layout['background'])) {
            $this->slide->update(['background' => $layout['background']]);
        }

        $this->slide->refresh();
    }

    public function uploadMedia()
    {
        $this->authorize('update', $this->presentation);

        $this->validate([
            'mediaUpload' => 'required|image|max:10240',
        ]);

        $user = Auth::user();
        $path = $this->mediaUpload->store('slides/media', 'public');
        $imageSize = getimagesize($this->mediaUpload->getRealPath());

        $media = SlidesMedia::create([
            'presentation_id' => $this->presentation->id,
            'filename' => $this->mediaUpload->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $this->mediaUpload->getMimeType(),
            'file_size' => $this->mediaUpload->getSize(),
            'width' => $imageSize[0] ?? null,
            'height' => $imageSize[1] ?? null,
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id ?? null,
        ]);

        $this->mediaUpload = null;

        $this->dispatch('mediaUploaded', [
            'id' => $media->id,
            'src' => '/storage/' . $path,
            'filename' => $media->filename,
            'width' => $media->width,
            'height' => $media->height,
        ]);
    }

    public function navigateToSlide($slideId)
    {
        $slide = SlidesSlide::where('id', $slideId)
            ->where('presentation_id', $this->presentation->id)
            ->first();

        if ($slide) {
            return $this->redirect(
                route('slides.presentations.slides.edit', [$this->presentation, $slide]),
                navigate: true
            );
        }
    }

    public function render()
    {
        $allSlides = $this->presentation->slides()->orderBy('sort_order')->get();
        $currentIndex = $allSlides->search(fn($s) => $s->id === $this->slide->id);
        $prevSlide = $currentIndex > 0 ? $allSlides[$currentIndex - 1] : null;
        $nextSlide = $currentIndex < $allSlides->count() - 1 ? $allSlides[$currentIndex + 1] : null;

        return view('slides::livewire.presentation.slide-editor', [
            'allSlides' => $allSlides,
            'currentIndex' => $currentIndex,
            'prevSlide' => $prevSlide,
            'nextSlide' => $nextSlide,
        ])->layout('platform::layouts.app');
    }
}
