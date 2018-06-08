<?php

use Dingo\Api\Routing\Router;

/** @var Router $api */
$api = app(Router::class);

$api->version('v1', function (Router $api) {
    $api->group(['prefix' => 'auth'], function(Router $api) {
        $api->post('signup', 'App\\Api\\V1\\Controllers\\SignUpController@signUp');
        $api->post('login', 'App\\Api\\V1\\Controllers\\LoginController@login');

        $api->post('recovery', 'App\\Api\\V1\\Controllers\\ForgotPasswordController@sendResetEmail');
        $api->post('reset', 'App\\Api\\V1\\Controllers\\ResetPasswordController@resetPassword');
    });

    $api->group(['middleware' => 'jwt.auth'], function(Router $api) {
        $api->get('protected', function() {
            return response()->json([
                'message' => 'Access to protected resources granted! You are seeing this text as you provided the token correctly.'
            ]);
        });

        $api->get('refresh', [
            'middleware' => 'jwt.refresh',
            function() {
                return response()->json([
                    'message' => 'By accessing this endpoint, you can refresh your access token at each request. Check out this response headers!'
                ]);
            }
        ]);
    });

    $api->get('hello', function() {
        return response()->json([
            'message' => 'This is a simple example of item returned by your APIs. Everyone can see it.'
        ]);
    });

    $api->group(['prefix' => 'models'], function(Router $api){
        $api->get('/', 'App\\Api\\V1\\Controllers\\ModelController@index' );
        // $api->get('/all', 'App\\Api\\V1\\Controllers\\ModelController@all' );
        $api->post('/', 'App\\Api\\V1\\Controllers\\ModelController@store' );
        $api->post('/upload', 'App\\Api\\V1\\Controllers\\ModelController@upload' );
        $api->post('/process', 'App\\Api\\V1\\Controllers\\ModelController@process' );
        $api->put('/{id}', 'App\\Api\\V1\\Controllers\\ModelController@update' );
        $api->delete('/{id}', 'App\\Api\\V1\\Controllers\\ModelController@delete' );
        $api->get('/{id}', 'App\\Api\\V1\\Controllers\\ModelController@show' );
    });

    $api->group(['prefix' => 'schedules'], function(Router $api){
        $api->get('/', 'App\\Api\\V1\\Controllers\\ScheduleController@index' );
        $api->get('/dates', 'App\\Api\\V1\\Controllers\\ScheduleController@dates' );
        $api->post('/', 'App\\Api\\V1\\Controllers\\ScheduleController@store' );
        $api->post('/upload', 'App\\Api\\V1\\Controllers\\ScheduleController@upload' );
        $api->put('/{id}', 'App\\Api\\V1\\Controllers\\ScheduleController@update' );
        $api->delete('/{id}', 'App\\Api\\V1\\Controllers\\ScheduleController@delete' );
        $api->get('/{id}', 'App\\Api\\V1\\Controllers\\ScheduleController@show' );
    });    

    $api->group(['prefix' => 'schedule_details'], function(Router $api){
        $api->get('/', 'App\\Api\\V1\\Controllers\\ScheduleDetailController@index' );
        $api->get('/preprocess', 'App\\Api\\V1\\Controllers\\ScheduleDetailController@preprocess' );
        $api->get('/download/{id}', 'App\\Api\\V1\\Controllers\\ScheduleDetailController@download' );

        $api->post('/process', 'App\\Api\\V1\\Controllers\\ScheduleDetailController@process' );
        $api->post('/upload', 'App\\Api\\V1\\Controllers\\ScheduleDetailController@upload' );

    });

    $api->group(['prefix' => 'histories'], function(Router $api){
        $api->get('/', 'App\\Api\\V1\\Controllers\\HistoryController@index' );
        // $api->post('/process', 'App\\Api\\V1\\Controllers\\HistoryController@process' );
    });

});
