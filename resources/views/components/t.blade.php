@props(['k', 'params' => []])
<span {{ $attributes }} x-text="$store.i18n.t(@js($k), @js($params))">{{ __('ui.'.$k, $params) }}</span>
