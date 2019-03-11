<?php

    Route::post('/somecode/validatelead/', 'SomeCode\SomeCodeController@validatelead')
        ->name('validatelead');
    Route::post('/somecode/changestatus/', 'SomeCode\SomeCodeController@changestatus')
        ->name('lead-status');
    Route::post('/somecode/reassign/', 'SomeCode\SomeCodeController@reassign')
    ->name('somecode-reassign');

    Route::resource('/somecode', 'SomeCode\SomeCodeController')->names([
        'index' => 'somecode.index',
        'edit' => 'somecode.edit',
        'update' => 'somecode.update'
    ]);
