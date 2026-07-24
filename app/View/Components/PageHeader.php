<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Enterprise page chrome: breadcrumb + title + subtitle + actions + smart back.
 */
class PageHeader extends Component
{
    /**
     * @param  list<array{label:string,url?:string|null}>  $breadcrumbs
     */
    public function __construct(
        public string $title,
        public ?string $subtitle = null,
        public ?string $icon = null,
        public array $breadcrumbs = [],
        public ?string $backRoute = null,
        public ?string $backLabel = null,
        public bool $smartBack = true,
        public bool $listBack = false,
        public bool $shortcuts = true,
        public ?string $formSelector = 'form.inventory-form, form[data-page-shortcut-save], .inventory-form',
        public ?string $searchSelector = 'input[type="search"], input[name="search"], input[name="q"], #search, .page-search-input',
        public ?string $filterSelector = '#btnApplyFilter, .page-filter-trigger, [data-page-shortcut-filter]',
    ) {}

    public function backHref(): ?string
    {
        $fallback = $this->normalizeUrl($this->backRoute);

        if ($this->listBack) {
            return $fallback;
        }

        if ($this->smartBack) {
            $previous = url()->previous();
            if ($this->isUsablePrevious($previous)) {
                return $previous;
            }
        }

        return $fallback;
    }

    public function resolvedBackLabel(): ?string
    {
        if (filled($this->backLabel)) {
            return $this->backLabel;
        }

        if (filled($this->backRoute)) {
            return 'Halaman sebelumnya';
        }

        return null;
    }

    public function showBack(): bool
    {
        return filled($this->backHref()) && filled($this->resolvedBackLabel());
    }

    /**
     * @return list<array{label:string,url:?string,active:bool}>
     */
    public function crumbItems(): array
    {
        $items = [];
        $count = count($this->breadcrumbs);

        foreach ($this->breadcrumbs as $index => $crumb) {
            $label = trim((string) ($crumb['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $url = isset($crumb['url']) ? $this->normalizeUrl($crumb['url']) : null;
            $isLast = $index === $count - 1;

            $items[] = [
                'label' => $label,
                'url' => $isLast ? null : $url,
                'active' => $isLast,
            ];
        }

        return $items;
    }

    public function render(): View
    {
        return view('components.page-header');
    }

    protected function normalizeUrl(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $value = trim($value);

        if (Str::startsWith($value, ['http://', 'https://', '/'])) {
            return $value;
        }

        if (\Illuminate\Support\Facades\Route::has($value)) {
            return route($value);
        }

        return $value;
    }

    protected function isUsablePrevious(?string $previous): bool
    {
        if (! filled($previous)) {
            return false;
        }

        $current = url()->current();
        if ($previous === $current) {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $prevHost = parse_url($previous, PHP_URL_HOST);
        if ($appHost && $prevHost && strcasecmp($appHost, $prevHost) !== 0) {
            return false;
        }

        $path = parse_url($previous, PHP_URL_PATH) ?? '';
        foreach (['/login', '/register', '/forgot-password', '/reset-password', '/password-otp', '/verify-email'] as $blocked) {
            if (Str::contains($path, $blocked)) {
                return false;
            }
        }

        return true;
    }
}
