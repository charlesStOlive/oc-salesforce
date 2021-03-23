<?php

Route::get('/api/sf/0u/authenticate', function () {
    Forrest::authenticate();
    return \Redirect::to('/backend/waka/salesforce/logsfs');
});
Route::get('/api/sf/0u/callback', function () {
    Forrest::authenticate();
    return \Redirect::to('/backend/waka/salesforce/logsfs');
});

// ROUTES POUR AUTHENTIFICATION CLASSIQUE SANS JWT
Route::group(['middleware' => ['web']], function () {
    Route::get('/api/sf/wu/authenticate', function () {
        return Forrest::authenticate();
    });
    Route::get('/api/sf/wu/callback', function () {
        return \Redirect::to('/backend/waka/salesforce/logsfs');
    });
});
