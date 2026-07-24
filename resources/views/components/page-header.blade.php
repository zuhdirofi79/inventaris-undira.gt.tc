{{--
  Enterprise page header — Inventaris UNDIRA
  Usage:
    <x-page-header
        title="Tambah Inventaris"
        subtitle="Kelola inventaris Universitas Dian Nusantara"
        icon="bi bi-plus-lg"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Inventaris', 'url' => route('inventory.index')],
            ['label' => 'Tambah'],
        ]"
        :back-route="route('inventory.index')"
        back-label="Data Inventaris"
        :list-back="true"
    >
        <x-slot:actions>
            <button type="submit" form="..." class="btn btn-undira btn-sm">Simpan</button>
        </x-slot:actions>
    </x-page-header>
--}}
@php
    /** @var \App\View\Components\PageHeader $component */
    $crumbs = $crumbItems();
    $backUrl = $backHref();
    $backText = $resolvedBackLabel();
    $hasActions = isset($actions) && ! $actions->isEmpty();
    $hasDefaultSlot = $slot->isNotEmpty();
    $actionContent = $hasActions ? $actions : ($hasDefaultSlot ? $slot : null);
@endphp

<div
    {{ $attributes->class(['ui-page-header', 'page-header-undira', 'page-header-enterprise']) }}
    @if($shortcuts)
        data-page-header
        data-back-url="{{ $showBack() ? $backUrl : '' }}"
        data-form-selector="{{ $formSelector }}"
        data-search-selector="{{ $searchSelector }}"
        data-filter-selector="{{ $filterSelector }}"
    @endif
>
    @if(count($crumbs) > 0)
        <nav class="page-header-enterprise__breadcrumb" aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-undira mb-0">
                @foreach($crumbs as $crumb)
                    @if($crumb['active'] || ! $crumb['url'])
                        <li class="breadcrumb-item active" aria-current="page">{{ $crumb['label'] }}</li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a></li>
                    @endif
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="page-header-enterprise__row">
        <div class="ui-page-header__main min-w-0">
            <h1 class="ui-page-header__title">
                @if($icon)
                    <span class="ui-page-header__icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
                @endif
                <span class="text-break">{{ $title }}</span>
            </h1>

            @isset($meta)
                <div class="ui-page-header__subtitle">{{ $meta }}</div>
            @elseif($subtitle)
                <p class="ui-page-header__subtitle mb-0">{{ $subtitle }}</p>
            @endif
        </div>

        @if($actionContent || $showBack())
            <div class="ui-page-header__actions page-header-enterprise__actions d-flex flex-wrap gap-2 align-items-center justify-content-md-end">
                @if($actionContent)
                    {{ $actionContent }}
                @endif

                @if($showBack())
                    <a
                        href="{{ $backUrl }}"
                        class="btn btn-outline-secondary btn-sm page-header-back"
                        data-page-back
                        title="Kembali ke {{ $backText }} (Esc)"
                    >
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ $backText }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
