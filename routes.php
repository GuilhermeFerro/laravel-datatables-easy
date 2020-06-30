<?php

//Route::group(["middleware" => "auth"], function() {
    Route::match(['get','post'],'datatablesEasy', ['as' => 'datatablesEasy', 'uses' => '\DatatablesEasy\controller\DatatablesEasyController@page']);
//});
