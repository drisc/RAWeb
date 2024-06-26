<?php

use Illuminate\Database\Eloquent\Model;

$display ??= 'name';
$resource ??= 'resource';
if (empty($model) || !($model instanceof Model)) {
    if ($display == 'name') {
        echo '<i>' . __('Unknown :Resource', ['resource' => $resource]) . '</i>';
    }

    return;
}

$tooltip ??= true;
if (!($model->exists ?? false)) {
    $tooltip = false;
}

$tooltipIconSize = 'md';

$tooltipContent = null;
// if ($tooltip && view()->exists('components.' . $resource . '.card')) {
//     /**
//      * cache all those cards
//      */
//     $cacheKey = 'views:components:' . $resource . ':card:' . $model->id;
//     $tooltipContent = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($resource, $model) {
//         $tooltipContent = view('components.' . $resource . '.card', [$resource => $model, 'tooltips' => false])->render();
//         $tooltipContent = str_replace(['"'], ["'"], $tooltipContent);
//         return $tooltipContent;
//     });
// }
?>

{{--
    TODO refactor, this template is difficult to reason about.
    maybe create a dynamic WrapperEl component which can conditionally be an <a> or a <span>.
    eg: <{{ $element }}></{{ $element }}>
--}}

@if ($href)
    <a href="{{ $href }}" class="{{ $class }} inline-block">
@elseif ($class)
    <span class="{{ $class }}">
@endif

@if ($tooltipContent)
    <span data-toggle="tooltip" title="{!! $tooltipContent !!}" data-placement="right">
@endif

{{ $slot }}

@if ($tooltipContent)
    </span>
@endif

@if ($href)
    </a>
@elseif ($class)
    </span>
@endif
