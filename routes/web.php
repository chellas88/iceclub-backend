<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/test', [\App\Http\Controllers\WidgetController::class, 'test']);

Route::post('/amo/widget', [\App\Http\Controllers\WidgetController::class, 'widget']);
Route::get('/amo/widget', [\App\Http\Controllers\WidgetController::class, 'widget']);
Route::get('/amo/auth', [\App\Http\Controllers\WidgetController::class, 'getAmoToken']);
Route::get('/amo/btn', [\App\Http\Controllers\WidgetController::class, 'amoAuth']);
Route::get('/amo/info', [\App\Http\Controllers\WidgetController::class, 'info']);

Route::get('/alfa/get_teachers', [\App\Http\Controllers\WidgetController::class, 'getTeachers']);
Route::get('/alfa/get_lessons', [\App\Http\Controllers\WidgetController::class, 'getLessons']);
Route::get('/dev', [\App\Http\Controllers\WidgetController::class, 'dev']);

Route::post('/alfa/hook', [\App\Http\Controllers\AmoAlfaController::class, 'alfaHook']);
Route::post('/amo/hook', [\App\Http\Controllers\AmoAlfaController::class, 'amoHook']);
Route::get('/alfa/dev', [\App\Http\Controllers\AmoAlfaController::class, 'dev']);
