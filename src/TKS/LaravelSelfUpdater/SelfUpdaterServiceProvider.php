<?php
/**
 * Copyright (C) 2015 Tobias Knipping
 *
 * based on th Work of Valera Trubachev
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace TKS\LaravelSelfUpdater;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SelfUpdaterServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        Route::get(Config::get('self-updater::routes.manual', '/trigger_update'), array(
            'before' => Config::get('self-updater::routes.manual_filter', null),
            'uses' => 'TKS\LaravelSelfUpdater\UpdateController@triggerManualUpdate',
        ));
        Route::post(Config::get('self-updater::routes.auto', '/trigger_update'), array(
            'uses' => 'TKS\LaravelSelfUpdater\UpdateController@triggerAutoUpdate',
        ));
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
    }

}
