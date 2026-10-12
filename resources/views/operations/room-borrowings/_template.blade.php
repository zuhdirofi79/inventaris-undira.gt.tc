@if(! empty($templates[$kind]['url']))
    <a href="{{ $templates[$kind]['url'] }}" class="btn btn-sm btn-outline-success mt-2" data-no-loader="1">
        {{ $templates[$kind]['label'] }}
    </a>
@endif
