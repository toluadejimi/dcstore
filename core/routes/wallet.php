<?php

use Illuminate\Support\Facades\Route;

Route::get('flutterwave/{trx}/{type}', 'Flutterwave\ProcessController@walletipn')->name('Flutterwave');