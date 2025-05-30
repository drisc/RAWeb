<?php

declare(strict_types=1);

namespace App\Platform\Actions;

use App\Community\Enums\AwardType;
use App\Models\PlayerBadge;
use App\Models\PlayerGame;
use App\Models\System;
use App\Platform\Enums\UnlockMode;
use App\Platform\Events\PlayerBadgeAwarded;
use App\Platform\Events\PlayerBadgeLost;
use App\Platform\Events\PlayerGameBeaten;
use App\Platform\Events\PlayerGameCompleted;
use Carbon\Carbon;

class RevalidateAchievementSetBadgeEligibilityAction
{
    public function execute(PlayerGame $playerGame): void
    {
        // TODO do this for each player_achievement_set as soon as achievement set separation is introduced

        if (!$playerGame->user) {
            return;
        }

        if ($playerGame->game->ConsoleID === System::Events) {
            $playerGame->game->loadMissing('event');
            $this->revalidateEventBadgeEligibility($playerGame);
        } else {
            $this->revalidateBeatenBadgeEligibility($playerGame);
            $this->revalidateCompletionBadgeEligibility($playerGame);
        }
    }

    private function revalidateBeatenBadgeEligibility(PlayerGame $playerGame): void
    {
        $badge = $playerGame->user->playerBadges()
            ->where('AwardType', AwardType::GameBeaten)
            ->where('AwardData', $playerGame->game->id);
        $softcoreBadge = (clone $badge)->where('AwardDataExtra', UnlockMode::Softcore);
        $hardcoreBadge = (clone $badge)->where('AwardDataExtra', UnlockMode::Hardcore);

        if ($playerGame->beaten_at === null && $softcoreBadge->exists()) {
            $this->dispatchBadgeLostEvent($softcoreBadge->first());
            $softcoreBadge->delete();
        }

        if ($playerGame->beaten_hardcore_at === null && $hardcoreBadge->exists()) {
            $this->dispatchBadgeLostEvent($hardcoreBadge->first());
            $hardcoreBadge->delete();
        }

        if ($playerGame->beaten_hardcore_at === null && $playerGame->beaten_at !== null && !$softcoreBadge->exists()) {
            $badge = AddSiteAward(
                $playerGame->user,
                AwardType::GameBeaten,
                $playerGame->game->id,
                UnlockMode::Softcore,
                $playerGame->beaten_at,
            );
            PlayerBadgeAwarded::dispatch($badge);
            PlayerGameBeaten::dispatch($playerGame->user, $playerGame->game);
        }

        if ($playerGame->beaten_hardcore_at !== null && !$hardcoreBadge->exists()) {
            $softcoreBadge->delete();

            $badge = AddSiteAward(
                $playerGame->user,
                AwardType::GameBeaten,
                $playerGame->game->id,
                UnlockMode::Hardcore,
                $playerGame->beaten_hardcore_at,
            );
            PlayerBadgeAwarded::dispatch($badge);
            PlayerGameBeaten::dispatch($playerGame->user, $playerGame->game, true);

            if ($playerGame->beaten_hardcore_at->gte(Carbon::now()->subMinutes(10))) {
                static_addnewhardcoregamebeaten($playerGame->game->id, $playerGame->user->username);
            }
        }
    }

    private function revalidateCompletionBadgeEligibility(PlayerGame $playerGame): void
    {
        $badge = $playerGame->user->playerBadges()
            ->where('AwardType', AwardType::Mastery)
            ->where('AwardData', $playerGame->game->id);
        $softcoreBadge = (clone $badge)->where('AwardDataExtra', UnlockMode::Softcore);
        $hardcoreBadge = (clone $badge)->where('AwardDataExtra', UnlockMode::Hardcore);

        if ($playerGame->completed_at === null && $softcoreBadge->exists()) {
            // if the user has at least one unlock for the set, assume there was
            // a revision and do nothing. if they want to get rid of the badge,
            // they can reset one or more of the achievements they have.
            if (!$playerGame->achievements_unlocked && $playerGame->achievements_total) {
                $this->dispatchBadgeLostEvent($softcoreBadge->first());
                $softcoreBadge->delete();
            }
        }

        if ($playerGame->completed_hardcore_at === null && $hardcoreBadge->exists()) {
            // user has no achievements for the set. if the set is empty, assume it
            // was demoted and keep the badge, otherwise assume they did a full reset
            // and destroy the badge.
            if (!$playerGame->achievements_unlocked && !$playerGame->achievements_unlocked_hardcore && $playerGame->achievements_total) {
                $this->dispatchBadgeLostEvent($hardcoreBadge->first());
                $hardcoreBadge->delete();
            }
        }

        if ($playerGame->achievements_total < PlayerBadge::MINIMUM_ACHIEVEMENTS_COUNT_FOR_MASTERY) {
            return;
        }

        if ($playerGame->completed_hardcore_at === null && $playerGame->completed_at !== null && !$softcoreBadge->exists()) {
            $badge = AddSiteAward(
                $playerGame->user,
                AwardType::Mastery,
                $playerGame->game->id,
                UnlockMode::Softcore,
                $playerGame->completed_at,
            );
            PlayerBadgeAwarded::dispatch($badge);
            PlayerGameCompleted::dispatch($playerGame->user, $playerGame->game);
        }

        if ($playerGame->completed_hardcore_at !== null && !$hardcoreBadge->exists()) {
            $softcoreBadge->delete();

            $badge = AddSiteAward(
                $playerGame->user,
                AwardType::Mastery,
                $playerGame->game->id,
                UnlockMode::Hardcore,
                $playerGame->completed_hardcore_at,
            );
            PlayerBadgeAwarded::dispatch($badge);
            PlayerGameCompleted::dispatch($playerGame->user, $playerGame->game, true);

            if ($playerGame->completed_hardcore_at->gte(Carbon::now()->subMinutes(10))) {
                static_addnewhardcoremastery($playerGame->game->id, $playerGame->user->username);
            }

            expireGameTopAchievers($playerGame->game->id);
        }
    }

    private function dispatchBadgeLostEvent(PlayerBadge $badge): void
    {
        PlayerBadgeLost::dispatch(
            $badge->user,
            $badge->AwardType,
            $badge->AwardData,
            $badge->AwardDataExtra,
        );
    }

    private function revalidateEventBadgeEligibility(PlayerGame $playerGame): void
    {
        $event = $playerGame->game->event;
        if (!$event) {
            // no event information, just check for all achievement unlocks (legacy event)
            $this->revalidateCompletionBadgeEligibility($playerGame);

            return;
        }

        $expectedAward = $event->awards->sortByDesc('points_required')
            ->where('points_required', '<=', $playerGame->points_hardcore)
            ->first();

        if ($expectedAward) {
            // found an award the user is eligible for
            $expectedTier = $expectedAward->tier_index;
        } elseif ($event->awards->isEmpty()
                && $playerGame->game->achievements_published > 0
                && $playerGame->achievements_unlocked_hardcore === $playerGame->game->achievements_published) {
            // no awards available, award tier 0 if all achievements have been unlocked
            $expectedTier = 0;
        } else {
            // player is not eligible for any awards for the event
            $expectedTier = -1;
        }

        $existingAward = $playerGame->user->playerBadges->where('AwardType', AwardType::Event)
            ->where('AwardData', $event->id)
            ->first();
        if ($existingAward) {
            if ($existingAward->AwardDataExtra >= $expectedTier) {
                // player already has the appropriate award (or better - never downgrade an award due to resetting)
                return;
            }

            // upgraded the badge and update the AwardDate.
            $existingAward->AwardDataExtra = $expectedTier;
            $existingAward->AwardDate = Carbon::now();
            $existingAward->save();
        } else {
            if ($expectedTier === -1) {
                // player doesn't have an award, and isn't due one
                return;
            }

            // new award
            $existingAward = AddSiteAward($playerGame->user, AwardType::Event, $event->id, $expectedTier);
        }

        PlayerBadgeAwarded::dispatch($existingAward);
    }
}
