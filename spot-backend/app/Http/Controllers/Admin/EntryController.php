<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\EntryService;
use App\Models\MarginContest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EntryController extends AppBaseController
{
    protected $entryService;

    public function __construct(EntryService $entryService)
    {
        $this->entryService = $entryService;
    }

    public function createContest(Request $request)
    {
        try {
            if (MarginContest::where('name', $request->get('name'))->first()) {
                throw new HttpException(422, 'This contest is existed!');
            }
            $data = $this->entryService->createContest($request->all());
            return $this->sendResponse($data);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function stopContest(Request $request)
    {
        $marginContest = MarginContest::find($request->get('id'));
        if ($marginContest->status !== 'started') {
            throw new HttpException(422, 'Can not stop contest that is not started.');
        }
        try {
            $this->entryService->stopContest($request->get('id'));
            return $this->sendResponse('success');
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function startContest(Request $request)
    {
        if (MarginContest::find($request->get('id'))->status !== 'draft') {
            throw new HttpException(422, 'This contest is already started or closed.');
        }
        if (MarginContest::where('status', 'started')->first()) {
            throw new HttpException(422, 'There is another contest is starting.');
        }
        try {
            $this->entryService->startContest($request->get('id'));
            return $this->sendResponse('success');
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function editContest(Request $request)
    {
        $marginContest = MarginContest::find($request->get('id'));
        if (MarginContest::where('id', '!=', $request->get('id'))->where('name', $request->get('name'))->first()) {
            throw new HttpException(422, 'This contest already exist');
        }
        try {
            if ($marginContest->status === 'draft') {
                $this->entryService->updateContest($request->all(), $marginContest);
            } else {
//                if (intval($request->get('number_of_users',0)) < $marginContest->real_number_of_users) {
//                    throw new HttpException(422, 'The number of users (UI) must be greater than or equal to real number of users.');
//                }
                $this->entryService->updateContest(['number_of_users' => $request->get('number_of_users', 0)], $marginContest);
            }
            return $this->sendResponse('success');
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getEntryTeamMember(Request $request)
    {
        try {
            $data = $this->entryService->getEntryTeamMember($request->all());
            return $this->sendResponse($data);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getMyTeamInfor(Request $request)
    {
        try {
            $data = $this->entryService->getMyTeamInfor();
            return $this->sendResponse($data);
        } catch (HttpException $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }
}
