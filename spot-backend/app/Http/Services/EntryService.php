<?php


namespace App\Http\Services;

use App\Consts;
use App\Events\MarginEntryUpdated;
use App\Models\MarginContest;
use App\Models\MarginEntry;
use App\Models\MarginEntryLeaderBoard;
use App\Models\MarginEntryTeamLeaderboard;
use App\Service\Margin\MarginLeaderboardService;
use App\Service\Margin\Utils;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EntryService
{
    private MarginLeaderboardService $marginLeaderboardService;

    public function __construct(MarginLeaderboardService $marginLeaderboardService)
    {
        $this->marginLeaderboardService = $marginLeaderboardService;
    }

    public function getEntryTeamMember($params): bool
    {
        $contest = MarginContest::where('status', Consts::MARGIN_STARTED)->first();
        if (!$contest) {
            return false;
        }
        $entry = MarginEntry::where('contest_id', $contest->id)
            ->where('user_id', Auth::id())
            ->first();
        if (!$entry) {
            return false;
        }
        $query = MarginEntry::select('margin_entry.*', 'margin_entry_leaderboard_volume.total_volume', 'margin_entry_leaderboard.roe')
            ->leftJoin('margin_entry_leaderboard_volume', function ($join) {
                $join->on('margin_entry.account_id', '=', 'margin_entry_leaderboard_volume.account_id');
                $join->on('margin_entry.contest_id', '=', 'margin_entry_leaderboard_volume.contest_id');
            })
            ->leftJoin('margin_entry_leaderboard', function ($join) {
                $join->on('margin_entry.account_id', '=', 'margin_entry_leaderboard.account_id');
                $join->on('margin_entry.contest_id', '=', 'margin_entry_leaderboard.contest_id');
            })
            ->where('status', Consts::ENTRY_JOINED)
            ->where('margin_entry.team_id', $entry->team_id);
        return Utils::applyCriterions($query, $params)
            ->when(!request('sort'), function ($query) {
                $query->getQuery()->orders = null;
                $query->orderBy('margin_entry_leaderboard.roe', 'desc');
            })
            ->paginate($params['limit']);
    }

    public function getEntryTeam($param): array
    {
        $contest = MarginContest::find(Arr::get($param, 'contest_id'));
        if (!$contest) {
            return [];
        }

        $sumROEquerry = '(select sum(mel.roe) from margin_entry_leaderboard as mel
                        join margin_entry as me on(mel.account_id = me.account_id and mel.contest_id = me.contest_id)
                        where me.status = "joined" and mel.team_id = margin_entry_team_leaderboards.id) as sum_roe';

        return Utils::applyCriterions(MarginEntryTeamLeaderboard::addSelect('*', DB::raw($sumROEquerry))
            ->where('contest_id', $contest->id), $param)
            ->with('leader:email,id')
            ->withCount('numberEntry')
            ->paginate($param['limit']);
    }

    public function getMyTeamInfor(): bool
    {
        $contest = MarginContest::where('status', Consts::MARGIN_STARTED)->first();
        if (!$contest) {
            return false;
        }

        $entry = MarginEntry::where('contest_id', $contest->id)
            ->where('user_id', Auth::id())
            ->with('team.leader:email,id')
            ->first();

        $now = Carbon::now()->toDateTimeString();
        if ($now >= $contest->start_contest_time) {
            $entryTop = $this->marginLeaderboardService->getAllEntryLeaderboard($contest);
            if ($contest->is_team_battle) {
                $entry->sum_roe = MarginEntryLeaderBoard::leftJoin('margin_entry', function ($join) {
                    $join->on('margin_entry.contest_id', '=', 'margin_entry_leaderboard.contest_id');
                    $join->on('margin_entry.account_id', '=', 'margin_entry_leaderboard.account_id');
                })->where('status', Consts::ENTRY_JOINED)
                    ->where('margin_entry_leaderboard.team_id', $entry->team_id)
                    ->where('margin_entry_leaderboard.contest_id', $entry->contest_id)
                    ->sum('margin_entry_leaderboard.roe');
                $rank = $entryTop->search(function ($team) use ($entry) {
                    return $entry->team_id == $team->id;
                });
            } else {
                $entry->roe = MarginEntryLeaderBoard::where('contest_id', $contest->id)
                    ->where('account_id', $entry->account_id)
                    ->first()
                    ->roe;
                $rank = $entryTop->search(function ($leaderboard) use ($entry) {
                    return $entry->id == $leaderboard->id;
                });
            }
            $entry->rank = is_numeric($rank) ? $rank + 1 : $rank;
        }


        return $entry;
    }

    public function createContest($params)
    {
        $params['minimum_member'] = Arr::get($params, 'minimum_member', 0);
        $params['minimum_volume'] = Arr::get($params, 'minimum_volume', 0);
        return MarginContest::create($params);
    }

    public function stopContest($id)
    {
        return MarginContest::find($id)->update([
            'status' => 'closed',
            'closed_at' => Carbon::now(),
        ]);
    }

    public function startContest($id): void
    {
        $contest = MarginContest::find($id);
        $endEntryTime = Carbon::parse($contest->end_entry_time);
        if (Carbon::now()->gt($endEntryTime)) {
            $status = 'closed';
        } else {
            $status = 'started';
            event(new MarginEntryUpdated());
        }
        MarginContest::find($id)->update([
            'status' => $status
        ]);
    }

    public function updateContest($data, $marginContest)
    {
        return $marginContest->update($data);
    }
}
