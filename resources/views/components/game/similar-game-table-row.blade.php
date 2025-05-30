@props([
    'gameId' => 0,
    'gameTitle' => '',
    'gameImageIcon' => '',
    'gameSetId' => null, // ?int
    'consoleName' => '',
    'isFullyFeaturedGame' => false,
    'totalPoints' => 0,
    'totalRetroPoints' => 0,
])

<?php

$href = route('game.show', $gameId);
if ($gameSetId) {
    $href = route('hub.show', ['gameSet' => $gameSetId]);
}

?>

<tr>
    <td class="w-full py-2">
        <x-game.multiline-avatar
            :dynamicType="$gameSetId ? 'hub' : 'game'"
            :gameId="$gameSetId ?? $gameId"
            :gameTitle="$gameTitle"
            :gameImageIcon="$gameImageIcon"
            :consoleName="$consoleName"
            :href="$href"
        />
    </td>

    @if ($isFullyFeaturedGame && $totalPoints > 0)
        <td>
            <div class="flex flex-col items-end">
                <p class="text-2xs whitespace-nowrap {{ $totalPoints === 0 ? 'text-text-muted' : '' }}">
                    <span>
                        {{ localized_number($totalPoints) }} points
                    </span>
                </p>

                <p class="text-2xs whitespace-nowrap {{ $totalRetroPoints === 0 ? 'text-text-muted' : '' }}">
                    <x-points-weighted-container>
                        @if ($totalRetroPoints > 0)
                            ({{ localized_number($totalRetroPoints) }})
                        @endif
                    </x-points-weighted-container>
                </p>
            </div>
        </td>
    @else
        <td class="min-w-[58px]"></td>
    @endif
</tr>
