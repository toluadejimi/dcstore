<?php

use App\Http\Controllers\Api\Authcontroller;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\UserController;


Route::any('e-fund',  'User\UserController@e_fund')->name('e-fund');
Route::any('verify',  'User\UserController@verify_username')->name('e-check');
