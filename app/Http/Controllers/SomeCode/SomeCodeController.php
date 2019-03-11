<?php

namespace App\Http\Controllers\SomeCode;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSomeCode;
use App\Http\Requests\SearchRequest;
use App\Http\Requests\ValidateLeadRequest;
use App\Models\SomeCode;
use App\Repositories\Eloquent\SomeCodeRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\SomeCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\ChangeLeadStatusRequest;
use App\Components\CommonConstants;

class SomeCodeController extends Controller
{
    protected $userRepository;
    protected $someCodeService;
    protected $someCodeRepository;

    public function __construct(
        UserRepository $userRepository,
        SomeCodeService $someCodeService,
        SomeCodeRepository $someCodeRepository
    ) {
        $this->userRepository = $userRepository;
        $this->someCodeService = $someCodeService;
        $this->someCodeRepository = $someCodeRepository;
    }

    /**
     * @param SearchRequest $request
     * @return \Illuminate\Contracts\View\Factory|View
     */
    public function index(SearchRequest $request)
    {
        $request->validated();
        $keyword = $request->input('keyword');
        $userRequest = $request->input('filterby');
        $statusRequest = $request->input('filterbystatus');
        $fieldSort = $request->input('field', 'id');
        $directionSort = $request->input('direction', 'asc');
        $paginate = $request->input('paginate');
        $perPage = $request->input('perpage', CommonConstants::DEFAULT_JOBS_ON_PAGE);
        $userCollection = $this->userRepository->findByAttr(['status' => (int) true]);
        $users = [['name' => __('message.all_assignees'), 'id' => null]];
        $statuses = array_merge([['name' => 'all_statuses', 'id' => null]], CommonConstants::SOMECODE_STATUSES);
        $userTable = $this->userRepository->tableName;

        foreach ($userCollection as $user) {
            $users[$user->id] = ['id' => $user->id, 'name' => $user->name];
        }

        $somecodes = $this->someCodeService->getSomeCodes(
            $keyword,
            $userRequest,
            $statusRequest,
            $fieldSort,
            $directionSort,
            $perPage
        );
        $totalCount = $this->someCodeRepository->getCount();
        $showCount = SomeCodeService::$showCount;

        return view('somecode.list', compact(
            'somecode',
            'keyword',
            'users',
            'userRequest',
            'fieldSort',
            'directionSort',
            'paginate',
            'perPage',
            'totalCount',
            'showCount',
            'statuses',
            'statusRequest',
            'userTable'
        ));
    }


    /**
     * Save Somecodes
     * @SWG\Post(
     *     path="/somecodes",
     *     operationId="Save",
     *     tags={"Somecode save"},
     *     summary="Save Somecode",
     *     description="Save Somecode",
     *     @SWG\Parameter(
     *         name="api_token",
     *         in="query",
     *         required=true,
     *         description="Authorization token",
     *         type="string",
     *         @SWG\Schema(
     *             type="string",
     *             example="S0ZIPSiN4E7h9T6JrZkFu3DIuoBuUUj19EQxcPAPZUfGbSl9BacFg9EkyZFV"
     *         )
     *     ),
     *     @SWG\Parameter(
     *         name="data",
     *         in="query",
     *         required=false,
     *         description="String with valid Json",
     *          type="string",
     *         @SWG\Schema(
     *             type="string",
     *         )
     *     ),
     *     @SWG\Response(
     *         response=201,
     *         description="{'response':[{'id':4,'somecode_id':'0107b57357ecbb9ee7','lead_status':1,'lead_status_name':'New', 'marker': false},{'id':5,'somecode_id':'01d9b7f536c4fc9554','lead_status':1,'lead_status_name':'New', 'marker': true}],'somecode_created':2,'token_validation':'Ok','code':201}"
     *     ),
     *     @SWG\Response(
     *         response=401,
     *         description="{ 'error':'Token is not valid. Relogin, please', 'code':401}"
     *     ),
     *     @SWG\Response(
     *         response=403,
     *         description="{"token_validation": "Ok", "code": 403, "response": { "error": "No managers in system"}, "somecode_created": 0}"
     *     ),
     *     @SWG\Response(
     *         response=400,
     *         description="{"data": {"error": "Json isn't valid", "code": 400, "login": "true"}}"
     *     )
     * )
     * @param CreateSomeCode $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function save(CreateSomeCode $request) :JsonResponse
    {
        $users = $this->userRepository->findByAttr([
            ['api_token', '=', $request->input('api_token')],
            ['updated_at', '>', date('Y-m-d H:i:s', time() - CommonConstants::TOKEN_EXPIRED)]
        ]);

        if (\count($users) === 0) {
            $response = ['response' => [
                'error' => __('message.token_not_valid'),
                'code' => '401'
                ]
            ];
            $code = 401;
        } else {
            $data = (array) json_decode($request->input('data'));
            $response = $this->someCodeService->save($data);
            $code = 201;

            if (isset($response['response']['error'])) {
                $code = 403;
            }

            $response = array_merge([
                'token_validation' => __('message.ok'),
                'code'             => $code
            ], $response);
        }

        return response()->json($response, $code);
    }

    /**
     * @param SearchRequest $request
     * @param int $someCodeId
     * @return \Illuminate\Contracts\View\Factory|View
     */
    public function edit(SearchRequest $request, int $someCodeId)
    {
        $request->validated();
        $this->someCodeRepository->setLimit(1);
        $someCode = $this->someCodeRepository->findOneByAttr(['id' => $someCodeId]);

        if (!$someCode instanceof SomeCode) {
            abort(404);
        }

        $closestSomeCode = $this->someCodeService->getClosestSomeCodes($request, $someCode->id);

        $userCollection = $this->userRepository->findByAttr(['status' => (int) true]);
        $users = [
            $someCode->assigned_by => [
                'name' => $someCode->assignedby->name,
                'selected' => true,
                'disabled' => true
            ]
        ];

        foreach ($userCollection as $user) {
            $users[$user->id] = [
                'name' => $user->name,
                'selected' => false,
                'disabled' => false
            ];
        }

        return view('somecode.show', compact('someCode', 'users', 'closestSomeCode'));
    }

    /**
     * @param ValidateLeadRequest $request
     */
    public function validatelead(ValidateLeadRequest $request)
    {
        $workId = $request->input('someCode_id');
        $attributes = ['assigned_by' => Auth::id()];
        $this->someCodeRepository->update($someCodeId, $attributes);
    }

    /**
     * @param ChangeLeadStatusRequest $request
     */
    public function changestatus(ChangeLeadStatusRequest $request)
    {
        $someCodeId = $request->input('somecode_Id');
        $attributes = ['lead_status' => $request->input('status_id')];
        $this->someCodekRepository->update($someCodeId, $attributes);
        session()->flash('statusmessage', __('message.status_was_updated'));
    }

    /**
     * @param ValidateLeadRequest $request
     */
    public function reassign(ValidateLeadRequest $request)
    {
        $someCodeId = $request->input('somecode_Id');
        $userId = $request->input('user_id');

        if ($someCodeId > 0 && $userId > 0) {
            $attributes = ['assigned_by' => $userId];
            $this->someCodeRepository->update($someCodeId, $attributes);
        }
        session()->flash('usermessage', __('message.assignee_was_updated'));
    }
}
