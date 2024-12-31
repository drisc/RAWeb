<?php

declare(strict_types=1);

namespace Tests\Feature\Connect;

use App\Enums\Permissions;
use App\Models\Achievement;
use App\Models\EventAchievement;
use App\Models\Game;
use App\Models\GameHash;
use App\Models\PlayerSession;
use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\TestsEmulatorUserAgent;
use Tests\Feature\Platform\Concerns\TestsPlayerAchievements;
use Tests\TestCase;

class StartSessionTest extends TestCase
{
    use BootstrapsConnect;
    use RefreshDatabase;
    use TestsEmulatorUserAgent;
    use TestsPlayerAchievements;

    public function testStartSession(): void
    {
        $now = Carbon::create(2020, 3, 4, 16, 40, 13); // 4:40:13pm 4 Mar 2020
        Carbon::setTestNow($now);

        /** @var System $system */
        $system = System::factory()->create();
        /** @var Game $game */
        $game = Game::factory()->create(['ConsoleID' => $system->ID]);
        /** @var GameHash $gameHash */
        $gameHash = GameHash::factory()->create(['game_id' => $game->id]);
        /** @var Achievement $achievement1 */
        $achievement1 = Achievement::factory()->published()->create(['GameID' => $game->ID]);
        /** @var Achievement $achievement2 */
        $achievement2 = Achievement::factory()->published()->create(['GameID' => $game->ID]);
        /** @var Achievement $achievement3 */
        $achievement3 = Achievement::factory()->published()->create(['GameID' => $game->ID]);
        /** @var Achievement $achievement4 */
        $achievement4 = Achievement::factory()->published()->create(['GameID' => $game->ID]);

        $unlock1Date = $now->clone()->subMinutes(65);
        $this->addHardcoreUnlock($this->user, $achievement1, $unlock1Date);
        $unlock2Date = $now->clone()->subMinutes(22);
        $this->addHardcoreUnlock($this->user, $achievement2, $unlock2Date);
        $unlock3Date = $now->clone()->subMinutes(1);
        $this->addSoftcoreUnlock($this->user, $achievement3, $unlock3Date);

        // we want explicit control over the new session, so delete the session(s) generated by the unlocks
        $this->user->playerSessions()->delete();

        $this->seedEmulatorUserAgents();

        // ----------------------------
        // game with unlocks
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($this->apiUrl('startsession', ['g' => $game->ID, 'm' => $gameHash->md5]))
            ->assertExactJson([
                'Success' => true,
                'HardcoreUnlocks' => [
                    [
                        'ID' => $achievement1->ID,
                        'When' => $unlock1Date->timestamp,
                    ],
                    [
                        'ID' => $achievement2->ID,
                        'When' => $unlock2Date->timestamp,
                    ],
                ],
                'Unlocks' => [
                    [
                        'ID' => $achievement3->ID,
                        'When' => $unlock3Date->timestamp,
                    ],
                ],
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // player session created
        $playerSession = PlayerSession::where([
            'user_id' => $this->user->id,
            'game_id' => $achievement3->game_id,
        ])->first();
        $this->assertModelExists($playerSession);
        $this->assertEquals(1, $playerSession->duration);
        $this->assertEquals('Playing ' . $game->title, $playerSession->rich_presence);
        $this->assertEquals($this->userAgentValid, $playerSession->user_agent);
        $this->assertEquals($gameHash->id, $playerSession->game_hash_id);

        /** @var User $user1 */
        $user1 = User::firstWhere('User', $this->user->User);
        $this->assertEquals($game->ID, $user1->LastGameID);
        $this->assertEquals("Playing " . $game->Title, $user1->RichPresenceMsg);

        // ----------------------------
        // non-existent game
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($this->apiUrl('startsession', ['g' => 999999]))
            ->assertExactJson([
                'Success' => false,
                'Error' => 'Unknown game',
            ]);

        // no player session
        $playerSession = PlayerSession::where([
            'user_id' => $this->user->id,
            'game_id' => 999999,
        ])->first();
        $this->assertNull($playerSession);

        // ----------------------------
        // game with no unlocks
        /** @var Game $game2 */
        $game2 = Game::factory()->create(['ConsoleID' => $system->ID]);
        /** @var GameHash $gameHash */
        $gameHash2 = GameHash::factory()->create(['game_id' => $game2->id]);
        Achievement::factory()->published()->count(6)->create(['GameID' => $game->ID]);

        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($this->apiUrl('startsession', ['g' => $game2->ID, 'm' => $gameHash2->md5]))
            ->assertExactJson([
                'Success' => true,
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // player session created
        $playerSession = PlayerSession::where([
            'user_id' => $this->user->id,
            'game_id' => $game2->id,
        ])->first();
        $this->assertModelExists($playerSession);
        $this->assertEquals(1, $playerSession->duration);
        $this->assertEquals('Playing ' . $game2->title, $playerSession->rich_presence);
        $this->assertEquals($gameHash2->id, $playerSession->game_hash_id);

        $user1 = User::firstWhere('User', $this->user->User);
        $this->assertEquals($game2->ID, $user1->LastGameID);
        $this->assertEquals("Playing " . $game2->Title, $user1->RichPresenceMsg);

        // ----------------------------
        // recently active session is extended
        Carbon::setTestNow($now->addMinutes(8));
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($this->apiUrl('startsession', ['g' => $game2->ID, 'm' => $gameHash2->md5]))
            ->assertExactJson([
                'Success' => true,
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // player session created
        $playerSession2 = PlayerSession::where([
            'user_id' => $this->user->id,
            'game_id' => $game2->id,
        ])->orderByDesc('id')->first();
        $this->assertModelExists($playerSession2);
        $this->assertEquals($playerSession->id, $playerSession2->id);
        $this->assertEquals(8, $playerSession2->duration);
        $this->assertEquals('Playing ' . $game2->title, $playerSession2->rich_presence);
        $this->assertEquals($gameHash2->id, $playerSession->game_hash_id);

        // ----------------------------
        // new session created after long absence
        Carbon::setTestNow($now->addHours(4));
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($this->apiUrl('startsession', ['g' => $game2->ID, 'm' => $gameHash2->md5]))
            ->assertExactJson([
                'Success' => true,
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // player session created
        $playerSession2 = PlayerSession::where([
            'user_id' => $this->user->id,
            'game_id' => $game2->id,
        ])->orderByDesc('id')->first();
        $this->assertModelExists($playerSession2);
        $this->assertNotEquals($playerSession->id, $playerSession2->id);
        $this->assertEquals(1, $playerSession2->duration);
        $this->assertEquals('Playing ' . $game2->title, $playerSession2->rich_presence);

        // ----------------------------
        // not-unlocked event achievement hides hardcore unlock when active
        System::factory()->create(['ID' => System::Events]);
        /** @var Game $eventGame */
        $eventGame = Game::factory()->create(['ConsoleID' => System::Events]);
        /** @var Achievement $eventAchievement1 */
        $eventAchievement1 = Achievement::factory()->published()->create(['GameID' => $eventGame->ID]);

        Carbon::setTestNow($now->addWeeks(1));
        EventAchievement::create([
            'achievement_id' => $eventAchievement1->ID,
            'source_achievement_id' => $achievement1->ID,
            'active_from' => $now->clone()->subDays(1),
            'active_until' => $now->clone()->addDays(2),
        ]);
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($this->apiUrl('startsession', ['g' => $game->ID, 'm' => $gameHash->md5]))
            ->assertExactJson([
                'Success' => true,
                'HardcoreUnlocks' => [
                    [
                        'ID' => $achievement2->ID,
                        'When' => $unlock2Date->timestamp,
                    ],
                ],
                'Unlocks' => [
                    [
                        'ID' => $achievement1->ID,
                        'When' => $unlock1Date->timestamp,
                    ],
                    [
                        'ID' => $achievement3->ID,
                        'When' => $unlock3Date->timestamp,
                    ],
                ],
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // ----------------------------
        // after event achievement is unlocked, hardcore unlock is returned
        $this->addHardcoreUnlock($this->user, $eventAchievement1, $now);
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($this->apiUrl('startsession', ['g' => $game->ID, 'm' => $gameHash->md5]))
            ->assertExactJson([
                'Success' => true,
                'HardcoreUnlocks' => [
                    [
                        'ID' => $achievement1->ID,
                        'When' => $unlock1Date->timestamp,
                    ],
                    [
                        'ID' => $achievement2->ID,
                        'When' => $unlock2Date->timestamp,
                    ],
                ],
                'Unlocks' => [
                    [
                        'ID' => $achievement3->ID,
                        'When' => $unlock3Date->timestamp,
                    ],
                ],
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // ----------------------------
        // if multiple event achievements are active for a single source achievement, all must be unlocked to keep hardcore unlock
        /** @var Achievement $eventAchievement2 */
        $eventAchievement2 = Achievement::factory()->published()->create(['GameID' => $eventGame->ID]);
        EventAchievement::create([
            'achievement_id' => $eventAchievement2->ID,
            'source_achievement_id' => $achievement1->ID,
            'active_from' => $now->clone()->subDays(2),
            'active_until' => $now->clone()->addDays(3),
        ]);
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($this->apiUrl('startsession', ['g' => $game->ID, 'm' => $gameHash->md5]))
            ->assertExactJson([
                'Success' => true,
                'HardcoreUnlocks' => [
                    [
                        'ID' => $achievement2->ID,
                        'When' => $unlock2Date->timestamp,
                    ],
                ],
                'Unlocks' => [
                    [
                        'ID' => $achievement1->ID,
                        'When' => $unlock1Date->timestamp,
                    ],
                    [
                        'ID' => $achievement3->ID,
                        'When' => $unlock3Date->timestamp,
                    ],
                ],
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // ----------------------------
        // after all event achievements are unlocked, hardcore unlock is returned
        $this->addHardcoreUnlock($this->user, $eventAchievement2, $now);
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($this->apiUrl('startsession', ['g' => $game->ID, 'm' => $gameHash->md5]))
            ->assertExactJson([
                'Success' => true,
                'HardcoreUnlocks' => [
                    [
                        'ID' => $achievement1->ID,
                        'When' => $unlock1Date->timestamp,
                    ],
                    [
                        'ID' => $achievement2->ID,
                        'When' => $unlock2Date->timestamp,
                    ],
                ],
                'Unlocks' => [
                    [
                        'ID' => $achievement3->ID,
                        'When' => $unlock3Date->timestamp,
                    ],
                ],
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // ----------------------------
        // event achievement outside of active range is ignored
        /** @var Achievement $eventAchievement2 */
        $eventAchievement3 = Achievement::factory()->published()->create(['GameID' => $eventGame->ID]);
        EventAchievement::create([
            'achievement_id' => $eventAchievement2->ID,
            'source_achievement_id' => $achievement1->ID,
            'active_from' => $now->clone()->addDays(2),
            'active_until' => $now->clone()->addDays(5),
        ]);

        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($this->apiUrl('startsession', ['g' => $game->ID, 'm' => $gameHash->md5]))
            ->assertExactJson([
                'Success' => true,
                'HardcoreUnlocks' => [
                    [
                        'ID' => $achievement1->ID,
                        'When' => $unlock1Date->timestamp,
                    ],
                    [
                        'ID' => $achievement2->ID,
                        'When' => $unlock2Date->timestamp,
                    ],
                ],
                'Unlocks' => [
                    [
                        'ID' => $achievement3->ID,
                        'When' => $unlock3Date->timestamp,
                    ],
                ],
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // ----------------------------
        // new session from outdated emulator
        Carbon::setTestNow($now->addHours(16));
        $this->withHeaders(['User-Agent' => $this->userAgentOutdated])
            ->get($this->apiUrl('startsession', ['g' => $game->ID, 'm' => $gameHash2->md5]))
            ->assertExactJson([
                'Success' => true,
                'HardcoreUnlocks' => [
                    [
                        'ID' => $achievement1->ID,
                        'When' => $unlock1Date->timestamp,
                    ],
                    [
                        'ID' => $achievement2->ID,
                        'When' => $unlock2Date->timestamp,
                    ],
                ],
                'Unlocks' => [
                    [
                        'ID' => $achievement3->ID,
                        'When' => $unlock3Date->timestamp,
                    ],
                    [
                        'ID' => Achievement::CLIENT_WARNING_ID,
                        'When' => Carbon::now()->unix(),
                    ],
                ],
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // player session created
        $playerSession3 = PlayerSession::where([
            'user_id' => $this->user->id,
            'game_id' => $game->id,
        ])->orderByDesc('id')->first();
        $this->assertModelExists($playerSession3);
        $this->assertNotEquals($playerSession->id, $playerSession3->id);
        $this->assertEquals(1, $playerSession3->duration);
        $this->assertEquals('Playing ' . $game->title, $playerSession3->rich_presence);
        $this->assertEquals($this->userAgentOutdated, $playerSession3->user_agent);

        // ----------------------------
        // new session from unsupported emulator
        Carbon::setTestNow($now->addHours(24));
        $this->withHeaders(['User-Agent' => $this->userAgentUnsupported])
            ->get($this->apiUrl('startsession', ['g' => $game->ID, 'm' => $gameHash2->md5]))
            ->assertExactJson([
                'Success' => true,
                'HardcoreUnlocks' => [
                    [
                        'ID' => $achievement1->ID,
                        'When' => $unlock1Date->timestamp,
                    ],
                    [
                        'ID' => $achievement2->ID,
                        'When' => $unlock2Date->timestamp,
                    ],
                ],
                'Unlocks' => [
                    [
                        'ID' => $achievement3->ID,
                        'When' => $unlock3Date->timestamp,
                    ],
                    [
                        'ID' => Achievement::CLIENT_WARNING_ID,
                        'When' => Carbon::now()->unix(),
                    ],
                ],
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // player session created
        $playerSession4 = PlayerSession::where([
            'user_id' => $this->user->id,
            'game_id' => $game->id,
        ])->orderByDesc('id')->first();
        $this->assertModelExists($playerSession4);
        $this->assertNotEquals($playerSession->id, $playerSession4->id);
        $this->assertEquals(1, $playerSession4->duration);
        $this->assertEquals('Playing ' . $game->title, $playerSession4->rich_presence);
        $this->assertEquals($this->userAgentUnsupported, $playerSession4->user_agent);

        // ----------------------------
        // new session from unknown emulator
        Carbon::setTestNow($now->addHours(32));
        $this->withHeaders(['User-Agent' => $this->userAgentUnknown])
            ->get($this->apiUrl('startsession', ['g' => $game->ID, 'm' => $gameHash2->md5]))
            ->assertExactJson([
                'Success' => true,
                'HardcoreUnlocks' => [
                    [
                        'ID' => $achievement1->ID,
                        'When' => $unlock1Date->timestamp,
                    ],
                    [
                        'ID' => $achievement2->ID,
                        'When' => $unlock2Date->timestamp,
                    ],
                ],
                'Unlocks' => [
                    [
                        'ID' => $achievement3->ID,
                        'When' => $unlock3Date->timestamp,
                    ],
                    [
                        'ID' => Achievement::CLIENT_WARNING_ID,
                        'When' => Carbon::now()->unix(),
                    ],
                ],
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // player session created
        $playerSession5 = PlayerSession::where([
            'user_id' => $this->user->id,
            'game_id' => $game->id,
        ])->orderByDesc('id')->first();
        $this->assertModelExists($playerSession5);
        $this->assertNotEquals($playerSession->id, $playerSession5->id);
        $this->assertEquals(1, $playerSession5->duration);
        $this->assertEquals('Playing ' . $game->title, $playerSession5->rich_presence);
        $this->assertEquals($this->userAgentUnknown, $playerSession5->user_agent);
    }

    public function testStartSessionDelegated(): void
    {
        $now = Carbon::create(2020, 3, 4, 16, 40, 13); // 4:40:13pm 4 Mar 2020
        Carbon::setTestNow($now);

        /** @var System $standalonesSystem */
        $standalonesSystem = System::factory()->create(['ID' => 102]);
        /** @var Game $gameOne */
        $gameOne = Game::factory()->create(['ConsoleID' => $standalonesSystem->ID]);

        /** @var User $integrationUser */
        $integrationUser = User::factory()->create(['Permissions' => Permissions::Registered, 'appToken' => Str::random(16)]);
        /** @var User $delegatedUser */
        $delegatedUser = User::factory()->create(['Permissions' => Permissions::Registered, 'appToken' => Str::random(16)]);

        // The integration user is the sole author of all the set's achievements.
        $achievements = Achievement::factory()->published()->count(6)->create(['GameID' => $gameOne->id, 'user_id' => $integrationUser->id]);

        $unlock1Date = $now->clone()->subMinutes(65);
        $this->addHardcoreUnlock($delegatedUser, $achievements->get(0), $unlock1Date);
        $unlock2Date = $now->clone()->subMinutes(22);
        $this->addHardcoreUnlock($delegatedUser, $achievements->get(1), $unlock2Date);
        $unlock3Date = $now->clone()->subMinutes(1);
        $this->addSoftcoreUnlock($delegatedUser, $achievements->get(2), $unlock3Date);

        $this->seedEmulatorUserAgents();

        $params = [
            'u' => $integrationUser->User,
            't' => $integrationUser->appToken,
            'r' => 'startsession',
            'g' => $gameOne->id,
            'k' => $delegatedUser->User,
        ];

        // ----------------------------
        // game with unlocks
        $requestUrl = sprintf('dorequest.php?%s', http_build_query($params));
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->post($requestUrl)
            ->assertExactJson([
                'Success' => true,
                'HardcoreUnlocks' => [
                    [
                        'ID' => $achievements->get(0)->ID,
                        'When' => $unlock1Date->timestamp,
                    ],
                    [
                        'ID' => $achievements->get(1)->ID,
                        'When' => $unlock2Date->timestamp,
                    ],
                ],
                'Unlocks' => [
                    [
                        'ID' => $achievements->get(2)->ID,
                        'When' => $unlock3Date->timestamp,
                    ],
                ],
                'ServerNow' => Carbon::now()->timestamp,
            ]);

        // player session created
        $playerSession = PlayerSession::where([
            'user_id' => $delegatedUser->id,
            'game_id' => $achievements->get(2)->game_id,
        ])->first();
        $this->assertModelExists($playerSession);
        $this->assertEquals(1, $playerSession->duration);
        $this->assertEquals('Playing ' . $gameOne->title, $playerSession->rich_presence);

        $this->assertEquals($gameOne->id, $delegatedUser->LastGameID);
        $this->assertEquals("Playing " . $gameOne->Title, $delegatedUser->RichPresenceMsg);

        // While delegating, updates are made on behalf of username `k`.
        $this->assertDatabaseMissing((new PlayerSession())->getTable(), [
            'user_id' => $integrationUser->id,
            'game_id' => $gameOne->id,
        ]);

        // Next, try to delegate on a non-standalone game.
        // This is not allowed and should fail.
        /** @var System $normalSystem */
        $normalSystem = System::factory()->create(['ID' => 1]);
        /** @var Game $gameTwo */
        $gameTwo = Game::factory()->create(['ConsoleID' => $normalSystem->ID]);

        $params['g'] = $gameTwo->id;

        $requestUrl = sprintf('dorequest.php?%s', http_build_query($params));
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->post($requestUrl)
            ->assertStatus(403)
            ->assertExactJson([
                "Success" => false,
                "Error" => "You do not have permission to do that.",
                "Code" => "access_denied",
                "Status" => 403,
            ]);

        // Next, try to delegate on a game with no achievements authored by the integration user.
        // This is not allowed and should fail.
        /** @var Game $gameThree */
        $gameThree = Game::factory()->create(['ConsoleID' => $standalonesSystem->ID]);
        Achievement::factory()->published()->count(6)->create(['GameID' => $gameThree->id]);
        $params['g'] = $gameThree->id;

        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->post($requestUrl)
            ->assertStatus(403)
            ->assertExactJson([
                "Success" => false,
                "Error" => "You do not have permission to do that.",
                "Code" => "access_denied",
                "Status" => 403,
            ]);

        $params = [
            'u' => $integrationUser->User,
            't' => $integrationUser->appToken,
            'r' => 'startsession',
            'g' => $gameOne->id,
            'k' => $delegatedUser->User,
        ];

        // Next, try a GET call, which should be blocked.
        $requestUrl = sprintf('dorequest.php?%s', http_build_query($params));
        $this->withHeaders(['User-Agent' => $this->userAgentValid])
            ->get($requestUrl)
            ->assertStatus(405)
            ->assertJson([
                "Success" => false,
                "Error" => "Access denied.",
                "Status" => 405,
            ]);
    }
}
