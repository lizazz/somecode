<?php

namespace App\Services;

use App\Http\Requests\SearchRequest;
use App\Repositories\Eloquent\SomeCodeRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\Collection;
use stdClass;
use App\Components\CommonConstants;
use App\Models\SomeCode;
use Illuminate\Support\Facades\DB;

class SomeCodeService
{
    protected $someCodeRepository;
    protected $userRepository;
    public static $showCount;

    public function __construct(
        SomeCodeRepository $someCodeRepository,
        UserRepository $userRepository
    ) {
        $this->someCodeRepository = $someCodeRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @param $data
     *
     * @return array
     */
    public function save(array $data) :array
    {
        $someCodeTableName = $this->someCodeRepository->tableName;
        $userTableName = $this->userRepository->tableName;
        $response = [];
        $newCodeCounter = 0;

        if (\count($data)) {
            $this->userRepository->setOrderBy('tasks', 'ASC');
            $this->userRepository->setHaving($userTableName .
                '.status = ? AND roles.available_assign_somecode = ?', [1, 1]);

            $managers = $this->userRepository->group(
                $userTableName . '.id',
                $userTableName . '.id, COUNT(' . $someCodeTableName . '.id) as tasks, ' . $userTableName .
                '.status, roles.available_assign_somecode'
            );

            if (!\count($managers)) {
                $response =  [
                   'error' => 'No managers in system'
                ];
            } else {
                $k = 0;
                $i = 0;
                $this->someCodeRepository->setOrderBy('id', 'ASC');
                $managerArrayCount = \count($managers);

                foreach ($data as $someCodeId => $somecode) {
                    $code = $this->someCodeRepository->findOneByAttr(['somecode_id' => $someCodeId]);

                    if ($code instanceof Somecode) {
                        $response[] = [
                            'id'               => $code->id,
                            'code_id'          => $code->somecode_id,
                            'lead_status'      => $code->lead_status,
                            'lead_status_name' => __('message.' .
                                CommonConstants::SOMECODE_STATUSES[$code->lead_status]['name']),
                            'is_new'           => false
                        ];

                    } else {
                        $codeObject = $this->codeObjectToArray($somecode, $$someCodeId, $managers[$k]->id);
                        $code       = $this->someCodeRepository->create($codeObject);

                        $response[] = [
                            'id'               => $code->id,
                            'somecode_id'          => $code->somecode_id,
                            'lead_status'      => $code->lead_status,
                            'lead_status_name' => __('message.' .
                                CommonConstants::SOMECODE_STATUSES[$code->lead_status]['name']),
                            'is_new'           => true
                        ];

                        if ($k === $managerArrayCount - 1) {
                            $k = 0;
                        } else {
                            $k ++;
                        }

                        $newCodeCounter ++;
                    }

                    $i ++;
                }
            }
        }

        return [
            'response' => $response,
            'job_created' => $newCodeCounter
        ];
    }

    /**
     * @param stdClass $task
     * @param string $someCodeId
     * @param int $userId
     * @return array
     */
    private function codeObjectToArray(stdClass $somecode, string $someCodeId, int $userId) :array
    {
        $somecodeArray = (array) $somecode;
        $somecodeArray = array_merge([
                'lead_status' => CommonConstants::SOMECODE_NEW_STATUS,
                'somecode_id' => $someCodeId,
                'assigned_by' => $userId
            ], $somecodeArray);
        $somecodeArray['createdOn'] = strtotime($somecodeArray['createdOn']);
        $somecodeArray['description'] = htmlspecialchars($somecodeArray['description']);
        $somecodeArray['title'] = preg_replace('/  +/', ' ', strip_tags($somecodeArray['title']));

        return $somecodeArray;
    }

    /**
     * @param $keyword
     * @param $userRequest
     * @param $statusRequest
     * @param $fieldSort
     * @param $directionSort
     * @param $perPage
     * @return Collection
     */
    public function getSomeCodes(
        $keyword,
        $userRequest,
        $statusRequest,
        $fieldSort,
        $directionSort,
        $perPage
    ) {
        $someCodeTableName = $this->someCodeRepository->tableName;
        $userTableName = $this->userRepository->tableName;
        $requestBuilder = DB::table($someCodeTableName);
        $requestBuilder->join($userTableName, $userTableName . '.id', '=', $someCodeTableName . '.assigned_by');
        $requestBuilder->where($userTableName . '.status', (int) true);
        $parameters = ['perpage' => $perPage];

        if (empty($keyword) || \strlen($keyword) > CommonConstants::MIN_SEARCH_LETTERS) {
            if (\strlen($keyword) > CommonConstants::MIN_SEARCH_LETTERS) {
                $parameters['keyword'] = $keyword;
                $requestBuilder->where($someCodeTableName . '.title', 'LIKE', '%'. $keyword . '%');
            }

            if ($userRequest > 0) {
                $parameters['filterby'] = $userRequest;
                $requestBuilder->where('assigned_by', $userRequest);
            }

            if ($statusRequest > 0) {
                $parameters['filterbystatus'] = $statusRequest;
                $requestBuilder->where('lead_status', $statusRequest);
            }

                $requestBuilder = $requestBuilder->select($someCodeTableName . '.*');

            if (\strlen($fieldSort) > 0) {
                $parameters['field'] = $fieldSort;
                $parameters['direction'] = $directionSort;
                $requestBuilder->orderBy($fieldSort, $directionSort)->orderBy('id', $directionSort);
            }

            self::$showCount = $requestBuilder->count();
            $someCodes = $requestBuilder->paginate($perPage);
            $someCodes->withPath(route('somecode.index', $parameters));
        } else {
            $someCodes = collect(new SomeCodes());
        }

        return $someCodes;
    }

    /**
     * @param SearchRequest $request
     * @param $someCodeId
     * @return array
     */
    public function getClosestSomeCodes(SearchRequest $request, $someCodeId) :array
    {
        $someCodeTableName = $this->someCodeRepository->tableName;
        $userTableName = $this->userRepository->tableName;
        $closestSomeCodes = [];
        $keyword = $request->input('keyword');
        $userRequest = $request->input('filterby');
        $statusRequest = $request->input('filterbystatus');
        $fieldSort = $request->input('field');
        $directionSort = $request->input('direction');
        $someCode = Somecode::find($someCodeId);
        $requestBuilder = DB::table($someCodeTableName);
        $requestBuilder->join($userTableName, $userTableName .'.id', '=', $someCodeTableName . '.assigned_by');
        $requestBuilder->where($userTableName . '.status', (int) true);

        if ($fieldSort === $userTableName .'.name') {
            $currentValue = $someCode->assignedby->name;
        } else {
            $currentValue = $someCode->$fieldSort;
        }

        if (\strlen($keyword) > CommonConstants::MIN_SEARCH_LETTERS) {
            $requestBuilder->where($someCodeTableName .'.title', 'LIKE', '%'. $keyword . '%');
        }

        if ($userRequest > 0) {
            $requestBuilder->where('assigned_by', $userRequest);
        }

        if ($statusRequest > 0) {
            $requestBuilder->where('lead_status', $statusRequest);
        }

        $reverseDirection = 'desc';
        $sign = '<';
        $reverseSign = '>';

        if ($directionSort == 'desc') {
            $reverseDirection = 'asc';
            $sign = '>';
            $reverseSign = '<';
        }

        $requestPreviousBuilder = clone $requestBuilder;
        $requestNextBuilder = clone $requestBuilder;

        if ($fieldSort == 'id') {
            $requestPreviousBuilder->where($someCodeTableName . '.id', $sign, $currentValue);
            $requestNextBuilder->where($someCodeTableName . '.id', $reverseSign, $currentValue);
        } else {
            $requestPreviousBuilder->where(
                function ($query) use ($someCodeTableName, $fieldSort, $someCode, $sign, $currentValue) {
                    $query->where(
                        function ($query) use ($someCodeTableName, $fieldSort, $someCode, $sign, $currentValue) {
                            $query->where($fieldSort, $currentValue)
                                  ->where($someCodeTableName . '.id', $sign, $someCode->id);
                        }
                    )->orWhere($fieldSort, $sign, $currentValue);
                }
            );
            $requestNextBuilder->where(
                function ($query) use ($someCodeTableName, $fieldSort, $someCode, $reverseSign, $currentValue) {
                    $query->where(
                        function ($query) use ($someCodeTableName, $fieldSort, $someCode, $reverseSign, $currentValue) {
                            $query->where($fieldSort, $currentValue)
                                  ->where($someCodeTableName . '.id', $reverseSign, $someCode->id);
                        }
                    )->orWhere($fieldSort, $reverseSign, $currentValue);
                }
            );
        }

        if (\strlen($fieldSort) > 0 && $fieldSort != 'id') {
            $requestPreviousBuilder->orderBy($fieldSort, $reverseDirection)
                                   ->orderBy($someCodeTableName . '.id', $reverseDirection);
        } elseif (\strlen($fieldSort) > 0 && $fieldSort == 'id') {
            $requestPreviousBuilder->orderBy($someCodeTableName .'.'. $fieldSort, $reverseDirection);
        }

        $previous = $requestPreviousBuilder->select($someCodeTableName . '.*')->limit(1)->first();

        if (\strlen($fieldSort) > 0 && $fieldSort !== 'id') {
            $requestNextBuilder->orderBy($fieldSort, $directionSort)->orderBy($someCodeTableName . '.id', $directionSort);
        } elseif (\strlen($fieldSort) > 0 && $fieldSort === 'id') {
            $requestNextBuilder->orderBy($someCodeTableName . '.' .$fieldSort, $directionSort);
        }

        $next = $requestNextBuilder->select($someCodeTableName . '.*')->limit(1)->first();

        if (!empty($previous)) {
            $closestSomeCode['previousCodeLink'] = [
                'somecode' => $previous->id,
                'keyword' => $keyword,
                'filterby' => $userRequest,
                'filterbystatus' => $statusRequest,
                'field' => $fieldSort,
                'direction' => $directionSort
            ];
        }

        if (!empty($next)) {
            $closestSomeCode['nextCodeLink'] = [
                'somecode' => $next->id,
                'keyword' => $keyword,
                'filterby' => $userRequest,
                'filterbystatus' => $statusRequest,
                'field' => $fieldSort,
                'direction' => $directionSort
            ];
        }

        return $closestSomeCode;
    }

    /**
    * @param $days
    * @return array
    */
    public function delete($days)
    {
        if ($days == 0) {
            $response = ['error' => 'Day must be more then 0'];
        } else {
            $this->someCodeRepository->setLimit(10000);
            $someCodes = $this->someCodeRepository->findByAttr([
                 [
                   'created_at',
                   '>',
                    date('Y-m-d H:i:s', time() - ($days * 86400))
                 ]
            ]);

            $countCodes = \count($someCodes);

            if ($countCodes) {
                foreach ($someCodes as $code) {
                    $this->someCodeRepository->delete($somecode->id);
                }
            }

            $response = ['success' => serialize($somecode), 'count' => $countCodes];
        }

        return $response;
    }

    /**
     * @param $quantity
     *
     * @return array
     */
    public function createTestCodes($quantity) :array
    {
        if (empty($quantity)) {
            return ['error' => 'Quantity must be more then 0'];
        }

        $codes = [];

        $baseCode = substr(md5(microtime()), rand(0, 26), 5);

        $codeTemplate = [
            "createdOn"   => time(),
            "description" => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam facilisis lacus elit,' .
                             'vel vestibulum urna auctor sed. Aliquam ac tincidunt lorem, et volutpat lacus. Integer' .
                             'auctor felis risus, in elementum ex dignissim nec. Nam sollicitudin ullamcorper ante, ' .
                             'eu sodales sem ultrices sed. Duis nec nunc sit amet ex bibendum blandit. Donec quis' .
                             ' vestibulum lorem. Nunc vehicula urna vitae posuere accumsan.' .
                             'Ut sed ultricies elit. Sed consequat elit ut eros fermentum, at porta velit condimentum' .
                             'Vestibulum volutpat velit vitae augue malesuada, vel porttitor libero tincidunt. ' .
                             'Maecenas eu justo sit amet ex euismod blandit id non sapien. Duis suscipit sapien ' .
                             'eget bibendum sollicitudin. Class aptent taciti sociosqu ad litora torquent per conubia' .
                             ' nostra, per inceptos himenaeos. Morbi mauris urna, interdum vitae ipsum id, auctor' .
                             ' tempor nunc. Etiam dolor arcu, convallis sit amet libero id, aliquam congue mi.' .
                             'Vestibulum tempus condimentum nulla, vel fermentum ipsum ultrices non. Interdum et ' .
                             'malesuada fames ac ante ipsum primis in faucibus. Ut rutrum erat ut ligula sagittis ' .
                             'porta nec ut augue. Sed aliquet a erat vitae vulputate. Maecenas sed eros aliquet, ' .
                             'facilisis nisl maximus, facilisis metus. Integer et mi lacus. Maecenas vestibulum ' .
                             'dapibus gravida. Mauris finibus lacinia egestas. Suspendisse bibendum vitae nunc non' .
                             ' fermentum. Maecenas vitae sodales nisi. Duis vulputate nibh id sapien egestas, nec ' .
                             'varius nibh tempor. Donec placerat arcu ex, sit amet porta metus mollis in. Sed blandit' .
                             ' posuere purus, a vestibulum tellus lobortis quis.',
            "title"       => "Title ",
            'assigned_by' => 1,
            'lead_status' => CommonConstants::SOMECODE_NEW_STATUS

        ];

        $k = 0;

        for ($i = 0; $i < $quantity; $i++) {
            $codes                  = $jobTemplate;
            $codes['title']         .= $i;
            $codes['somecode_id']       = $baseCode . $i;
            $codes[$baseCode . $i] = $job;

            if ($k === 1000) {
                $k = 0;
                $this->someCodeRepository->insert($codes);
                $jobs = [];
            } else {
                $k++;
            }
        }

        $this->someCodeRepository->insert($codes);

        return ['count' => $quantity, 'success' => true];
    }
}
