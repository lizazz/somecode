<?php

namespace App\Repositories\Eloquent;

use App\Components\CommonConstants;
use App\Models\Somecode;
use Illuminate\Container\Container as App;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SomeCodeRepository
{
    /**
     * @var string $orderByColumn
     */
    protected $orderByColumn = 'id';

    /**
     * @var string
     */
    public $tableName;

    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * SomeCodeRepository constructor.
     *
     * @param App $app
     * @param SomeCode $someCode
     * @param UserRepository $userRepository
     *
     */
    public function __construct(App $app, SomeCode $someCode, UserRepository $userRepository)
    {
        $this->tableName = $someCode->getTable();
        $this->userRepository = $userRepository;
    }

    /**
     * @var string $orderByDirection
     */
    protected $orderByDirection = 'asc';

    /**
     * @var int $limit
     */

    protected $limit = 100;


    /**
     * Specify Model class name
     *
     * @return mixed
     */

    public function model()
    {
        return SomeCode::class;
    }

    /**
     * {@inheritdoc}
     */
    public function create($data) : SomeCode
    {
        return SomeCode::create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id) : bool
    {
        return SomeCode::where([
            'id' => $id
        ])->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function findByAttr($attributes) : Collection
    {
        return SomeCode::where($attributes)
            ->orderBy($this->tableName . '.' . $this->orderByColumn, $this->orderByDirection)
            ->orderBy($this->tableName . '.id', 'asc')
            ->take($this->limit)
            ->get();
    }

    /**
     * @return mixed
     */
    public function findAll()
    {
        return SomeCode::orderBy($this->orderByColumn, $this->orderByDirection)
            ->take($this->limit)
            ->get();
    }

    /**
     * @param string $orderByColumn
     * @param string $orderByDirection
     */
    public function setOrderBy(string $orderByColumn = 'id', string $orderByDirection = 'asc')
    {
        $this->orderByColumn = $orderByColumn;
        $this->orderByDirection = $orderByDirection;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit = 100)
    {
        $this->limit = $limit;
    }

    /**
     * @param int $id
     * @param array $attributes
     * @return mixed
     */
    public function update(int $id, array $attributes)
    {
        return SomeCode::find($id)
            ->update($attributes);
    }

    /**
     * @param array $attribute
     * @param array $task
     * @return mixed
     */
    public function updateOrCreate(array $attribute, array $task)
    {
        return SomeCode::updateOrCreate($attribute, $task);
    }

    public function getCount()
    {
        $userTableName  = $this->userRepository->tableName;
        $requestBuilder = DB::table($this->tableName)
                            ->join($userTableName, $userTableName . '.id', '=', $this->tableName . '.assigned_by')
                            ->where($userTableName . '.status', (int) true)
                            ->select($this->tableName . '.*')->count();

        return $requestBuilder;
    }

    /**
     * {@inheritdoc}
    */
    public function findOneByAttr($attributes)
    {
        return SomeCode::where($attributes)
            ->orderBy($this->orderByColumn, $this->orderByDirection)
            ->orderBy('id', 'asc')
            ->first();
    }

    /**
     * @param $data
     */
    public function insert($data) : void
    {
        SomeCode::insert($data);
    }

}
