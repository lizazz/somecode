<?php

namespace App\Components;

/**
 * Class CommonConstants
 * @package App\Components
 */
class CommonConstants
{
    /**
     * SomeCOde
     */
    const SOMECODE_NEW_STATUS = 1;
    const SOMECODE_JUNK_STATUS = 2;
    const SOMECODE_ANSWERED_STATUS = 3;

    const SOMECODE_STATUSES = [
        self::SOMECODE_NEW_STATUS       => [
            'name'   => 'new',
            'button' => 'btn-default',
            'id'     => self::SOMECODE_NEW_STATUS
        ],
        self::SOMECODE_JUNK_STATUS      => [
            'name'   => 'junk',
            'button' => 'btn-danger',
            'id'     => self::SOMECODE_JUNK_STATUS
        ]
        self::SOMECODE_ANSWERED_STATUS  => [
            'name'   => 'answered',
            'button' => 'btn-success',
            'id'     => self::SOMECODE_ANSWERED_STATUS
        ]
    ];

    /**
     * grids
     */
    const MIN_SEARCH_LETTERS = 2;
    const DEFAULT_JOBS_ON_PAGE = 25;

    /**
     * API
     */
    const TOKEN_EXPIRED = 3600 * 24 * 30;
}