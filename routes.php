<?php

// Route::get('/api/salesforce/authenticate', function () {
//     Forrest::authenticate();
//     return \Redirect::to('/backend/waka/salesforce/logsfs');
// });

// ROUTES POUR AUTHENTIFICATION CLASSIQUE SANS JWT
Route::group(['middleware' => ['web']], function () {
    Route::get('/api/authenticate', function () {
        return Forrest::authenticate();
    });
    Route::get('/api/callback', function () {
        Forrest::callback();
        //_log('account');
        //trace_log(Forrest::resources());
        return \Redirect::to('/backend/waka/salesforce/logsfs');
    });
});
