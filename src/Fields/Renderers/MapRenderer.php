<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\MapField;

/**
 * Address search (OpenStreetMap Nominatim), latitude / longitude inputs and an
 * OpenStreetMap preview. Value: { lat, lng, zoom, address }.
 */
final class MapRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $p    = $this->esc($statePath);
        $zoom = $field instanceof MapField ? $field->getZoom() : 14;
        $id   = $this->esc($this->inputId($field));

        $searchLabel = $this->esc(__('Search address', 'ctrlfield'));
        $searchBtn   = $this->esc(__('Search', 'ctrlfield'));
        $latLabel    = $this->esc(__('Latitude', 'ctrlfield'));
        $lngLabel    = $this->esc(__('Longitude', 'ctrlfield'));
        $clear       = $this->esc(__('Clear location', 'ctrlfield'));
        $none        = $this->esc(__('No results.', 'ctrlfield'));

        return <<<HTML
<div class="ctrlf-map" x-data="{ q: '', results: [], searched: false, busy: false }">
    <div class="ctrlf-map__search">
        <input type="search" id="{$id}" class="ctrlf-input" placeholder="{$searchLabel}" x-model="q"
               @keydown.enter.prevent="busy = true; results = await geocode(q); searched = true; busy = false">
        <button type="button" class="button" :disabled="busy"
                @click="busy = true; results = await geocode(q); searched = true; busy = false">{$searchBtn}</button>
    </div>
    <ul class="ctrlf-picker__results" x-show="results.length">
        <template x-for="(r, i) in results" :key="i">
            <li><button type="button" class="ctrlf-picker__result"
                @click="{$p} = { lat: r.lat, lng: r.lng, zoom: {$zoom}, address: r.address }; results = []; q = ''; searched = false"
                x-text="r.address"></button></li>
        </template>
    </ul>
    <p class="description" x-show="searched && !busy && !results.length">{$none}</p>
    <p class="ctrlf-map__address" x-show="{$p} && {$p}.address" x-text="{$p} ? {$p}.address : ''"></p>
    <div class="ctrlf-map__coords">
        <label>{$latLabel}
            <input type="number" step="any" class="ctrlf-input" :value="{$p} && {$p}.lat !== null ? {$p}.lat : ''"
                   @change="{$p} = mapWith({$p}, 'lat', \$event.target.value, {$zoom})"></label>
        <label>{$lngLabel}
            <input type="number" step="any" class="ctrlf-input" :value="{$p} && {$p}.lng !== null ? {$p}.lng : ''"
                   @change="{$p} = mapWith({$p}, 'lng', \$event.target.value, {$zoom})"></label>
    </div>
    <template x-if="hasCoords({$p})">
        <div>
            <iframe class="ctrlf-map__preview" :src="osmEmbedUrl({$p})" width="100%" height="260" loading="lazy" title="Map"></iframe>
            <button type="button" class="button-link ctrlf-picker__remove" @click="{$p} = null">{$clear}</button>
        </div>
    </template>
</div>
HTML;
    }
}
