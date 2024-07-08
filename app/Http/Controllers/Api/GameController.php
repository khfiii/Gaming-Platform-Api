<?php

namespace App\Http\Controllers\Api;

use App\Models\Game;
use App\Models\Scor;
use App\Models\User;
use App\Models\GameVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Database\Query\Builder;

class GameController extends Controller
{

    public function index(Request $request)
    {
        $page = $request->query('page', 0);
        $size = $request->query('size', 10);
        $sortBy = $request->query('sortBy', 'title');
        $sortDir = $request->query('sortDir', 'asc');

        $baseQuery = Game::query();

        if ($sortBy === 'popular') {
            $baseQuery->withCount(['versions as total_scores' => function (Builder $query) {
                $query->select(DB::raw('SUM(scores.score)'));
                $query->join('scores', 'game_versions.id', '=', 'scores.game_version_id');
            }])->orderBy('total_scores', $sortDir);
        } elseif ($sortBy === 'uploaddate') {
            $baseQuery->with(['versions' => function (Builder $query) use ($sortDir) {
                $query->orderBy('created_at', $sortDir);
            }]);
        } else {
            $baseQuery->orderBy($sortBy, $sortDir);
        }

        $games = $baseQuery->with('user', 'versions')->paginate($size, ['*'], 'page', $page + 1);

        $totalElements = $games->total();

        return response()->json([
            'page' => $page,
            'size' => $size,
            'totalElements' => $totalElements,
            'content' => $games->map(function ($game) {

                $latestVersion = $game->versions->sortByDesc('created_at')->first();
                $scoreCount = $game->total_scores ?? 0;

                return [
                    'slug' => $game->slug,
                    'title' => $game->title,
                    'description' => $game->description,
                    'uploadTimestamp' => $latestVersion ? $latestVersion->created_at : null,
                    'author' => $game->user ? $game->user->username : null,
                    'scoreCount' => $scoreCount,
                    'thumbnail' => $latestVersion ? $latestVersion->thumbnail : null,
                ];
            })
        ], 200);
    }


    public function getUserDetail(Request $request, string $username)
    {

        $user = User::with(['games' => function (Builder $query) {
            $query->with('versions', function (Builder $query) {
                $query->withSum('scores', 'score')->orderBy('scores_sum_score', 'desc');
            });
        }])->where('username', $username)->first();


        $response = [
            'username' => $user->username,
            'registerTimestamp' => $user->created_at,
            'authorGames' => [],
            'highScores' => []
        ];

        if ($user->games->isNotEmpty()) {
            foreach ($user->games as $game) {
                $response['authorGames'][] = [
                    'slug' => $game->slug,
                    'title' => $game->title,
                    'description' => $game->description
                ];

                foreach ($user->games as $game) {
                    foreach ($game->versions as $version) {
                        $response['highScores'][] = [
                            'game' => [
                                'slug' => $game->slug,
                                'title' => $game->title,
                                'description' => $game->description
                            ],
                            'score' => $version->scores_sum_score,
                            'timestamp' => $version->created_at
                        ];
                    }
                }
            }
        }


        return response()->json($response, 200);
    }

    public function create(Request $request)
    {

        $credentials = $request->only('title', 'description');

        $generateSlug = Str::slug($request->title);

        $credentials = array_merge($credentials, ['slug' => $generateSlug, 'created_by' => $request->user()->id]);

        $validate = Validator::make($credentials, [
            'title' => 'required|min:3|max:60',
            'slug' => 'unique:games,slug',
            'description' => 'required|min:0|max:200',
        ]);


        if ($validate->fails()) {

            $message = $validate->errors();

            if ($message->has('slug')) {
                return response()->json([
                    "status" => "invalid",
                    "slug" => "Game title already exists"
                ], 400);
            }


            $violations = [];

            foreach ($message->toArray() as $key => $error) {
                $violations[$key] = [
                    'message' => implode(',', $error)
                ];
            }

            return response()->json([
                "status" => 'invalid',
                "message" => "Request body is not valid.",
                "violations" => $violations

            ], 400);
        }

        Game::create($credentials);

        return response()->json([
            "status" => "success",
            "slug" => "generated-game-slug"
        ], 201);
    }

    public function detailGame(Game $game)
    {
        $sumScores = [];
        $gamePath = '';

        foreach ($game->versions as $version) {
            $sumScores[] = $version->scores->sum('score');
            $gamePath = $version->storage_path;
        }

        return response()->json([
            'slug' => $game->slug,
            'title' => $game->title,
            'description' => $game->description,
            'uploadTimestamp' => $game->created_at,
            'author' => $game->user->username,
            'scoresCount' => array_sum($sumScores),
            'gamePath' => $gamePath
        ], 200);
    }

    public function uploadGame(Request $request, Game $game)
    {

        $credentials = $request->only('token', 'zipfile');

        $validate = Validator::make($credentials, [
            'token' => 'required',
            'zipfile' => 'required|file|mimes:zip'
        ]);

        if ($validate->fails()) {
            $violations = [];

            foreach ($validate->errors()->toArray() as $key => $error) {
                $violations[$key] = [
                    'message' => implode(',', $error)
                ];
            }

            return response()->json([
                "status" => 'invalid',
                "message" => "Request body is not valid.",
                "violations" => $violations

            ], 400);
        }

        if ($request->user()->id !== $game->created_by) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the game author'
            ], 403);
        }

        $newVersion = '';

        foreach ($game->versions as $version) {
            $newVersion = 'v' . $version->id + 1;
        }

        $filePath = "games/{$game->id}/{$newVersion}/";

        GameVersion::create([
            'game_id' => $game->id,
            'version' => $newVersion,
            'storage_path' => $filePath
        ]);

        return response(['message' => 'Game uploaded successfully'], status: 201);
    }

    public function updateGame(Request $request, Game $game)
    {
        if ($request->user()->id !== $game->created_by) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the game author'
            ], 403);
        }

        $credentials = $request->only('title', 'description');

        $game->update($credentials);

        return response()->json([
            'status' => 'success'
        ], 200);
    }

    public function deleteGame(Request $request, Game $game)
    {
        if ($request->user()->id !== $game->created_by) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the game author'
            ], 403);
        }

        $game->delete();

        return response()->json(status: 204);
    }

    public function getScores(Game $game)
    {

        $allScores = collect();
        foreach ($game->versions as $version) {
            $allScores = $allScores->merge($version->scores);
        }

        $highestScores = $allScores->groupBy('user_id')->map(function ($scores) {
            return $scores->sortByDesc('score')->first();
        });

        $highestScores = $highestScores->sortByDesc('score')->values()->all();

        $formattedScores['scores'] = [];

        foreach ($highestScores as $score) {
            $user = User::find($score->user_id);
            $formattedS = [
                'username' => $user->username,
                'score' => $score->score,
                'timestamp' => $score->created_at
            ];

            array_push($formattedScores['scores'], $formattedS);
        }

        return response()->json($formattedScores, 200);
    }

    public function postScores(Request $request, Game $game)
    {

        $credentials = $request->only('score');

        $validate = Validator::make($credentials, [
            'score' => 'required',
        ]);

        if ($validate->fails()) {
            $violations = [];

            foreach ($validate->errors()->toArray() as $key => $error) {
                $violations[$key] = [
                    'message' => implode(',', $error)
                ];
            }

            return response()->json([
                "status" => 'invalid',
                "message" => "Request body is not valid.",
                "violations" => $violations

            ], 400);
        }


        $data = array_merge([
            'user_id' => $request->user()->id,
            'game_version_id' => $game->id,
        ], $credentials);

        Scor::create($data);

        return response()->json([
            'message' => 'success'
        ], 201);
    }
}
