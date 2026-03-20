<?php

namespace Platform\Slides\Livewire\Presentation;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Platform\Slides\Models\SlidesPresentation;

class Settings extends Component
{
    public SlidesPresentation $presentation;

    public string $name = '';
    public string $description = '';
    public int $slideWidth = 1920;
    public int $slideHeight = 1080;
    public bool $isPublished = false;
    public ?string $publicToken = null;

    // Branding (Settings)
    public bool $slideNumberEnabled = false;
    public string $slideNumberPosition = 'bottom-right';
    public bool $footerEnabled = false;
    public string $footerText = '';

    // Theme
    public string $colorPrimary = '#1a1a2e';
    public string $colorAccent = '#0f3460';
    public string $colorText = '#1a1a2e';
    public string $colorBackground = '#ffffff';
    public string $fontHeading = 'Open Sans';
    public string $fontBody = 'Open Sans';

    public function mount(SlidesPresentation $slidesPresentation)
    {
        $this->presentation = $slidesPresentation;
        $this->authorize('update', $this->presentation);

        $this->name = $this->presentation->name;
        $this->description = $this->presentation->description ?? '';
        $this->slideWidth = $this->presentation->slide_width;
        $this->slideHeight = $this->presentation->slide_height;
        $this->isPublished = $this->presentation->is_published;
        $this->publicToken = $this->presentation->public_token;

        // Load branding settings
        $settings = $this->presentation->settings;
        $this->slideNumberEnabled = $settings['slideNumber']['enabled'] ?? false;
        $this->slideNumberPosition = $settings['slideNumber']['position'] ?? 'bottom-right';
        $this->footerEnabled = $settings['footer']['enabled'] ?? false;
        $this->footerText = $settings['footer']['text'] ?? '';

        $theme = $this->presentation->theme;
        $this->colorPrimary = $theme['colors']['primary'] ?? '#1a1a2e';
        $this->colorAccent = $theme['colors']['accent'] ?? '#0f3460';
        $this->colorText = $theme['colors']['text'] ?? '#1a1a2e';
        $this->colorBackground = $theme['colors']['background'] ?? '#ffffff';
        $this->fontHeading = $theme['fonts']['heading'] ?? 'Open Sans';
        $this->fontBody = $theme['fonts']['body'] ?? 'Open Sans';
    }

    public function save()
    {
        $this->authorize('update', $this->presentation);

        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'slideWidth' => 'required|integer|min:800|max:3840',
            'slideHeight' => 'required|integer|min:600|max:2160',
        ]);

        // Preserve existing fontSizes and other theme data not managed by this UI
        $rawTheme = $this->presentation->getRawOriginal('theme');
        $existingTheme = $rawTheme ? json_decode($rawTheme, true) : [];

        $theme = [
            'colors' => [
                'primary' => $this->colorPrimary,
                'accent' => $this->colorAccent,
                'text' => $this->colorText,
                'background' => $this->colorBackground,
            ],
            'fonts' => [
                'heading' => $this->fontHeading,
                'body' => $this->fontBody,
            ],
            'defaultBackground' => [
                'type' => 'color',
                'value' => $this->colorBackground,
            ],
        ];

        // Preserve fontSizes if they exist in the current theme
        if (!empty($existingTheme['fontSizes'])) {
            $theme['fontSizes'] = $existingTheme['fontSizes'];
        }

        // Build settings (bypass accessor to avoid merging defaults into persisted data)
        $rawSettings = $this->presentation->getRawOriginal('settings');
        $existingSettings = $rawSettings ? json_decode($rawSettings, true) : [];

        $settings = array_replace_recursive($existingSettings, [
            'slideNumber' => [
                'enabled' => $this->slideNumberEnabled,
                'position' => $this->slideNumberPosition,
            ],
            'footer' => [
                'enabled' => $this->footerEnabled,
                'text' => $this->footerText,
            ],
        ]);

        $this->presentation->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'slide_width' => $this->slideWidth,
            'slide_height' => $this->slideHeight,
            'is_published' => $this->isPublished,
            'theme' => $theme,
            'settings' => $settings,
        ]);

        session()->flash('success', 'Einstellungen gespeichert.');
    }

    public function togglePublish()
    {
        $this->authorize('update', $this->presentation);

        $this->isPublished = !$this->isPublished;

        if ($this->isPublished && !$this->publicToken) {
            $this->publicToken = Str::random(64);
        }

        $this->presentation->update([
            'is_published' => $this->isPublished,
            'public_token' => $this->isPublished ? $this->publicToken : $this->presentation->public_token,
        ]);
    }

    public function regenerateToken()
    {
        $this->authorize('update', $this->presentation);

        $this->publicToken = Str::random(64);
        $this->presentation->update(['public_token' => $this->publicToken]);
        session()->flash('success', 'Neuer öffentlicher Link generiert.');
    }

    public function applyPreset(string $presetKey)
    {
        $this->authorize('update', $this->presentation);

        $preset = SlidesPresentation::getThemePreset($presetKey);
        if (!$preset) {
            return;
        }

        $this->colorPrimary = $preset['colors']['primary'];
        $this->colorAccent = $preset['colors']['accent'];
        $this->colorText = $preset['colors']['text'];
        $this->colorBackground = $preset['colors']['background'];
        $this->fontHeading = $preset['fonts']['heading'];
        $this->fontBody = $preset['fonts']['body'];
    }

    public function render()
    {
        return view('slides::livewire.presentation.settings')
            ->layout('platform::layouts.app');
    }
}
