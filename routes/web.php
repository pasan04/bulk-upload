<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', 'ImportExport@importExportView');
Route::get('downloadExcel/{type}', 'ImportExport@export');
Route::post('importExcel', 'ImportExport@import');

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
